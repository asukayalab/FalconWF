<?php
if(wp_get_environment_type()!=='local' || defined('FWF_BACKUP_DIR')){throw new RuntimeException('Isolated local only');}
use FalconWF\Backup\Storage as S;
use FalconWF\Backup\Manager as B;
use FalconWF\Backup\Scheduler as C;
$root='/fwf-backups/transit-test-'.getenv('FWF_TRANSIT_TEST_OWNER');$_SERVER['DOCUMENT_ROOT']=$root.'/blocked/public';wp_set_current_user(get_user_by('login','fwf-admin')->ID);global $wpdb;
$names=['fwf_backup_directory','fwf_backup_storage_diagnostics'];$saved=[];foreach($names as $name){$saved[$name]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A);delete_option($name);}
$count=0;$ok=static function($x,$label)use(&$count){if(!$x)throw new RuntimeException('FAIL: '.$label);$count++;echo "PASS: $label\n";};
try{
 $path=S::provision();$ok(!is_wp_error($path) && dirname($path)===$root.'/transit' && S::temporary(),'blocked persistent parent automatically falls back to owned private transit');
 $ok((fileperms($path)&0777)===0700,'automatic transit mode0700');
 $ok(S::provision()===$path,'repeat provisioning preserves location');
 $ok(is_wp_error(B::queue(['settings'],false,'',null,'server')),'transit not mislabeled as permanent server archive');
 $ok(is_wp_error(C::save(['enabled'=>true,'frequency'=>'monthly','monthday'=>'31','weekday'=>'1','time'=>'02:00','components'=>['settings']],C::revision())),'automatic server schedule refuses volatile transit');
 $id=B::queue(['settings'],false,'',null,'download');$ok(!is_wp_error($id),'download uses existing queue');
 for($i=0;$i<16;$i++){$job=B::work($id);if(is_wp_error($job)){throw new RuntimeException($job->get_error_message());}if($job['phase']==='done')break;}
 $jobs=B::jobs();$ok(($jobs[$id]['phase']??null)==='done' && ($jobs[$id]['destination']??null)==='download','download job finalizes through existing archive engine');
 $ok(!is_wp_error(B::inspect($id)) && is_file(B::path($id)),'transit produces validated actual ZIP');
 $ok(is_wp_error(B::queue(['settings'],false,'',null,'cloud')),'unsupported destination rejected');
 // Persistent account home fallback, without changing an existing working location.
 delete_option('fwf_backup_directory');chmod($root.'/home',0700);clearstatcache();$persistent=S::provision();$ok(!is_wp_error($persistent) && dirname($persistent)===$root.'/home' && !S::temporary(),'available private account home selected before temporary storage');
 echo "Private transit checks passed: $count. Owned local paths only.\n";
}finally{
 foreach($saved as $name=>$row){$wpdb->delete($wpdb->options,['option_name'=>$name]);if($row){$wpdb->insert($wpdb->options,$row);}}wp_cache_flush();
 chmod($root.'/blocked',0700);chmod($root.'/home',0700);
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $file){$file->isDir()&&!$file->isLink()?rmdir($file->getPathname()):unlink($file->getPathname());}rmdir($root);
}
