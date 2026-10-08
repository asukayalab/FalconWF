<?php
namespace FalconWF\Backup;
use FalconWF\Packages\Lock;
/** Durable file undo journal and transactional commit proof. No normal WP bootstrap required. */
final class Recovery {
    private static function fail(string $message): never { throw new \RuntimeException($message); }
    private static function context(): array {
        global $wpdb;$database=$wpdb->get_var('SELECT DATABASE()');if (!$database || $wpdb->last_error) { self::fail('Database pemulihan tidak tersedia.'); }
        $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$wpdb->options));if ($engine!=='InnoDB' || $wpdb->last_error) { self::fail('Penanda commit memerlukan tabel options InnoDB.'); }
        return ['root'=>realpath(ABSPATH),'database'=>$database,'table'=>$wpdb->options];
    }
    private static function key(): string {
        $path=self::directory().'/.recovery-key';if (!is_file($path) || is_link($path) || (fileperms($path)&0077)!==0) { self::fail('Kunci journal privat tidak tersedia.'); }$key=file_get_contents($path);if (!preg_match('/^[a-f0-9]{64}$/D',$key)) { self::fail('Kunci journal tidak valid.'); }return $key;
    }
    private static function initializeKey(): void {
        $path=self::directory().'/.recovery-key';if (file_exists($path)) { self::key();return; }$handle=fopen($path,'xb');if (!$handle) { self::fail('Kunci journal gagal dibuat.'); }$key=bin2hex(random_bytes(32));try { if (!chmod($path,0600) || fwrite($handle,$key)!==strlen($key) || !fflush($handle) || !fsync($handle)) { self::fail('Kunci journal gagal disimpan.'); } }finally { fclose($handle); }
    }
    private static function id(string $id): void { if (!preg_match('/^[a-f0-9-]{36}$/D',$id)) { self::fail('ID journal tidak valid.'); } }
    private static function directory(): string { $dir=Manager::directory();if (is_wp_error($dir)) { self::fail($dir->get_error_message()); }return $dir; }
    private static function path(string $id): string { self::id($id);$base=self::directory();$pending=$base.'/restore-'.$id;$completed=$base.'/completed-restore-'.$id;if (file_exists($pending) && file_exists($completed)) { self::fail('Identitas journal bertentangan.'); }$path=file_exists($pending)?$pending:$completed;if (is_link($path) || !is_dir($path) || (fileperms($path)&0077)!==0) { self::fail('Journal tidak tersedia atau izin tidak aman.'); }return $path; }
    public static function databaseLease(): string {
        global $wpdb;$name='fwf_restore_'.substr(hash('sha256',json_encode(self::context())),0,48);$result=$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$name));if ((string)$result!=='1' || $wpdb->last_error) { self::fail('Koneksi restore lain masih aktif; jangan memulihkan journal.'); }return $name;
    }
    public static function releaseDatabase(string $name): void { global $wpdb;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name)); }
    private static function write(array $state): void {
        $path=self::path($state['id']).'/state.json';$body=json_encode($state,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);$encoded=json_encode(['state'=>$body,'signature'=>hash_hmac('sha256',$body,self::key())],JSON_THROW_ON_ERROR);$temp=$path.'.tmp';
        if (is_link($path) || is_link($temp)) { self::fail('Symlink journal ditolak.'); }$handle=fopen($temp,'wb');if (!$handle) { self::fail('Checkpoint journal gagal.'); }
        try { if (!chmod($temp,0600) || fwrite($handle,$encoded)!==strlen($encoded) || !fflush($handle) || !fsync($handle)) { self::fail('Checkpoint journal gagal.'); } }finally { fclose($handle); }
        if (!rename($temp,$path)) { self::fail('Checkpoint journal gagal.'); }
    }
    private static function load(string $id): array {
        $path=self::path($id).'/state.json';if (!is_file($path) || is_link($path) || (fileperms($path)&0077)!==0) { self::fail('Journal lama/rusak memerlukan pemeriksaan operator; tidak direplay.'); }
        $envelope=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);if (!is_string($envelope['state']??null) || !is_string($envelope['signature']??null) || !hash_equals(hash_hmac('sha256',$envelope['state'],self::key()),$envelope['signature'])) { self::fail('Signature journal tidak cocok.'); }
        $state=json_decode($envelope['state'],true,512,JSON_THROW_ON_ERROR);
        if (($state['schema']??null)!==1 || ($state['id']??null)!==$id || ($state['context']??null)!==self::context() || !in_array($state['phase']??null,['files','committing','uncertain','rollback','committed','rolled_back'],true) || !is_array($state['entries']??null) || !is_array($state['roots']??null) || !is_string($state['proof']??null) || !preg_match('/^[a-f0-9]{64}$/D',$state['proof']) || !is_string($state['updates_owner']??null) || !is_int($state['connection']??null)) { self::fail('Schema journal tidak cocok.'); }
        foreach ($state['roots'] as $component=>$root) { if (!in_array($component,['media','plugins','themes'],true) || !is_string($root) || is_link($root) || realpath($root)!==$root) { self::fail('Root journal tidak cocok.'); } }
        foreach ($state['entries'] as $index=>$entry) { if (!is_int($index) || !is_array($entry) || !isset($state['roots'][$entry['component']??'']) || !is_string($entry['relative']??null) || !self::relative($entry['relative']) || !is_bool($entry['existed']??null) || !is_int($entry['mode']??null) || !is_string($entry['after']??null) || !preg_match('/^[a-f0-9]{64}$/D',$entry['after']) || ($entry['existed'] && (!is_string($entry['before']??null) || !preg_match('/^[a-f0-9]{64}$/D',$entry['before'])))) { self::fail('Entry journal tidak valid.'); }self::target($state,$entry); }
        if (str_starts_with(basename(self::path($id)),'completed-') && !in_array($state['phase'],['committed','rolled_back'],true)) { self::fail('Journal yang ditutup belum terminal.'); }return $state;
    }
    private static function relative(string $path): bool { return $path!=='' && !str_contains($path,'\\') && !str_contains($path,"\0") && !preg_match('~(^/|^[A-Za-z]:|(^|/)(\.|\.\.)(/|$)|//)~',$path); }
    private static function target(array $state,array $entry): string {
        $root=$state['roots'][$entry['component']];$target=$root.'/'.$entry['relative'];$probe=$target;while ($probe!==$root) { if (is_link($probe)) { self::fail('Symlink target journal ditolak.'); }$probe=dirname($probe); }if (file_exists($target) && !is_file($target)) { self::fail('Target journal bukan file.'); }return $target;
    }
    public static function begin(array $roots,string $updatesOwner,string $archive,string $safety): array {
        self::initializeKey();$id=wp_generate_uuid4();$dir=self::directory().'/restore-'.$id;if (!mkdir($dir,0700)) { self::fail('Folder recovery gagal.'); }$normalized=[];foreach ($roots as $component=>$root) { if (is_link($root) || !($real=realpath($root))) { self::fail('Root recovery tidak tersedia.'); }$normalized[$component]=$real; }
        $state=['schema'=>1,'id'=>$id,'context'=>self::context(),'archive'=>$archive,'safety'=>$safety,'created'=>time(),'updates_owner'=>$updatesOwner,'connection'=>(int)$GLOBALS['wpdb']->get_var('SELECT CONNECTION_ID()'),'proof'=>bin2hex(random_bytes(32)),'roots'=>$normalized,'phase'=>'files','entries'=>[]];self::write($state);return $state;
    }
    public static function prepare(array &$state,string $component,string $relative,string $after): string {
        $entry=['component'=>$component,'relative'=>$relative,'after'=>$after];$target=self::target($state,$entry);$index=count($state['entries']);$entry['existed']=is_file($target);$entry['mode']=$entry['existed']?fileperms($target)&0777:0644;$entry['before']=$entry['existed']?hash_file('sha256',$target):null;
        if ($entry['existed']) { $slot=self::path($state['id']).'/'.$index;$handle=fopen($slot,'xb');$source=fopen($target,'rb');if (!$handle || !$source) { if($handle)fclose($handle);if($source)fclose($source);self::fail('Journal copy gagal.'); }try { if (!chmod($slot,0600) || stream_copy_to_stream($source,$handle)===false || !fflush($handle) || !fsync($handle)) { self::fail('Journal copy gagal.'); } }finally { fclose($source);fclose($handle); }if (!hash_equals($entry['before'],hash_file('sha256',$slot))) { self::fail('File berubah saat journal dibuat.'); } }
        $state['entries'][]=$entry;self::write($state);return dirname($target).'/.fwf-restore-'.$state['id'].'-'.$index;
    }
    public static function connectionIntact(array $state): bool {
        global $wpdb;$name='fwf_restore_'.substr(hash('sha256',json_encode(self::context())),0,48);$id=$wpdb->get_var($wpdb->prepare('SELECT IF(IS_USED_LOCK(%s)=CONNECTION_ID(),CONNECTION_ID(),0)',$name));return !$wpdb->last_error && (int)$id===($state['connection']??-1);
    }
    public static function uncertain(array &$state): void { $state['phase']='uncertain';self::write($state); }
    public static function committing(array &$state): void {
        global $wpdb;if (!self::connectionIntact($state)) { self::uncertain($state);self::fail('Koneksi transaksi berubah; hasil database memerlukan operator.'); }$state['phase']='committing';self::write($state);
        if ($wpdb->query($wpdb->prepare("INSERT INTO `$wpdb->options` (option_name,option_value,autoload) SELECT %s,%s,'off' WHERE CONNECTION_ID()=%d",'fwf_backup_restore_commit_'.$state['id'],$state['proof'],$state['connection']))!==1) { self::fail('Penanda commit restore gagal.'); }
    }
    private static function proof(array $state): bool {
        global $wpdb;$value=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM `$wpdb->options` WHERE option_name=%s",'fwf_backup_restore_commit_'.$state['id']));if ($wpdb->last_error) { self::fail('Hasil commit belum dapat dipastikan.'); }if ($value!==null && !hash_equals($state['proof'],$value)) { self::fail('Penanda commit berbeda; pemeriksaan operator diperlukan.'); }return $value!==null;
    }
    private static function decision(array $state): string {
        $committed=self::proof($state);if ($state['phase']==='uncertain' && !$committed) { self::fail('Koneksi transaksi berubah; hasil database belum pasti. Gunakan pemeriksaan operator dan backup keselamatan.'); }if (in_array($state['phase'],['rollback','rolled_back'],true) && $committed) { self::fail('Journal dan penanda commit bertentangan.'); }
        return $committed || $state['phase']==='committed'?'committed':'rollback';
    }
    private static function fingerprint(array $state,string $decision): string {
        $files=[];foreach ($state['entries'] as $entry) { $target=self::target($state,$entry);$files[]=is_file($target)?[hash_file('sha256',$target),fileperms($target)&0777]:null; }
        global $wpdb;$lock=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM `$wpdb->options` WHERE option_name=%s",'fwf_lock_updates'));if ($wpdb->last_error) { self::fail('Pembacaan lock gagal.'); }return hash('sha256',json_encode([$state,$decision,$files,$lock],JSON_THROW_ON_ERROR));
    }
    private static function applyState(array &$state,string $decision): void {
        global $wpdb;$dir=self::path($state['id']);if (str_starts_with(basename($dir),'completed-')) { self::cleanupSlots($dir);return; }
        $lock=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM `$wpdb->options` WHERE option_name=%s",'fwf_lock_updates'));if ($wpdb->last_error) { self::fail('Pembacaan lock gagal.'); }$stored=$lock===null?null:@unserialize($lock,['allowed_classes'=>false]);if ($stored!==null && (!is_array($stored) || ($stored['owner']??null)!==$state['updates_owner'])) { self::fail('Lock update milik operasi lain; journal dipertahankan.'); }
        if ($decision==='rollback' && $state['phase']!=='rolled_back') {
            // Check all targets and undo bytes before touching any file; later human edits block recovery.
            foreach ($state['entries'] as $index=>$entry) { $target=self::target($state,$entry);$hash=is_file($target)?hash_file('sha256',$target):null;if ($hash!==$entry['after'] && $hash!==$entry['before']) { self::fail('File target berubah setelah restore terputus; jangan timpa edit baru.'); }if ($entry['existed'] && (is_link($dir.'/'.$index) || !is_file($dir.'/'.$index) || !hash_equals($entry['before'],hash_file('sha256',$dir.'/'.$index)))) { self::fail('Undo copy journal hilang/rusak.'); } }
            $state['phase']='rollback';self::write($state);
            foreach (array_reverse($state['entries'],true) as $index=>$entry) {
                $target=self::target($state,$entry);$temp=dirname($target).'/.fwf-restore-'.$state['id'].'-'.$index;if (is_link($temp)) { self::fail('Symlink temp recovery ditolak.'); }
                if ($entry['existed']) { if (!copy($dir.'/'.$index,$temp) || !chmod($temp,$entry['mode']) || !rename($temp,$target)) { self::fail('Undo file gagal; journal dipertahankan.'); } }elseif (is_file($target) && !unlink($target)) { self::fail('Undo file baru gagal; journal dipertahankan.'); }
                if (is_file($temp) && !unlink($temp)) { self::fail('Pembersihan temp gagal.'); }if (function_exists('opcache_invalidate')) { opcache_invalidate($target,true); }
            }
            $state['phase']='rolled_back';self::write($state);
        }elseif ($decision==='committed') { $state['phase']='committed';self::write($state); }
        foreach ($state['entries'] as $index=>$entry) { $temp=dirname(self::target($state,$entry)).'/.fwf-restore-'.$state['id'].'-'.$index;if (is_link($temp)) { self::fail('Symlink temp recovery ditolak.'); }if (is_file($temp) && !unlink($temp)) { self::fail('Pembersihan temp gagal.'); } }
        if ($wpdb->delete($wpdb->options,['option_name'=>'fwf_backup_restore_commit_'.$state['id']])===false) { self::fail('Pembersihan penanda gagal.'); }
        if ($stored!==null && $wpdb->delete($wpdb->options,['option_name'=>'fwf_lock_updates','option_value'=>$lock])===false) { self::fail('Pembersihan lock gagal.'); }
        // Terminal decision persisted before removing proof/undo slots. Crash during cleanup is retryable.
        $retired=self::directory().'/completed-restore-'.$state['id'];if (file_exists($retired) || !rename($dir,$retired)) { self::fail('Penutupan journal gagal.'); }$dir=$retired;
        do_action('fwf_backup_restore_checkpoint','retired',$state['id']);self::cleanupSlots($dir);
    }
    private static function cleanupSlots(string $dir): void {
        foreach (new \DirectoryIterator($dir) as $file) { if ($file->isDot() || $file->getFilename()==='state.json') { continue; }if ($file->isLink() || !$file->isFile() || !preg_match('/^(?:[0-9]+|state\.json\.tmp)$/D',$file->getFilename())) { self::fail('File asing pada journal; jangan hapus.'); }if (!unlink($file->getPathname())) { self::fail('Pembersihan journal gagal.'); } }
        if (!unlink($dir.'/state.json') || !rmdir($dir)) { self::fail('Pembersihan journal gagal.'); }
    }
    /** Caller holds backup and database leases; used by the synchronous engine too. */
    public static function finish(array &$state): string { $decision=self::decision($state);self::applyState($state,$decision);return $decision; }
    public static function pending(): array { $result=[];foreach (glob(self::directory().'/restore-*')?:[] as $dir) { $id=substr(basename($dir),8);$result[]=$id; }return $result; }
    public static function completed(): array { return array_map(static fn($dir)=>substr(basename($dir),strlen('completed-restore-')),glob(self::directory().'/completed-restore-*')?:[]); }
    public static function operate(string $id,?string $review=null,bool $confirm=false): array {
        self::id($id);$handle=Lock::openFile(self::directory(),'backup');$lease=null;
        try {
            if (get_option('fwf_lock_backup',false)!==false) { self::fail('Lock backup lama memerlukan pemeriksaan operator.'); }$lease=self::databaseLease();$state=self::load($id);$decision=self::decision($state);$fingerprint=self::fingerprint($state,$decision);
            $summary=['id'=>$id,'decision'=>$decision,'phase'=>$state['phase'],'files'=>count($state['entries']),'safety'=>$state['safety'],'cleanup_only'=>str_starts_with(basename(self::path($id)),'completed-'),'review'=>$fingerprint];
            if ($review!==null) { if (!$confirm || !hash_equals($fingerprint,$review)) { self::fail('Konfirmasi/review journal berubah. Periksa ulang.'); }if (!in_array(wp_get_environment_type(),['local','staging'],true) || (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS)) { self::fail('Rescue fondasi hanya local/staging writable.'); }self::applyState($state,$decision);if (function_exists('wp_cache_flush')) { wp_cache_flush(); }$summary['resolved']=true; }return $summary;
        }finally { if ($lease!==null) { self::releaseDatabase($lease); }flock($handle,LOCK_UN);fclose($handle); }
    }
}
