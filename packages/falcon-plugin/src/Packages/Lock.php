<?php
namespace FalconWF\Packages;
final class Lock {
    public static function acquire(string $target): string|\WP_Error {
        $owner = wp_generate_uuid4();
        // Atomic unique option; never silently steal an expired lock from a live writer.
        if (!add_option('fwf_lock_' . sanitize_key($target), ['owner'=>$owner, 'time'=>time()], '', false)) {
            return new \WP_Error('FWF_LOCK', 'Operasi target masih terkunci. Periksa proses sebelum recovery.');
        }
        return $owner;
    }
    public static function release(string $target, string $owner): void {
        $key = 'fwf_lock_' . sanitize_key($target);
        if ((get_option($key)['owner'] ?? '') === $owner) { delete_option($key); }
    }
}
