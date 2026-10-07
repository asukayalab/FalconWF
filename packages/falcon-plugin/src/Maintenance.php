<?php
namespace FalconWF;
final class Maintenance {
    public static function render(): void {
        if (!get_option('fwf_maintenance',false) || current_user_can('fwf_manage_system') || is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) { return; }
        status_header(503); nocache_headers(); header('Retry-After: 3600'); header('X-Robots-Tag: noindex');
        echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pemeliharaan</title><main><h1>Website sedang dalam pemeliharaan</h1><p>Silakan kembali beberapa saat lagi.</p></main></html>';
        exit;
    }
}
