<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
global $wpdb;$mode=$args[0]??'';$owner=$args[1]??'';$key='fwf_backup_http_test';
if (!preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Owner required.'); }
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if ($mode==='capture') {
 $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",'fwf_identity'),ARRAY_A);
 $scheduleRows=[];foreach (['fwf_backup_schedule','fwf_backup_schedule_state','fwf_backup_schedule_error','fwf_backup_schedule_paused','cron'] as $name) { $scheduleRows[$name]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A); }
 if (!add_option($key,['owner'=>$owner,'row'=>$row,'schedule_rows'=>$scheduleRows],'',false)) { throw new RuntimeException('Prior backup HTTP snapshot requires recovery.'); }
 $relative='fwf-http-media-'.$owner.'/report.pdf';$file=wp_get_upload_dir()['basedir'].'/'.$relative;wp_mkdir_p(dirname($file));file_put_contents($file,'OWNED HTTP REPORT');$id=wp_insert_attachment(['post_title'=>'FWF HTTP '.$owner,'post_mime_type'=>'application/pdf','guid'=>home_url('/uploads/'.$relative)],$file,0,true);if(is_wp_error($id))throw new RuntimeException('Attachment fixture failed');update_post_meta($id,'_wp_attached_file',$relative);$snapshot=get_option($key);$snapshot['media_id']=$id;$snapshot['media_path']=$file;update_option($key,$snapshot,false);echo wp_json_encode(['media_id'=>$id]);
} elseif ($mode==='makejournal') {
 $snapshot=get_option($key);$id=$args[2]??'';if(($snapshot['owner']??null)!==$owner || !preg_match('/^[a-f0-9-]{36}$/D',$id))throw new RuntimeException('Owner mismatch');$backup=FalconWF\Backup\Manager::inspect($id);if(is_wp_error($backup) || $backup['manifest']['components']!==['settings'])throw new RuntimeException('Owned settings archive required');
 $lease=FalconWF\Packages\Lock::acquire('backup');if(is_wp_error($lease))throw new RuntimeException('Backup lease unavailable');$db=FalconWF\Backup\Recovery::databaseLease();$updates=FalconWF\Packages\Lock::acquire('updates');if(is_wp_error($updates))throw new RuntimeException('Update lease unavailable');try{$state=FalconWF\Backup\Recovery::begin(['media'=>wp_get_upload_dir()['basedir'],'plugins'=>WP_PLUGIN_DIR,'themes'=>get_theme_root()],$updates,$id,$id);$snapshot['journal']=$state['id'];update_option($key,$snapshot,false);echo wp_json_encode(['journal'=>$state['id']]);}finally{FalconWF\Backup\Recovery::releaseDatabase($db);FalconWF\Packages\Lock::release('backup',$lease);}
} elseif ($mode==='runjob') {
 $snapshot=get_option($key);$id=$args[2]??'';if (($snapshot['owner']??null)!==$owner || !preg_match('/^[a-f0-9-]{36}$/D',$id) || !str_starts_with(get_option('fwf_identity')['name']??'','FWF HTTP '.$owner.' ')) { throw new RuntimeException('Owner mismatch.'); }
 for ($i=0;$i<10;$i++) { do_action('fwf_backup_step',$id);$jobs=FalconWF\Backup\Manager::jobs();if (is_wp_error($jobs)) { throw new RuntimeException($jobs->get_error_message()); }$state=$jobs[$id];if ($state['phase']==='done') { break; }if ($state['phase']==='failed') { throw new RuntimeException($state['error']); } }
 if ($state['phase']!=='done') { throw new RuntimeException('Job incomplete.'); }
 FalconWF\Backup\Manager::forget($id);
} elseif ($mode==='cleanup') {
 $snapshot=get_option($key);if (!is_array($snapshot) || $snapshot['owner']!==$owner) { throw new RuntimeException('Owner mismatch.'); }
 if(isset($snapshot['journal']) && is_dir(FWF_BACKUP_DIR.'/restore-'.$snapshot['journal'])){$review=FalconWF\Backup\Manager::recovery($snapshot['journal']);if(is_wp_error($review) || $review['files']!==0)throw new RuntimeException('Owned empty journal cleanup refused');$result=FalconWF\Backup\Manager::recovery($review['id'],$review['review'],true);if(is_wp_error($result))throw new RuntimeException('Owned journal cleanup failed');}
 foreach (FalconWF\Backup\Manager::listing() as $id=>$item) {
  $path=FalconWF\Backup\Manager::path($id);$review=FalconWF\Backup\Manager::inspect($id);if (is_wp_error($review)) { continue; }if (($review['manifest']['media_selection']??null)===[$snapshot['media_id']]) { FalconWF\Backup\Manager::forget($id);if(!unlink($path))throw new RuntimeException('Selected ZIP cleanup failed');continue; }if ($review['manifest']['components']!==['settings']) { continue; }
  $z=new ZipArchive();$z->open($path);$data=json_decode($z->getFromName('database.json'),true);$z->close();
  foreach ($data['settings']??[] as $row) { if ($row['option_name']==='fwf_identity') { $value=maybe_unserialize($row['option_value']);if (is_array($value) && str_starts_with($value['name']??'','FWF HTTP '.$owner.' ')) { if (is_dir(FWF_BACKUP_DIR.'/job-'.$id)) { $forgot=FalconWF\Backup\Manager::forget($id);if(is_wp_error($forgot))throw new RuntimeException($forgot->get_error_message()); }if (!unlink($path)) { throw new RuntimeException('Owned ZIP cleanup failed.'); }delete_option('fwf_backup_note_'.$id); } } }
 }
 if ($snapshot['row']) { $wpdb->delete($wpdb->options,['option_name'=>'fwf_identity']);if ($wpdb->insert($wpdb->options,$snapshot['row'])===false) { throw new RuntimeException('Identity restore failed.'); } }else { delete_option('fwf_identity'); }
 foreach ($snapshot['schedule_rows']??[] as $name=>$row) { $wpdb->delete($wpdb->options,['option_name'=>$name]);if ($row && $wpdb->insert($wpdb->options,$row)===false) { throw new RuntimeException('Schedule settings restore failed.'); } }
 $media=get_post($snapshot['media_id']);if(!$media || $media->post_title!=='FWF HTTP '.$owner || get_post_meta($media->ID,'_wp_attached_file',true)!=='fwf-http-media-'.$owner.'/report.pdf')throw new RuntimeException('Media cleanup owner mismatch');$wpdb->delete($wpdb->postmeta,['post_id'=>$media->ID]);$wpdb->delete($wpdb->posts,['ID'=>$media->ID]);if(!unlink($snapshot['media_path']) || !rmdir(dirname($snapshot['media_path'])))throw new RuntimeException('Media cleanup failed');
 delete_option($key);wp_cache_flush();
} else { throw new RuntimeException('Unknown mode.'); }
