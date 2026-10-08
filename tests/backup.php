<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
use FalconWF\Backup\Manager as B;
global $wpdb;$original=$wpdb->prefix;$prefix='fwbt_'.bin2hex(random_bytes(5)).'_';$tables=[];$backups=[];$count=0;$folder=null;$memoryLimit=ini_get("memory_limit");ini_set("memory_limit","128M");
$ok=static function($pass,$label) use (&$count) { if (!$pass) { throw new RuntimeException('FAIL: '.$label); }$count++;echo "PASS: $label\n"; };
$must=static function($value) { if (is_wp_error($value)) { throw new RuntimeException($value->get_error_message()); }return $value; };
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
try {
 $dir=$must(B::directory());$ok((fileperms($dir)&0077)===0 && !str_starts_with(realpath($dir),realpath(ABSPATH).'/'),'storage private and outside WordPress');
 $sources=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($original).'%'));
 foreach ($sources as $source) {
  if (!preg_match('/^[a-zA-Z0-9_]+$/D',$source)) { throw new RuntimeException('Invalid source table.'); }
  $target=$prefix.substr($source,strlen($original));
  if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($target)))) { throw new RuntimeException('Fixture collision.'); }
  if ($wpdb->query("CREATE TABLE `$target` LIKE `$source`")===false) { throw new RuntimeException('Clone schema failed.'); }$tables[]=$target;
  if ($wpdb->query("INSERT INTO `$target` SELECT * FROM `$source`")===false) { throw new RuntimeException('Clone rows failed.'); }
 }
 $wpdb->set_prefix($prefix);wp_cache_flush();
 $ok(is_wp_error(B::create([])) && is_wp_error(B::create(['unknown'])) && is_wp_error(B::create([['database']])),'empty/unknown backup components rejected');
 update_option('fwf_identity',['name'=>'backup-fixture','contact'=>'public']);
 $id=$must(B::create(['settings']));$backups[]=$id;$review=$must(B::inspect($id));
 $ok($review['manifest']['components']===['settings'] && count($review['manifest']['entries'])===1,'settings-only ZIP has manifest and exact inventory');
 $must(B::saveNote($id,'Sebelum update desain — klien'));$ok(B::listing()[$id]['note']==='Sebelum update desain — klien','archive notes saved as protected local metadata');
 $ok(is_wp_error(B::saveNote($id,str_repeat('a',1001))) && is_wp_error(B::saveNote('../bad','no')),'invalid notes and note target traversal denied');
 $deletion=$must(B::reviewRemoval($id));$ok(is_wp_error(B::remove($id,$deletion['sha256'],false)) && is_wp_error(B::remove($id,str_repeat('0',64),true)) && is_file($must(B::path($id))),'delete requires exact reviewed hash and confirmation');
 $victim=$must(B::create(['settings']));$must(B::saveNote($victim,'Disposable fixture'));$deleteReview=$must(B::reviewRemoval($victim));$must(B::remove($victim,$deleteReview['sha256'],true));
 $ok(is_wp_error(B::path($victim)) && get_option('fwf_backup_note_'.$victim,false)===false && get_option('fwf_identity')['name']==='backup-fixture','confirmed deletion removes only selected ZIP and note, preserves site');
 $zip=new ZipArchive();$zip->open($must(B::path($id)));$stat=$zip->statName('database.json');$zip->close();$ok(($stat['encryption_method']??0)===0,'ZIP has no password/encryption');
 $ok(is_wp_error(B::path('../bad')) && is_wp_error(B::restore($id,$review['sha256'],$review['revision'],false)),'path traversal and unconfirmed restore rejected');
 update_option('fwf_identity',['name'=>'changed','contact'=>'']);
 $ok(is_wp_error(B::restore($id,$review['sha256'],$review['revision'],true)),'stale live-site revision blocks restore');
 $review=$must(B::inspect($id));$before=array_keys(B::listing());$must(B::restore($id,$review['sha256'],$review['revision'],true));$backups=array_merge($backups,array_diff(array_keys(B::listing()),$before));
 $ok(get_option('fwf_identity')['name']==='backup-fixture' && B::listing()[$id]['note']==='Sebelum update desain — klien','reviewed settings restore persists exact prior values');
 $ok(count(array_diff(array_keys(B::listing()),$before))===1,'restore creates safety backup first');
 $import=$must(B::import($must(B::path($id))));$backups[]=$import;$ok($must(B::inspect($import))['manifest']['site']===$review['manifest']['site'],'local ZIP import revalidates same installation signature');
 $tampered=$dir.'/tampered-'.wp_generate_uuid4().'.zip';copy($must(B::path($id)),$tampered);$zip=new ZipArchive();$zip->open($tampered);$zip->addFromString('database.json','{}');$zip->close();
 $ok(is_wp_error(B::import($tampered)),'corrupt payload checksum refused');unlink($tampered);
 $tampered=$dir.'/tampered-'.wp_generate_uuid4().'.zip';copy($must(B::path($id)),$tampered);$zip=new ZipArchive();$zip->open($tampered);$zip->addFromString('signature.txt',str_repeat('0',64));$zip->close();$ok(is_wp_error(B::import($tampered)),'foreign/unsigned ZIP refused');unlink($tampered);
 $folder=wp_get_upload_dir()['basedir'].'/fwf-backup-fixture-'.wp_generate_uuid4();wp_mkdir_p($folder);file_put_contents($folder.'/report.pdf','ORIGINAL REPORT');
 $large=$folder.'/large.bin';$handle=fopen($large,'wb');fwrite($handle,'ORIGINAL');ftruncate($handle,100*1024*1024);fclose($handle);$largeHash=hash_file('sha256',$large);
 $largeTable=$prefix.'large';$wpdb->query("CREATE TABLE `$largeTable` (id bigint NOT NULL PRIMARY KEY, value longtext NOT NULL) ENGINE=InnoDB");$tables[]=$largeTable;
 $value=str_repeat('x',65536);for ($i=1;$i<=640;$i++) { if ($wpdb->insert($largeTable,['id'=>$i,'value'=>$value])===false) { throw new RuntimeException('Large database fixture failed.'); } }unset($value);
 $estimates=$must(B::estimates());
 $ok($estimates['media']['bytes']>=100*1024*1024 && $estimates['database']['bytes']>40*1024*1024,'per-component estimates include 100MiB media and 40MiB database');
 $ok(B::selectedSize($estimates,['database','settings'])===$estimates['database']['bytes'],'database selection avoids duplicate settings size');
 $id=$must(B::create(array_keys(B::components())));$backups[]=$id;$review=$must(B::inspect($id));
 $sql=json_decode((function() use ($id,$must) {$z=new ZipArchive();$z->open($must(B::path($id)));$v=$z->getFromName('database.json');$z->close();return $v;})(),true);
 $ok(!isset($sql[$wpdb->users]) && !isset($sql[$wpdb->usermeta]),'database backup excludes authentication/account tables');
 update_option('fwf_request_fixture',['result'=>'keep-current']);update_option('fwf_ai_budget',['day'=>gmdate('Y-m-d'),'count'=>11]);
 $post=wp_insert_post(['post_title'=>'backup fixture added after snapshot','post_status'=>'draft']);
 file_put_contents($folder.'/report.pdf','CHANGED REPORT');$handle=fopen($large,'r+b');fwrite($handle,'MODIFIED');fclose($handle);$wpdb->update($largeTable,['value'=>'changed'],['id'=>1]);
 $review=$must(B::inspect($id));$active=get_option('active_plugins');$theme=get_option('stylesheet');$before=array_keys(B::listing());
 $fail=static function($query) use ($wpdb) {return str_starts_with($query,"INSERT INTO `{$wpdb->posts}`")?'INSERT INTO `fwf_missing_fixture_table` VALUES (1)':$query;};
 $suppressed=$wpdb->suppress_errors(true);add_filter('query',$fail);
 $failed=B::restore($id,$review['sha256'],$review['revision'],true);remove_filter('query',$fail);$wpdb->suppress_errors($suppressed);
 $backups=array_merge($backups,array_diff(array_keys(B::listing()),$before));
 $ok(is_wp_error($failed) && get_post($post)!==null && file_get_contents($folder.'/report.pdf')==='CHANGED REPORT','database failure rolls back rows and prior file writes');
 $ok(!(glob($dir.'/restore-*')?:[]),'handled failure leaves no unfinished journal');
 $review=$must(B::inspect($id));$before=array_keys(B::listing());
 $must(B::restore($id,$review['sha256'],$review['revision'],true));$backups=array_merge($backups,array_diff(array_keys(B::listing()),$before));
 $ok(hash_file('sha256',$large)===$largeHash && (int)$wpdb->get_var("SELECT SUM(OCTET_LENGTH(value)) FROM `$largeTable`")===40*1024*1024,'100MiB media and 40MiB database restore through streams');
 $ok(memory_get_peak_usage(true)<128*1024*1024,'large backup/restore fits 128MiB PHP memory');
 $ok(get_post($post)===null && file_get_contents($folder.'/report.pdf')==='ORIGINAL REPORT','actual transactional database and report-file recovery');
 $ok(get_option('fwf_request_fixture')===['result'=>'keep-current'] && get_option('fwf_ai_budget')['count']===11,'restore cannot rewind AI idempotency records or spending budget');
 $ok(get_option('active_plugins')===$active && get_option('stylesheet')===$theme,'restore preserves active plugin/theme selection');
 $deny=static function($caps) {$caps['fwf_manage_system']=false;return $caps;};add_filter('user_has_cap',$deny);
 $ok(is_wp_error(B::create(['settings'])) && is_wp_error(B::inspect($id)),'unauthorized backup and review denied');remove_filter('user_has_cap',$deny);
 $owner=FalconWF\Packages\Lock::acquire('backup');try {$ok(is_wp_error(B::create(['settings'])),'concurrent backup denied');}finally{FalconWF\Packages\Lock::release('backup',$owner);}
 echo "Backup recovery checks passed: $count. Isolated cloned tables, same-site local recovery only.\n";
} finally {
 ini_set('memory_limit',$memoryLimit);$wpdb->set_prefix($original);wp_cache_flush();
 foreach ($tables as $table) { if (!str_starts_with($table,$prefix)) { throw new RuntimeException('Cleanup ownership mismatch.'); }$wpdb->query("DROP TABLE `$table`"); }
 if ($folder && is_dir($folder)) { foreach (glob($folder.'/*')?:[] as $file) { unlink($file); }rmdir($folder); }
 foreach (array_unique($backups) as $id) { $path=B::path($id);if (!is_wp_error($path)) { unlink($path); } }
}
