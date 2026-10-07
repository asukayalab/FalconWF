<?php
namespace FalconWF\AI;
use FalconWF\Packages\Lock;
final class RequestGuard {
    public static function run(string $action, string $key, array $input, callable $callback): array|\WP_Error {
        if (!preg_match('/^[a-zA-Z0-9_-]{8,100}$/',$key)) { return new \WP_Error('FWF_VALIDATION','Idempotency key wajib, 8–100 karakter.'); }
        $name='fwf_request_' . hash('sha256',get_current_user_id() . ':' . $action . ':' . $key);
        $owner=Lock::acquire($name); if (is_wp_error($owner)) { return $owner; }
        try {
            $fingerprint=hash('sha256',wp_json_encode($input)); $old=get_option($name,false);
            if ($old) {
                if (!hash_equals($old['fingerprint'],$fingerprint)) { return new \WP_Error('FWF_CONFLICT','Key sudah digunakan untuk input lain.'); }
                if (!isset($old['result'])) { return new \WP_Error('FWF_CONFLICT','Hasil operasi belum diketahui; periksa draft dan audit sebelum recovery.'); }
                return $old['result'];
            }
            // Persist before mutation so a crash cannot silently create a second draft on retry.
            if (!add_option($name,['fingerprint'=>$fingerprint,'at'=>time()], '', false)) { return new \WP_Error('FWF_LOCK','Operasi concurrent.'); }
            $result=$callback();
            if (is_wp_error($result)) {
                // Known pre-write refusal can be retried; unknown/post-write failures remain blocked.
                if (in_array($result->get_error_code(),['FWF_VALIDATION','FWF_PERMISSION','FWF_CONFLICT','FWF_NOT_FOUND','FWF_LOCK'],true)) { delete_option($name); }
                return $result;
            }
            update_option($name,['fingerprint'=>$fingerprint,'at'=>time(),'result'=>$result],false);
            return $result;
        } finally { Lock::release($name,$owner); }
    }
    public static function limit(int $actor): true|\WP_Error {
        $owner=Lock::acquire('rate_' . $actor); if (is_wp_error($owner)) { return $owner; }
        try {
            $key='fwf_rate_' . $actor; $s=get_option($key,['start'=>time(),'count'=>0]);
            if ($s['start']+60<time()) { $s=['start'=>time(),'count'=>0]; }
            if ($s['count']>=30) { return new \WP_Error('FWF_RATE','Batas 30 request per menit tercapai.'); }
            $s['count']++; update_option($key,$s,false); return true;
        } finally { Lock::release('rate_' . $actor,$owner); }
    }
    public static function cleanup(): void {
        // Pending operation records are retained for manual reconciliation.
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('fwf_request_').'%'), ARRAY_A);
        foreach ($rows as $row) { $v=maybe_unserialize($row['option_value']); if (isset($v['result']) && ($v['at']??time())<time()-7*DAY_IN_SECONDS) { delete_option($row['option_name']); } }
        $proposals=$wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('fwf_proposal_').'%'), ARRAY_A);
        foreach ($proposals as $row) { $v=maybe_unserialize($row['option_value']); if (($v['at']??0)<time()-DAY_IN_SECONDS) { delete_option($row['option_name']); } }
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}fwf_audit WHERE created_at < %s",gmdate('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS)));
    }
}
