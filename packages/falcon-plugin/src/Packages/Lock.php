<?php
namespace FalconWF\Packages;
final class Lock {
    private static array $leases = [];
    public static function acquire(string $target): string|\WP_Error {
        if (in_array($target,['backup','backup_schedule'],true)) {
            $dir=\FalconWF\Backup\Manager::directory();if (is_wp_error($dir)) { return $dir; }
            try { $handle=self::openFile($dir,$target); }catch (\Throwable $e) { return new \WP_Error('FWF_LOCK',$e->getMessage()); }
            if (get_option('fwf_lock_'.$target,false)!==false) { fclose($handle);return new \WP_Error('FWF_LOCK','Lock backup lama memerlukan pemeriksaan operator.'); }
            $owner=wp_generate_uuid4();self::$leases[$owner]=$handle;return $owner;
        }
        $owner = wp_generate_uuid4();
        // Atomic unique option; never silently steal an expired lock from a live writer.
        if (!add_option('fwf_lock_' . sanitize_key($target), ['owner'=>$owner, 'time'=>time()], '', false)) {
            return new \WP_Error('FWF_LOCK', 'Operasi target masih terkunci. Periksa proses sebelum recovery.');
        }
        return $owner;
    }
    /** One filesystem lease implementation, also usable during SHORTINIT rescue. */
    public static function openFile(string $directory,string $target) {
        if (!in_array($target,['backup','backup_schedule'],true)) { throw new \RuntimeException('Target lease tidak valid.'); }
        $path=$directory.'/.'.$target.'.lock';if (is_link($path)) { throw new \RuntimeException('Lease backup tidak valid.'); }
        $handle=fopen($path,'c');if (!$handle || !chmod($path,0600) || !flock($handle,LOCK_EX|LOCK_NB)) { if ($handle) { fclose($handle); }throw new \RuntimeException('Backup/restore sedang berjalan.'); }return $handle;
    }
    public static function release(string $target, string $owner): void {
        if (in_array($target,['backup','backup_schedule'],true)) { if (isset(self::$leases[$owner])) { flock(self::$leases[$owner],LOCK_UN);fclose(self::$leases[$owner]);unset(self::$leases[$owner]); }return; }
        $key = 'fwf_lock_' . sanitize_key($target);
        if ((get_option($key)['owner'] ?? '') === $owner) { delete_option($key); }
    }
}
