<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
use FalconWF\Backup\Manager as B;
$mode=$args[0]??'';$owner=$args[1]??'';
if (!preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Fixture owner required.'); }
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$key='fwf_backup_job_test_'.$owner;$fixture=get_option($key);
$must=static function($value) {if(is_wp_error($value))throw new RuntimeException($value->get_error_message());return $value;};
if ($mode==='capture') {
 if ($fixture!==false) { throw new RuntimeException('Owned fixture already exists.'); }
 $folder=wp_get_upload_dir()['basedir'].'/fwf-job-'.$owner;wp_mkdir_p($folder);
 for ($i=0;$i<20;$i++) { file_put_contents($folder.'/'.$i.'.txt','SNAPSHOT-'.$owner.'-'.$i); }
 $id=$must(B::queue(['media'],false));add_option($key,['id'=>$id,'folder'=>$folder],'',false);echo $id;
} elseif (!$fixture) { throw new RuntimeException('Owned fixture missing.'); }
elseif ($mode==='capture-step') {
 $state=$must(B::work($fixture['id']));if($state['phase']!=='packing')throw new RuntimeException('Expected captured snapshot.');
 file_put_contents($fixture['folder'].'/0.txt','LIVE CHANGED');echo wp_json_encode(['phase'=>$state['phase'],'total'=>$state['total']]);
} elseif ($mode==='pack-step') {
 $state=$must(B::work($fixture['id']));echo wp_json_encode(['phase'=>$state['phase'],'cursor'=>$state['cursor'],'total'=>$state['total']]);
} elseif ($mode==='die-with-lease') {
 $must(FalconWF\Packages\Lock::acquire('backup'));exit(13);
} elseif ($mode==='corrupt-zip') {
 $dir=$must(B::directory()).'/job-'.$fixture['id'];file_put_contents($dir.'/archive.zip','BROKEN ZIP');
 $result=B::work($fixture['id']);if (!is_wp_error($result)) { throw new RuntimeException('Corrupt ZIP must fail.'); }
 $state=$must(B::jobs())[$fixture['id']];echo $state['phase'];
} elseif ($mode==='resume') { $must(B::control($fixture['id']));echo 'resumed'; }
elseif ($mode==='finish') {
 for ($i=0;$i<100;$i++) { $state=$must(B::work($fixture['id']));if ($state['phase']==='done')break;if($state['phase']==='failed')throw new RuntimeException($state['error']); }
 $review=$must(B::inspect($fixture['id']));$path=$must(B::path($fixture['id']));$zip=new ZipArchive();$zip->open($path);$bytes=$zip->getFromName('files/media/'.basename($fixture['folder']).'/0.txt');$zip->close();
 if ($bytes!=='SNAPSHOT-'.$owner.'-0') { throw new RuntimeException('Resumed ZIP lost captured snapshot.'); }
 echo wp_json_encode(['phase'=>$state['phase'],'cursor'=>$state['cursor'],'total'=>$state['total'],'components'=>$review['manifest']['components']]);
} elseif ($mode==='snapshot-interrupt') {
 $id=$must(B::queue(['settings'],false));$fixture['interrupted']=$id;update_option($key,$fixture,false);
 add_filter('query',static function($query) { if (str_starts_with($query,'SELECT * FROM')) { exit(13); }return $query; });B::work($id);throw new RuntimeException('Expected interrupted snapshot.');
} elseif ($mode==='snapshot-restart') {
 $id=$fixture['interrupted'];$state=$must(B::work($id));if ($state['phase']!=='packing')throw new RuntimeException('Snapshot retry failed.');
 for($i=0;$i<5;$i++) { $state=$must(B::work($id));if($state['phase']==='done')break; }$must(B::inspect($id));$must(B::forget($id));unlink($must(B::path($id)));unset($fixture['interrupted']);update_option($key,$fixture,false);echo $state['phase'];
} elseif ($mode==='denial') {
 $deny=static function($caps){$caps['fwf_manage_system']=false;return $caps;};add_filter('user_has_cap',$deny);
 if(!is_wp_error(B::queue(['settings'])) || !is_wp_error(B::control($fixture['id'])) || !is_wp_error(B::jobs()))throw new RuntimeException('Capability denied paths failed.');remove_filter('user_has_cap',$deny);echo 'denied';
} elseif ($mode==='cancel') {
 $id=$must(B::queue(['settings'],false));$must(B::control($id,true));$state=$must(B::jobs())[$id];$must(B::forget($id));echo $state['phase'];
} elseif ($mode==='revocation') {
 $id=$must(B::queue(['settings'],false));$deny=static function($caps){$caps['fwf_manage_system']=false;return $caps;};add_filter('user_has_cap',$deny);
 $result=B::work($id);remove_filter('user_has_cap',$deny);$state=$must(B::jobs())[$id];$must(B::forget($id));if(!is_wp_error($result))throw new RuntimeException('Revoked worker accepted.');echo $state['phase'];
} elseif ($mode==='cleanup') {
 if(isset($fixture['interrupted'])) { $must(B::forget($fixture['interrupted']));$extra=B::path($fixture['interrupted']);if(!is_wp_error($extra))unlink($extra); }
 $must(B::forget($fixture['id']));$path=B::path($fixture['id']);if(!is_wp_error($path))unlink($path);
 foreach(glob($fixture['folder'].'/*')?:[] as $file)unlink($file);rmdir($fixture['folder']);delete_option($key);
} else { throw new RuntimeException('Unknown fixture mode.'); }
