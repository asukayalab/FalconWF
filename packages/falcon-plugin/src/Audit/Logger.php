<?php
namespace FalconWF\Audit;
final class Logger {
    private static ?string $requestId = null;
    public static function install(): true|\WP_Error {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = $wpdb->prefix . 'fwf_audit';
        dbDelta("CREATE TABLE $table (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            actor bigint unsigned NOT NULL DEFAULT 0,
            action varchar(80) NOT NULL,
            object_id bigint unsigned NOT NULL DEFAULT 0,
            result varchar(80) NOT NULL,
            request_id varchar(36) NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) " . $wpdb->get_charset_collate() . ';');
        $exists=$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)));
        return $exists===$table?true:new \WP_Error('FWF_MIGRATION','Audit table gagal dibuat; schema belum ditandai selesai.');
    }
    // Allowlisted columns only: no request bodies, tokens, URLs, prompts or responses.
    public static function write(string $action, string $result, int $object = 0, ?int $actor = null): string|\WP_Error {
        global $wpdb;
        $id = self::$requestId ??= wp_generate_uuid4();
        $ok = $wpdb->insert($wpdb->prefix . 'fwf_audit', [
            'created_at' => current_time('mysql', true), 'actor' => $actor ?? get_current_user_id(),
            'action' => sanitize_key($action), 'object_id' => $object,
            'result' => sanitize_key($result), 'request_id' => $id,
        ], ['%s', '%d', '%s', '%d', '%s', '%s']);
        return $ok === false ? new \WP_Error('FWF_AUDIT', 'Audit tidak dapat disimpan; operasi dihentikan.') : $id;
    }
    public static function recent(): array {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'fwf_audit ORDER BY id DESC LIMIT 50', ARRAY_A) ?: [];
    }
}
