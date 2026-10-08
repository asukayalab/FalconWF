<?php
use FalconWF\Backup\Manager as B;
if(wp_get_environment_type()!=='local' || FWF_BACKUP_DIR!=='/fwf-backups/retention-test-'.getenv('FWF_RETENTION_TEST_OWNER'))throw new RuntimeException('Isolated local directory required.');
global $wpdb;$keys=['fwf_backup_retention','fwf_backup_retention_result'];$saved=[];$ids=[];$count=0;
foreach($keys as $key)$saved[$key]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$key),ARRAY_A);
$must=static function($value){if(is_wp_error($value))throw new RuntimeException($value->get_error_message());return $value;};
$ok=static function($value,$label)use(&$count){if(!$value)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";$count++;};
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$create=static function($background=false)use(&$ids,$must){$id=$must(B::queue(['settings'],$background));$ids[]=$id;for($n=0;$n<12;$n++){$state=$must(B::work($id));if($state['phase']==='failed')throw new RuntimeException($state['error']);if($state['phase']==='done')return $id;}throw new RuntimeException('Incomplete job.');};
try{
 delete_option('fwf_backup_retention');$ok(B::retention()===['enabled'=>false,'limit'=>5],'retention default is opt-in with five ordinary archives');
 $rev=B::retentionRevision();$ok(is_wp_error(B::saveRetention(true,'0',$rev)) && is_wp_error(B::saveRetention(true,'1001',$rev)) && is_wp_error(B::saveRetention(true,'1.5',$rev)),'invalid retention counts rejected');
 $must(B::saveRetention(false,'2',$rev));$ok(is_wp_error(B::saveRetention(true,'2',$rev)),'stale retention revision rejected');
 $old=$create();$middle=$create();$protected=$create();touch($must(B::path($old)),time()-300);touch($must(B::path($middle)),time()-200);touch($must(B::path($protected)),time()-100);clearstatcache();
 $must(B::saveNote($old,'Owned disposable archive'));$must(B::protect($protected,true));
 $ok(B::listing()[$protected]['protected'],'protected marker persisted in listing');
 $review=$must(B::reviewRemoval($protected));$ok(is_wp_error(B::remove($protected,$review['sha256'],true)) && is_file($must(B::path($protected))),'protected archive rejects confirmed manual deletion');
 $must(B::saveRetention(true,2,B::retentionRevision()));$ok(count(B::listing())===3,'saving policy does not immediately delete archives');
 $failed=$must(B::queue(['settings'],false));$ids[]=$failed;$must(B::control($failed,true));$ok(count(B::listing())===3,'cancelled backup does not trigger retention');
 $failedJob=$must(B::queue(['settings'],false));$ids[]=$failedJob;$statePath=FWF_BACKUP_DIR.'/job-'.$failedJob.'/state.json';$state=json_decode(file_get_contents($statePath),true);$state['owner']=0;file_put_contents($statePath,wp_json_encode($state));$failure=B::work($failedJob);$state=B::jobs()[$failedJob];$ok(is_wp_error($failure) && $state['phase']==='failed' && count(B::listing())===3,'failed backup preserves all prior archives');
 $new=$create(true);$items=B::listing();$ok(!isset($items[$old]) && isset($items[$middle],$items[$protected],$items[$new]) && count($items)===3,'successful background archive prunes oldest ordinary archive and excludes protected quota');
 $ok(get_option('fwf_backup_note_'.$old,false)===false && !is_dir(FWF_BACKUP_DIR.'/job-'.$old),'retention uses shared deletion path and cleans note and terminal job');
 $ok(get_option('fwf_backup_retention_result')['deleted']===1,'retention reports actual deletion count');
 $must(B::protect($protected,false));$ok(!B::listing()[$protected]['protected'] && count(B::listing())===3,'unprotect makes eligible without immediate deletion');
 $safety=$create(false);$ok(count(B::listing())===4 && isset(B::listing()[$new]),'synchronous safety snapshot preserves source and skips pruning');
 $next=$create(true);$ok(count(B::listing())===2 && isset(B::listing()[$next]),'next successful background archive enforces quota');
 $must(B::saveRetention(false,2,B::retentionRevision()));$create(true);$ok(count(B::listing())===3,'disabled retention preserves all archives');
 $must(B::protect($next,true));$policy=B::retention();$review=$must(B::inspect($next));$beforeRestore=count(B::listing());$must(B::restore($next,$review['sha256'],$review['revision'],true));$ids=array_unique(array_merge($ids,array_keys(B::listing())));$ok(B::listing()[$next]['protected'] && B::retention()===$policy && count(B::listing())===$beforeRestore+1,'settings restore preserves protection and retention policy with safety archive');
 wp_set_current_user(0);$ok(is_wp_error(B::protect($next,true)) && is_wp_error(B::saveRetention(true,2,B::retentionRevision())),'protection and retention require system capability');wp_set_current_user(get_user_by('login','fwf-admin')->ID);
 $ok(is_wp_error(B::protect('../escape',true)),'protection target traversal rejected');
 echo "Backup retention checks passed: $count. Isolated Docker directory; no client archives used.\n";
}finally{
 wp_set_current_user(get_user_by('login','fwf-admin')->ID);
 foreach($ids as $id){B::forget($id);delete_option('fwf_backup_protected_'.$id);delete_option('fwf_backup_note_'.$id);$path=FWF_BACKUP_DIR.'/'.$id.'.zip';if(is_file($path))unlink($path);}
 foreach($saved as $key=>$row){$wpdb->delete($wpdb->options,['option_name'=>$key]);if($row && $wpdb->insert($wpdb->options,$row)===false)throw new RuntimeException('Policy restoration failed.');}wp_cache_flush();
 if(is_file(FWF_BACKUP_DIR.'/.recovery-key'))unlink(FWF_BACKUP_DIR.'/.recovery-key');if(is_file(FWF_BACKUP_DIR.'/.backup.lock'))unlink(FWF_BACKUP_DIR.'/.backup.lock');if(!rmdir(FWF_BACKUP_DIR))throw new RuntimeException('Fixture cleanup incomplete.');
}
