<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
global $wpdb;$mode=$args[0]??'';$owner=$args[1]??'';
if (!preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Owner required.'); }
$key='fwf_test_storage_http';$path='/fwf-backups/storage-http-'.$owner;
if ($mode==='capture') {
    $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",'fwf_backup_directory'),ARRAY_A);
    if (file_exists($path) || !add_option($key,['owner'=>$owner,'row'=>$row],'',false)) { throw new RuntimeException('Previous storage fixture requires cleanup.'); }
    if (!mkdir($path,0700)) { throw new RuntimeException('Owned private directory failed.'); }delete_option('fwf_backup_directory');echo $path;
} elseif ($mode==='cleanup') {
    $state=get_option($key);if (($state['owner']??null)!==$owner) { throw new RuntimeException('Owner mismatch.'); }
    $wpdb->delete($wpdb->options,['option_name'=>'fwf_backup_directory']);if ($state['row']) { $wpdb->insert($wpdb->options,$state['row']); }
    // This test only registers storage; no backup job or content mutation is permitted.
    if (is_dir($path) && !rmdir($path)) { throw new RuntimeException('Owned directory not empty; refusing cleanup.'); }
    delete_option($key);wp_cache_flush();echo 'Owned HTTP storage fixture restored';
} else { throw new RuntimeException('Unknown mode.'); }
