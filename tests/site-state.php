<?php
// Reversible settings guard for the isolated local integration harness only.
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
global $wpdb;
$mode=$args[0]??''; $owner=$args[1]??''; $key='fwf_test_site_state';
if (!is_string($owner) || !preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Snapshot owner required.'); }
$names=['blogname','blogdescription','home','siteurl','stylesheet','template','active_plugins','rewrite_rules'];
$select=static function () use ($wpdb,$names,$key) {
    $rows=$wpdb->get_results($wpdb->prepare("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like('fwf_').'%'),ARRAY_A);
    foreach ($names as $name) { $row=$wpdb->get_row($wpdb->prepare("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A); if ($row) { $rows[]=$row; } }
    return array_values(array_filter($rows,static fn($row)=>$row['option_name']!==$key));
};
if ($mode==='fingerprint') {
    $rows=array_column($select(),null,'option_name');ksort($rows);echo hash('sha256',serialize($rows));
} elseif ($mode==='capture') {
    if (!add_option($key,['owner'=>$owner,'rows'=>$select()],'',false)) { throw new RuntimeException('Previous site snapshot requires explicit recovery; not overwritten.'); }
    echo "Site settings guard captured (values hidden).\n";
} elseif ($mode==='restore') {
    $saved=get_option($key,false);
    if (!$saved || !hash_equals($saved['owner'],$owner)) { throw new RuntimeException('Snapshot missing or owner mismatch.'); }
    $before=array_column($saved['rows'],null,'option_name');
    foreach ($select() as $row) { if (!isset($before[$row['option_name']])) { delete_option($row['option_name']); } }
    // Restore exact stored bytes and autoload flags, without replaying activation/switch hooks.
    foreach ($before as $row) { if ($wpdb->replace($wpdb->options,$row,['%s','%s','%s'])===false) { throw new RuntimeException('Restore failed; snapshot retained for recovery.'); } }
    wp_cache_flush();
    $after=array_column($select(),null,'option_name'); ksort($before); ksort($after);
    if ($before!==$after) { throw new RuntimeException('Restore verification failed; snapshot retained.'); }
    delete_option($key);echo "Original site settings restored and verified (values hidden).\n";
} else { throw new RuntimeException('Unknown site state operation.'); }
