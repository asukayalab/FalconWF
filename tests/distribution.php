<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Updates\UpdateManager as U;
use FalconWF\Updates\ProjectManager as P;
use FalconWF\Updates\Monitor as M;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$keys=['fwf_repo','fwf_update_channel','fwf_update_tag','fwf_release_candidate','fwf_project_connection','fwf_project_candidate','fwf_update_observations'];$saved=[];
foreach($keys as $key){$saved[$key]=get_option($key,null);}
$savedCron=get_option('cron');$count=0;$ok=static function($value,$message)use(&$count){if(!$value){throw new RuntimeException($message);}++$count;};
$version='0.1.0-alpha.90';$fail=false;$downloads=0;$calls=[];
$corePackage=['id'=>'falcon-wf','type'=>'plugin','version'=>$version,'artifact'=>'falcon-wf-'.$version.'.zip','sha256'=>str_repeat('a',64),'min_wp'=>'6.7','min_php'=>'8.3'];
$projectPackage=['id'=>'falcon-discovery-fixture','type'=>'theme','parent'=>'falcon-theme','version'=>$version,'artifact'=>'falcon-discovery-fixture-'.$version.'.zip','sha256'=>str_repeat('b',64),'min_wp'=>'6.7','min_php'=>'8.3','compatibility'=>['falcon-theme'=>['min'=>'0.1.0-alpha.4','max_exclusive'=>'0.2.0']]];
$mock=static function($pre,$args,$url)use(&$version,&$fail,&$downloads,&$calls,$corePackage,$projectPackage){
 $calls[]=$url;if($fail){return new WP_Error('fixture','Network unavailable');}
 $project=str_contains($url,'/fixture/project/');$body=[];
 if(str_contains($url,'/releases?')){$body=[['draft'=>false,'prerelease'=>str_contains($version,'-'),'tag_name'=>'v'.$version],['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.1.0-alpha.9']];}
 elseif(str_contains($url,'/releases/tags/') || str_ends_with($url,'/releases/latest')){$body=['draft'=>false,'prerelease'=>str_contains($version,'-'),'tag_name'=>'v'.$version,'assets'=>[['name'=>$project?'project-manifest.json':'release-manifest.json','id'=>1],['name'=>($project?'falcon-discovery-fixture':'falcon-wf').'-'.$version.'.zip','id'=>2]]];}
 elseif(str_ends_with($url,'/assets/1')){$p=$project?$projectPackage:$corePackage;$p['version']=$version;$p['artifact']=$p['id'].'-'.$version.'.zip';$body=['schema'=>1,'version'=>$version,'status'=>str_contains($version,'-')?'development':'stable','dirty'=>false,'source_commit'=>str_repeat('c',40),'packages'=>[$p]];if($project){$body['project_id']='discovery-project';}}
 else{$downloads++;return new WP_Error('fixture','Unexpected ZIP or external request');}
 return ['headers'=>[],'response'=>['code'=>200],'body'=>wp_json_encode($body)];
};
add_filter('pre_http_request',$mock,10,3);
try{
 $ok(is_wp_error(P::save(['repo'=>'fixture/project','project_id'=>'0','theme_id'=>'','tag'=>'','channel'=>'testing'])) && is_wp_error(P::save(['repo'=>'fixture/project','project_id'=>[],'theme_id'=>'','tag'=>'','channel'=>'testing'])),'invalid explicit identity cannot become blank discovery');
 $ok(U::saveConnection('fixture/core','testing')===true,'core testing saved');
 $ok(P::save(['repo'=>'fixture/project','project_id'=>'','theme_id'=>'','tag'=>'','channel'=>'testing'])===true,'project identity discovered from manifest');
 $connection=P::connection();$ok($connection['project_id']==='discovery-project' && $connection['theme_id']==='falcon-discovery-fixture','resolved identity persisted');
 $ok(is_wp_error(P::save([...$connection,'theme_id'=>'','project_id'=>'wrong-project'])) && P::connection()===$connection,'discovery cannot replace explicit conflicting identity');
 $u=new U();$p=new P();$ok(!is_wp_error($u->check()) && !is_wp_error($p->check()),'human core/project check');
 $before=[get_option('fwf_release_candidate'),get_option('fwf_project_candidate')];$version='0.1.0-alpha.91';wp_set_current_user(0);do_action('fwf_update_check');
 $ok(get_option('fwf_release_candidate')===$before[0] && get_option('fwf_project_candidate')===$before[1],'cron does not replace human review candidates');
 $status=M::status();$ok(count($status)===2 && $status['core']['packages'][0]['version']===$version && $status['project']['packages'][0]['version']===$version,'next releases discovered without configuration changes');
 $ok($downloads===0,'background discovery never downloads ZIP');
 ob_start();M::notice();$html=ob_get_clean();$ok($html==='','unauthorized user sees no update notice');
 wp_set_current_user(get_user_by('login','fwf-admin')->ID);ob_start();M::notice();$html=ob_get_clean();$ok(str_contains($html,'Pembaruan Falcon tersedia') && str_contains($html,$version),'admin sees available core update');
 $ok(is_wp_error($u->update('falcon-wf',true)) && is_wp_error($p->apply(true)) && $downloads===0,'changed releases rejected before ZIP/write despite cron results');
 $fail=true;M::run();$ok(M::status()['core']['error']!=='' && M::status()['core']['packages']===[],'failed check reports error instead of stale success');$fail=false;
 $ok(U::saveConnection('fixture/core','manual','v0.1.0-alpha.91')===true && U::selection()==='v0.1.0-alpha.91','core manual pin');
 $ok(P::save([...$connection,'channel'=>'manual','tag'=>'v0.1.0-alpha.91'])===true && !is_wp_error($p->check()),'project manual pin');
 $ok(!is_wp_error($u->check()),'core pinned prerelease discovery');
 $version='0.1.1';$ok(U::saveConnection('fixture/core','testing')===true && !is_wp_error($u->check()),'core including-prerelease channel accepts a winning stable release');
 $ok(P::save([...$connection,'channel'=>'testing','tag'=>''])===true && !is_wp_error($p->check()),'project including-prerelease channel accepts a winning stable release');
 $ok(U::saveConnection('fixture/core','manual','v0.1.1')===true && !is_wp_error($u->check()),'core stable manual tag supported');
 $ok(P::save([...$connection,'channel'=>'manual','tag'=>'v0.1.1'])===true && !is_wp_error($p->check()),'project stable manual tag supported');
 $ok(is_wp_error(U::saveConnection('fixture/core','manual','*')) && is_wp_error(P::save([...$connection,'channel'=>'manual','tag'=>'*'])),'manual tags cannot use automatic wildcard');
 $ok(is_wp_error(U::saveConnection('fixture/core','manual','')) && is_wp_error(U::saveConnection('fixture/core','manual','../bad')),'invalid manual core tags denied');
 M::run();update_option('fwf_repo','fixture/different',false);$ok(!isset(M::status()['core']),'source change suppresses obsolete notifications');
 $ok(wp_next_scheduled('fwf_update_check')!==false,'twice-daily check scheduled');
 $events=_get_cron_array();$found=0;foreach($events as $hooks){$found+=isset($hooks['fwf_update_check'])?count($hooks['fwf_update_check']):0;}$ok($found===1,'no duplicate cron event');
 $observed=get_option('fwf_update_observations');\FalconWF\Lifecycle::deactivate();$ok(!wp_next_scheduled('fwf_update_check') && get_option('fwf_update_observations')===$observed,'deactivation unschedules metadata checks and preserves data');M::register();$ok(wp_next_scheduled('fwf_update_check')!==false,'reactivation/bootstrap rearms check');
 echo "Distribution checks passed: $count. Mock transport; actual WordPress cron/permissions/candidates.\n";
}finally{
 remove_filter('pre_http_request',$mock,10);wp_set_current_user(get_user_by('login','fwf-admin')->ID);
 update_option('cron',$savedCron);
 foreach($saved as $key=>$value){$value===null?delete_option($key):update_option($key,$value,false);}
}
