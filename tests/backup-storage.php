<?php
if (wp_get_environment_type()!=='local' || defined('FWF_BACKUP_DIR')) { throw new RuntimeException('Unconfigured local fixture required.'); }
use FalconWF\Backup\Storage as S;
use FalconWF\Backup\Manager as B;
global $wpdb;
$root='/fwf-backups/storage-test-'.getenv('FWF_STORAGE_TEST_OWNER');$count=0;
// WP-CLI resets server fields after its --require phase; emulate the HTTP server here.
$_SERVER['DOCUMENT_ROOT']=$root.'/public';
$ok=static function($condition,$label) use (&$count) { if (!$condition) { throw new RuntimeException('FAIL: '.$label); }$count++;echo 'PASS: '.$label."\n"; };
$must=static function($result) { if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }return $result; };
$saved=[];foreach (['fwf_backup_directory','fwf_identity'] as $name) { $saved[$name]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A); }
$admin=get_user_by('login','fwf-admin')->ID;wp_set_current_user($admin);delete_option('fwf_backup_directory');$editor=null;
try {
    $ok(is_wp_error(B::directory()),'unconfigured storage is explicit');
    require_once ABSPATH.'wp-admin/includes/template.php';
    $dashboard=new FalconWF\Admin\Dashboard(WP_PLUGIN_DIR.'/falcon-wf/falcon-wf.php',FalconWF\Bootstrap::instance());
    ob_start();$dashboard->render('backup');$html=ob_get_clean();
    $ok(str_contains($html,'value="setup_backup_storage"') && str_contains($html,'Siapkan penyimpanan backup'),'unconfigured dashboard has a real setup action');
    $ok(count(glob($root.'/.falcon-wf-backups-*')?:[])===0,'read-only screen does not create storage');
    $editor=wp_insert_user(['user_login'=>'fwf-storage-test-'.bin2hex(random_bytes(6)),'user_pass'=>wp_generate_password(48,true),'role'=>'editor']);$must($editor);wp_set_current_user($editor);
    $ok(is_wp_error(S::setup()),'editor cannot configure private storage');wp_set_current_user($admin);
    mkdir($root.'/public/private',0700);
    $ok(is_wp_error(S::setup($root.'/public/private')),'document-root folder rejected even outside WordPress');
    $ok(is_wp_error(S::setup(ABSPATH)),'WordPress folder rejected');
    mkdir($root.'/open',0700);chmod($root.'/open',0777);
    $ok(is_wp_error(S::setup($root.'/open')),'world-readable folder rejected');
    mkdir($root.'/private',0700);symlink($root.'/private',$root.'/link');
    $ok(is_wp_error(S::setup($root.'/link')),'symlink folder rejected');
    mkdir($root.'/private/child',0700);
    $ok(is_wp_error(S::setup($root.'/link/child')),'symlink ancestor rejected');
    $ok(is_wp_error(S::setup($root.'/missing')),'manual setup does not create arbitrary folders');
    mkdir($root.'/locked',0700);mkdir($root.'/locked/public',0700);chmod($root.'/locked',0555);$_SERVER['DOCUMENT_ROOT']=$root.'/locked/public';
    $ok(is_wp_error(S::setup()) && get_option('fwf_backup_directory',false)===false,'server-denied automatic setup reports failure without saving a location');chmod($root.'/locked',0700);
    $_SERVER['DOCUMENT_ROOT']=WP_CONTENT_DIR;$ok(is_wp_error(S::setup()),'automatic candidate still inside WordPress is rejected');$_SERVER['DOCUMENT_ROOT']=$root.'/public';
    $dir=$must(S::setup());$ok(is_dir($dir) && (fileperms($dir)&0777)===0700 && dirname($dir)===$root,'automatic setup creates private sibling outside document root');
    $ok(B::directory()===$dir && $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name=%s",'fwf_backup_directory'))==='off','storage persisted non-autoload and shared by Manager');
    $ok(S::setup($root.'/private')===$dir,'repeat setup preserves original archive location');
    $configured=get_option('fwf_backup_directory');$_SERVER['DOCUMENT_ROOT']='';update_option('fwf_backup_directory',['path'=>$root.'/public/private','public_roots'=>$configured['public_roots']],false);
    $ok(is_wp_error(B::directory()),'stored public roots remain enforced in CLI without HTTP document root');update_option('fwf_backup_directory',$configured,false);$_SERVER['DOCUMENT_ROOT']=$root.'/public';
    update_option('fwf_identity',['name'=>'OWNED STORAGE FIXTURE'],false);
    $id=$must(B::create(['settings']));$review=$must(B::inspect($id));
    $ok(is_file($must(B::path($id))) && (fileperms($must(B::path($id)))&0777)===0600,'backup engine creates private ZIP without server constant');
    update_option('fwf_identity',['name'=>'CHANGED STORAGE FIXTURE'],false);$review=$must(B::inspect($id));
    $must(B::restore($id,$review['sha256'],$review['revision'],true));
    $ok(get_option('fwf_identity')['name']==='OWNED STORAGE FIXTURE' && B::directory()===$dir,'reviewed restore uses configured storage without rewinding location');
    ob_start();$dashboard->render('backup');$html=ob_get_clean();
    $ok(str_contains($html,'name="components[]"') && str_contains($html,'Backup berkala') && str_contains($html,'Download ZIP') && str_contains($html,'value="Restore"') && str_contains($html,'value="Delete"') && str_contains($html,'Lindungi backup ini') && str_contains($html,'Catatan admin / klien'),'ready dashboard exposes approved backup and archive controls');
    $lease=$must(FalconWF\Packages\Lock::acquire('backup'));$database=FalconWF\Backup\Recovery::databaseLease();$updates=$must(FalconWF\Packages\Lock::acquire('updates'));
    try { $journal=FalconWF\Backup\Recovery::begin(['media'=>wp_get_upload_dir()['basedir'],'plugins'=>WP_PLUGIN_DIR,'themes'=>get_theme_root()],$updates,$id,$id); } finally { FalconWF\Backup\Recovery::releaseDatabase($database);FalconWF\Packages\Lock::release('backup',$lease); }
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(WP_PLUGIN_DIR.'/falcon-wf/rescue.php').' --root='.escapeshellarg(ABSPATH).' --inspect='.escapeshellarg($journal['id']).' 2>&1',$lines,$status);
    $decoded=json_decode(end($lines),true);if ($status!==0) { throw new RuntimeException('Rescue fixture: '.implode("\n",$lines)); }
    $ok(($decoded['id']??null)===$journal['id'],'SHORTINIT rescue discovers option storage without server constant');
    $review=$must(B::recovery($journal['id']));$must(B::recovery($journal['id'],$review['review'],true));
    chmod($dir,0755);clearstatcache();$ok(is_wp_error(B::directory()),'changed unsafe permissions block storage');chmod($dir,0700);clearstatcache();
    echo "Backup storage checks passed: $count. Isolated owned directory; actual WordPress archive/restore.\n";
} finally {
    wp_set_current_user($admin);
    if (isset($updates) && is_string($updates)) { FalconWF\Packages\Lock::release('updates',$updates); }
    foreach ($saved as $name=>$row) { $wpdb->delete($wpdb->options,['option_name'=>$name]);if ($row) { $wpdb->insert($wpdb->options,$row); } }
    if ($editor && !is_wp_error($editor)) { require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($editor); }
    wp_cache_flush();
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) { if ($file->isLink() || !$file->isDir()) { unlink($file->getPathname()); }else { rmdir($file->getPathname()); } }rmdir($root);
}
