<?php
use FalconWF\Backup\Scheduler as S;
use FalconWF\Backup\Manager as B;
if(wp_get_environment_type()!=='local'||FWF_BACKUP_DIR!=='/fwf-backups/schedule-test-'.getenv('FWF_SCHEDULE_TEST_OWNER'))throw new RuntimeException('Isolated local fixture required.');
global $wpdb;$mode=$args[0]??'';$owner=getenv('FWF_SCHEDULE_TEST_OWNER');$key='fwf_backup_schedule_fixture';$count=0;
$ok=static function($value,$label)use(&$count){if(!$value)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label."\n";$count++;};
$must=static function($value){if(is_wp_error($value))throw new RuntimeException($value->get_error_message());return $value;};
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if($mode==='capture'){
 $keys=['fwf_backup_schedule','fwf_backup_schedule_state','fwf_backup_schedule_runs','fwf_backup_schedule_error','fwf_backup_schedule_paused','fwf_backup_retention','fwf_backup_retention_result','cron'];$saved=[];
 foreach($keys as $name)$saved[$name]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A);
 if(!add_option($key,['owner'=>$owner,'saved'=>$saved],'',false))throw new RuntimeException('Previous fixture needs recovery.');
 foreach(array_diff($keys,['cron']) as $name)delete_option($name);wp_unschedule_hook(S::HOOK);
 $input=['enabled'=>true,'frequency'=>'daily','weekday'=>'1','time'=>'02:15','components'=>['settings']];
 $policy=$input+['timezone'=>'Asia/Jakarta'];$after=strtotime('2026-10-08T01:00:00+07:00');$ok(S::next($policy,$after)===strtotime('2026-10-08T02:15:00+07:00'),'daily schedule uses chosen local time');
 $policy['frequency']='weekly';$ok(S::next($policy,$after)===strtotime('2026-10-12T02:15:00+07:00'),'weekly schedule uses chosen weekday');
 $policy['frequency']='daily';$policy['timezone']='America/New_York';$policy['time']='09:00';$after=strtotime('2026-03-07T10:00:00-05:00');$ok(S::next($policy,$after)===strtotime('2026-03-08T09:00:00-04:00'),'DST keeps next calendar wall-clock time');
 $rev=S::revision();$bad=$input;$bad['time']='24:00';$ok(is_wp_error(S::save($bad,$rev)),'invalid time rejected');$bad=$input;$bad['components']=['unknown'];$ok(is_wp_error(S::save($bad,$rev)),'unknown component rejected');
 $must(S::save($input,$rev));$ok(is_wp_error(S::save($input,$rev)),'stale schedule revision rejected');$policy=S::policy();$state=get_option('fwf_backup_schedule_state');$first=wp_next_scheduled(S::HOOK,[$policy['generation'],$state['next']]);S::reconcile();S::reconcile();
 $events=0;foreach(_get_cron_array() as $hooks)$events+=count($hooks[S::HOOK]??[]);$ok($events===1 && $first===$state['next'],'reconciliation preserves exactly one event');
 $due=time()-5*DAY_IN_SECONDS;$state['next']=$due;update_option('fwf_backup_schedule_state',$state,false);wp_unschedule_hook(S::HOOK);$must(wp_schedule_single_event($due,S::HOOK,[$policy['generation'],$due],true));
 echo "Schedule setup checks passed: $count. Actual due cron runs in a separate process.\n";
}else{
 $fixture=get_option($key);if(($fixture['owner']??null)!==$owner)throw new RuntimeException('Fixture owner mismatch.');
 if($mode==='assert-run'){
  $runs=S::runs();$run=$runs[0];$jobs=$must(B::jobs());$ok($run['status']==='queued' && isset($jobs[$run['job']]),'real WP-Cron creates scheduled job through Manager');
  $policy=S::policy();$state=get_option('fwf_backup_schedule_state');$ok($state['next']>time(),'late cron advances to future slot without catchup flood');
  do_action(S::HOOK,$policy['generation'],$run['due']);$ok(count($must(B::jobs()))===1,'duplicate invocation cannot queue same slot twice');
  // A due slot while the previous snapshot is active is skipped.
  $state['next']=$state['claimed']+60;update_option('fwf_backup_schedule_state',$state,false);do_action(S::HOOK,$policy['generation'],$state['next']);$ok(S::runs()[0]['status']==='skipped' && count($must(B::jobs()))===1,'overlapping schedule skipped with reason');
  for($i=0;$i<12;$i++){$result=$must(B::work($run['job']));if($result['phase']==='done')break;}$ok($result['phase']==='done' && array_values(array_filter(S::runs(),static fn($r)=>$r['job']===$run['job']))[0]['status']==='done','archive completion records successful scheduled result');
  $ok($must(B::inspect($run['job']))['manifest']['components']===['settings'],'scheduled archive contains selected components');
  $old=S::policy();$state=get_option('fwf_backup_schedule_state');$must(S::save(['enabled'=>true,'frequency'=>'weekly','weekday'=>'7','time'=>'03:30','components'=>['settings']],S::revision()));do_action(S::HOOK,$old['generation'],$state['next']);$ok(count($must(B::jobs()))===1,'old generation cannot execute after schedule edit');
  $before=S::policy();$deny=static fn()=>false;add_filter('pre_schedule_event',$deny);$bad=S::save(['enabled'=>true,'frequency'=>'daily','weekday'=>'1','time'=>'04:00','components'=>['settings']],S::revision());remove_filter('pre_schedule_event',$deny);S::reconcile();$ok(is_wp_error($bad) && S::policy()===$before && wp_next_scheduled(S::HOOK,[S::policy()['generation'],get_option('fwf_backup_schedule_state')['next']]),'cron failure rolls back schedule policy and can rearm');
  S::deactivate();S::reconcile();$ok(get_option('fwf_backup_schedule_paused')===true && !wp_next_scheduled(S::HOOK,[S::policy()['generation'],get_option('fwf_backup_schedule_state')['next']]),'deactivation pauses events while retaining configuration and archives');
  S::activate();$ok(wp_next_scheduled(S::HOOK,[S::policy()['generation'],get_option('fwf_backup_schedule_state')['next']])!==false,'activation rearms saved schedule');
  $policy=S::policy();$policy['owner']=0;update_option('fwf_backup_schedule',$policy,false);$state=get_option('fwf_backup_schedule_state');$state['next']=time()-60;update_option('fwf_backup_schedule_state',$state,false);do_action(S::HOOK,$policy['generation'],$state['next']);$ok(S::runs()[0]['status']==='failed' && get_option('fwf_backup_schedule_state')['blocked']!=='' && count($must(B::jobs()))===1,'revoked owner permission blocks scheduling without new archive');
  wp_set_current_user(0);$ok(is_wp_error(S::save(['enabled'=>false,'frequency'=>'daily','weekday'=>'1','time'=>'02:00','components'=>['settings']],S::revision())),'schedule save enforces administrator capability');wp_set_current_user(get_user_by('login','fwf-admin')->ID);
  $must(S::save(['enabled'=>false,'frequency'=>'daily','weekday'=>'1','time'=>'02:00','components'=>['settings']],S::revision()));$ok(!S::policy()['enabled'] && count(B::listing())===1,'disable schedule preserves completed archive');
  echo "Schedule execution checks passed: $count. Real local cron, same archive engine.\n";
 }elseif($mode==='die-slot'){
  $policy=S::policy();$must(S::save(['enabled'=>true,'frequency'=>'daily','weekday'=>'1','time'=>'02:00','components'=>['settings']],S::revision()));$policy=S::policy();$state=get_option('fwf_backup_schedule_state');$state['next']=time()-60;update_option('fwf_backup_schedule_state',$state,false);wp_unschedule_hook(S::HOOK);add_action('updated_option',static function($name,$old,$value){if($name==='fwf_backup_schedule_state' && isset($value['pending']))exit(13);},10,3);do_action(S::HOOK,$policy['generation'],$state['next']);throw new RuntimeException('Termination did not happen.');
 }elseif($mode==='assert-death'){
  $run=S::runs()[0];$state=get_option('fwf_backup_schedule_state');$jobs=$must(B::jobs());$before=count($jobs);$ok($run['status']==='starting' && $run['job']==='' && $state['claimed']===$run['due'],'terminated scheduler retains explicit unknown execution record');
  do_action(S::HOOK,S::policy()['generation'],$run['due']);$ok(count($must(B::jobs()))===$before && S::runs()[0]['status']==='starting','unknown slot is not blindly replayed');$ok(wp_next_scheduled(S::HOOK,[S::policy()['generation'],$state['next']])!==false,'next bootstrap rearms future event after process death');
  $must(S::save(['enabled'=>false,'frequency'=>'daily','weekday'=>'1','time'=>'02:00','components'=>['settings']],S::revision()));$ok(count(B::listing())===1 && S::runs()[0]['status']==='starting','disable preserves ambiguous run and prior archive');
  echo "Schedule process-death checks passed: $count. Separate Docker process.\n";
 }elseif($mode==='cleanup'){
  foreach($must(B::jobs()) as $id=>$job){$must(B::forget($id));$path=FWF_BACKUP_DIR.'/'.$id.'.zip';if(is_file($path))unlink($path);delete_option('fwf_backup_note_'.$id);delete_option('fwf_backup_protected_'.$id);}
  foreach($fixture['saved'] as $name=>$row){$wpdb->delete($wpdb->options,['option_name'=>$name]);if($row && $wpdb->insert($wpdb->options,$row)===false)throw new RuntimeException('Restore option failed.');}delete_option($key);wp_cache_flush();
  foreach(['.backup.lock','.backup_schedule.lock','.recovery-key'] as $file)if(is_file(FWF_BACKUP_DIR.'/'.$file))unlink(FWF_BACKUP_DIR.'/'.$file);if(!rmdir(FWF_BACKUP_DIR))throw new RuntimeException('Schedule fixture cleanup failed.');
 }else throw new RuntimeException('Unknown mode.');
}
