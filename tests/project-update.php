<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Updates\ProjectManager as P;
use FalconWF\Packages\Verifier as V;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
putenv('FWF_PROJECT_GITHUB_TOKEN=fixture-project-token');
require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/theme.php';
$id='falcon-project-fixture';$root=get_theme_root().'/'.$id;$mode=$args[0]??'';$owner=$args[1]??'';$snapshotKey='fwf_project_update_test';
if(!preg_match('/^[a-f0-9-]{36}$/D',$owner)){throw new RuntimeException('Fixture owner required.');}
$snapshot=get_option($snapshotKey,false);
if($mode==='cleanup'){
 if(!$snapshot){echo "No project snapshot.\n";return;}
 if(($snapshot['owner']??'')!==$owner){throw new RuntimeException('Snapshot owner mismatch.');}
 clearstatcache(true,$root);$problem='';if(($args[2]??'')==='verify' && !is_dir($root)){$problem='Final project missing after shutdown.';}
 if(($args[2]??'')==='verify' && get_stylesheet()!==$snapshot['settings']['stylesheet']){$problem='Active theme changed after request shutdown.';}
 if(is_dir($root)){
  $header=get_file_data($root.'/style.css',['owner'=>'FWF Fixture Owner','version'=>'Version']);
  if($header['owner']!==$owner){throw new RuntimeException('Fixture file owner mismatch; preserve for review.');}
  if(($args[2]??'')==='verify' && $header['version']!=='0.1.2-alpha.1'){$problem='Final project version after shutdown differs.';}
  $deleted=delete_theme($id);clearstatcache(true,$root);if(is_wp_error($deleted) || is_dir($root)){throw new RuntimeException('Project cleanup failed.');}
 }
 foreach($snapshot['settings'] as $key=>$value){$value===null?delete_option($key):update_option($key,$value);}
 require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($snapshot['editor']);wp_clean_themes_cache();delete_option($snapshotKey);
 if($problem){throw new RuntimeException($problem);}echo "Project fixture cleanup verified after request shutdown.\n";return;
}
if($mode==='run' && (is_dir($root) || $snapshot)){throw new RuntimeException('Prior fixture requires explicit recovery.');}
if(in_array($mode,['fail','retry'],true) && (!$snapshot || $snapshot['owner']!==$owner || wp_get_theme($id)->get('Version')!=='0.1.1')){throw new RuntimeException('Recovery after failed request did not preserve prior version.');}
$settings=[];foreach(['fwf_project_connection','fwf_project_candidate','stylesheet','template','current_theme','theme_switched','theme_mods_'.$id] as $key){$settings[$key]=get_option($key,null);}
$editor=$mode==='run'?wp_insert_user(['user_login'=>'fwf-project-editor-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(32,true),'role'=>'editor']):$snapshot['editor'];if(is_wp_error($editor)){throw new RuntimeException('Editor fixture failed.');}
if($mode==='run' && !add_option($snapshotKey,['owner'=>$owner,'settings'=>$settings,'editor'=>$editor],'',false)){throw new RuntimeException('Snapshot collision.');}
$zipPath=wp_tempnam('fwf-project-fixture.zip');$count=0;$ok=static function($x,$label) use (&$count){if(!$x){throw new RuntimeException('FAIL: '.$label);}$count++;echo "PASS: $label\n";};
$zip=static function($version,$parent='falcon-theme',$extra=null) use($zipPath,$id,$owner){$z=new ZipArchive();$z->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);$z->addFromString($id.'/style.css',"/*\nTheme Name: Local Project Fixture\nFWF Fixture Owner: $owner\nVersion: $version\nTemplate: $parent\n*/\n");$z->addFromString($id.'/index.php',"<?php get_header(); echo '<main>Fixture</main>'; get_footer();");if($extra){$z->addFromString($extra,'fixture');}$z->close();};
$zip('0.1.0');
$connection=['repo'=>'fixture/client-design','project_id'=>'project-fixture','theme_id'=>$id,'tag'=>''];
$package=['id'=>$id,'type'=>'theme','parent'=>'falcon-theme','version'=>'0.1.0','artifact'=>$id.'-0.1.0.zip','sha256'=>hash_file('sha256',$zipPath),'min_wp'=>'6.7','min_php'=>'8.3','compatibility'=>['falcon-theme'=>['min'=>'0.1.0-alpha.4','max_exclusive'=>'0.2.0'],'falcon-wf'=>['min'=>'0.1.0-alpha.4','max_exclusive'=>'0.2.0']]];
$manifest=['schema'=>1,'project_id'=>'project-fixture','version'=>'0.1.0','status'=>'stable','dirty'=>false,'source_commit'=>str_repeat('b',40),'packages'=>[$package]];
$redirect=false;$signed=false;$corrupt=false;$duplicate=false;$prerelease=false;$seenAuth=[];
$mock=static function($pre,$args,$url) use (&$manifest,$zipPath,&$redirect,&$signed,&$corrupt,&$duplicate,&$prerelease,&$seenAuth){
 if(str_starts_with($url,'https://api.github.com/repos/fixture/client-design/')){if(!isset($args['headers']['Authorization'])){return ['headers'=>[],'response'=>['code'=>404,'message'=>'private fixture'],'body'=>''];}$seenAuth[]=$args['headers']['Authorization'];}
 $body='';$code=200;$headers=[];
 if(str_contains($url,'/repos/fixture/client-design/releases/latest') || str_contains($url,'/repos/fixture/client-design/releases/tags/')){$assets=[['name'=>'project-manifest.json','id'=>51],['name'=>$manifest['packages'][0]['artifact'],'id'=>52]];if($duplicate){$assets[]=$assets[0];}$body=wp_json_encode(['draft'=>false,'prerelease'=>$prerelease,'tag_name'=>'v'.$manifest['version'],'assets'=>$assets]);}
 elseif(str_ends_with($url,'/assets/51')){$body=wp_json_encode($manifest);}
 elseif(str_ends_with($url,'/assets/52')){if($redirect || $signed){$code=302;$headers=['location'=>$signed?'https://release-assets.githubusercontent.com/fixture.zip?signature=fixture':'https://attacker.invalid/zip'];}else{$body=$corrupt?'corrupt':file_get_contents($zipPath);}}
 elseif(str_starts_with($url,'https://release-assets.githubusercontent.com/')){if(isset($args['headers']['Authorization'])){throw new RuntimeException('Credential leaked to signed host.');}$body=file_get_contents($zipPath);}
 else{return new WP_Error('fixture_network','External transport disabled in local project test.');}
 if(!empty($args['stream'])){file_put_contents($args['filename'],$body);$body='';}
 return ['headers'=>$headers,'response'=>['code'=>$code,'message'=>'fixture'],'body'=>$body];
};
add_filter('pre_http_request',$mock,10,3);$manager=new P();
try {
 if($mode==='fail'){
  if(P::save($connection)!==true){throw new RuntimeException('Failure request connection setup failed.');}
 $zip('0.1.2');$manifest['version']='0.1.2';$manifest['packages'][0]=[...$package,'version'=>'0.1.2','artifact'=>$id.'-0.1.2.zip','sha256'=>hash_file('sha256',$zipPath)];$manager->check();
 $original=file_get_contents($root.'/style.css');$deny=static fn($value,$extra)=>($extra['theme']??'')==='falcon-project-fixture'?new WP_Error('fixture_failure','Local deliberate failure'):$value;add_filter('upgrader_pre_install',$deny,5,2);
 try{$result=$manager->apply(true);}finally{remove_filter('upgrader_pre_install',$deny,5);}
 $ok(is_wp_error($result) && $result->get_error_code()==='FWF_FILESYSTEM' && file_get_contents($root.'/style.css')===$original,'pre-install failure preserves original project files');
 echo "Project failure checks passed: $count.\n";return;
 }
 if($mode==='retry'){
  $zip('0.1.2-alpha.1');$manifest['version']='0.1.2-alpha.1';$manifest['status']='development';$manifest['packages'][0]=[...$package,'version'=>'0.1.2-alpha.1','artifact'=>$id.'-0.1.2-alpha.1.zip','sha256'=>hash_file('sha256',$zipPath)];$prerelease=true;$connection['tag']='v0.1.2-alpha.1';
  $ok(P::save($connection)===true && !is_wp_error($manager->check()) && $manager->apply(true)===true,'actual prerelease retry succeeds after separate failed request');echo "Project retry checks passed: $count.\n";return;
 }
 $ok(P::save([...$connection,'repo'=>'https://github.com/fixture/client-design.git'])===true && P::connection()===$connection,'project GitHub URL normalizes to shared repo identity');
 $ok(P::save($connection)===true,'project connection stored independently');
 $denyStore=static fn($new,$old)=>$old;add_filter('pre_update_option_fwf_project_connection',$denyStore,10,2);
 try{$failed=P::save([...$connection,'project_id'=>'other-project']);}finally{remove_filter('pre_update_option_fwf_project_connection',$denyStore,10);}
 $ok(is_wp_error($failed) && P::connection()===$connection,'failed connection persistence is reported and preserves state');
 $ok(is_wp_error(P::save([...$connection,'repo'=>'https://bad.invalid/token'])),'project repo URL rejected');
 $ok(is_wp_error(P::save([...$connection,'theme_id'=>'falcon-theme'])),'project cannot target core theme');
 $ok(is_wp_error(P::save([...$connection,'project_id'=>'../unsafe'])),'invalid project slug refused');
 $ok(!is_wp_error(P::validate($manifest,$connection)),'valid child manifest/ranges accepted');
 $mixed=$manifest;$mixed['packages'][0]['version']='0.1.5';$mixed['packages'][0]['artifact']=$id.'-0.1.5.zip';$ok(!is_wp_error(P::validate($mixed,$connection)),'project release and component versions remain independent');
 foreach (['project_id'=>'wrong-project','dirty'=>true,'source_commit'=>null] as $key=>$value){$bad=$manifest;$bad[$key]=$value;$ok(is_wp_error(P::validate($bad,$connection)),'project identity/provenance validation');}
 foreach (['parent'=>'other-parent','type'=>'plugin','id'=>'falcon-theme','sha256'=>[],'min_php'=>'99.0','compatibility'=>[]] as $key=>$value){$bad=$manifest;$bad['packages'][0][$key]=$value;$ok(is_wp_error(P::validate($bad,$connection)),'invalid project package/range rejected');}
 $bad=$manifest;$bad['packages'][0]['compatibility']['falcon-theme']['min']='99.0.0';$ok(is_wp_error(P::validate($bad,$connection)),'incompatible parent refused before file write');
 $bad=$manifest;$bad['packages'][0]['compatibility']['falcon-wf']['min']='0.1.1';$ok(is_wp_error(P::validate($bad,$connection)),'incompatible FP refused');
 $bad=$manifest;$bad['packages'][]=$package;$ok(is_wp_error(P::validate($bad,$connection)),'multiple/duplicate packages refused');
 $ok(is_wp_error($manager->apply(true)) && !is_dir($root),'unreviewed install refused');
 $ok(!is_wp_error($manager->check()) && array_unique($seenAuth)===['Bearer fixture-project-token'],'project uses isolated credential and manifest asset');
 update_option('fwf_project_candidate',['fixture'=>'previous'],false);add_filter('pre_update_option_fwf_project_candidate',$denyStore,10,2);
 try{$failed=$manager->check();}finally{remove_filter('pre_update_option_fwf_project_candidate',$denyStore,10);}
 $ok(is_wp_error($failed) && get_option('fwf_project_candidate',null)===null,'failed candidate persistence cannot approve a package');$manager->check();
 $ok(is_wp_error($manager->apply(false)) && !is_dir($root),'project install requires backup confirmation');
 $manifest['source_commit']=str_repeat('c',40);$ok(is_wp_error($manager->apply(true)) && !is_dir($root),'changed release requires fresh review');$manager->check();
 $corrupt=true;$ok(is_wp_error($manager->apply(true)) && !is_dir($root),'corrupt project ZIP refused');$corrupt=false;
 $redirect=true;$ok(is_wp_error($manager->apply(true)) && !is_dir($root),'foreign project redirect refused');$redirect=false;
 $zip('0.1.0','other-parent');$manifest['packages'][0]['sha256']=hash_file('sha256',$zipPath);$manager->check();$ok(is_wp_error($manager->apply(true)) && !is_dir($root),'ZIP parent differs from manifest refused');
 $zip('0.1.0','falcon-theme',$id.'/../escape.php');$manifest['packages'][0]['sha256']=hash_file('sha256',$zipPath);$manager->check();$ok(is_wp_error($manager->apply(true)) && !is_dir($root),'project traversal refused');
 $zip('0.1.0','falcon-theme',$id.'//index.php');$manifest['packages'][0]['sha256']=hash_file('sha256',$zipPath);$ok(is_wp_error(V::remote($zipPath,$manifest['packages'][0])),'canonical path aliases refused by shared verifier');
 $zip('0.1.0');$manifest['packages'][0]['sha256']=hash_file('sha256',$zipPath);$manager->check();$beforeTheme=get_stylesheet();$beforeReading=[get_option('show_on_front'),get_option('page_on_front')];
 $signed=true;$result=$manager->apply(true);$signed=false;$ok($result===true,'actual WordPress fresh child theme install via signed redirect without credential forwarding');
 $ok(get_stylesheet()===$beforeTheme && [get_option('show_on_front'),get_option('page_on_front')]===$beforeReading && wp_get_theme($id)->get('Version')==='0.1.0','fresh install preserves active theme and homepage');
 $manager->check();$ok(is_wp_error($manager->apply(true)),'same-version project refused');
 $zip('0.0.9');$manifest['version']='0.0.9';$manifest['packages'][0]=[...$package,'version'=>'0.0.9','artifact'=>$id.'-0.0.9.zip','sha256'=>hash_file('sha256',$zipPath)];$manager->check();$ok(is_wp_error($manager->apply(true)) && wp_get_theme($id)->get('Version')==='0.1.0','project downgrade refused');
 $zip('0.1.1');$manifest['version']='0.1.1';$manifest['packages'][0]=[...$package,'version'=>'0.1.1','artifact'=>$id.'-0.1.1.zip','sha256'=>hash_file('sha256',$zipPath)];$manager->check();
 // Successful update and deliberate failure are separate versions; recovery finishes at request shutdown.
 switch_theme($id);$result=$manager->apply(true);$ok($result===true && wp_get_theme($id)->get('Version')==='0.1.1' && get_stylesheet()===$id,'actual child update preserves active selection');
 $duplicate=true;$ok(is_wp_error($manager->check()) && get_option('fwf_project_candidate',null)===null,'failed project check invalidates candidate');$duplicate=false;
 wp_set_current_user($editor);$ok(is_wp_error($manager->check()) && is_wp_error($manager->apply(true)) && is_wp_error(P::save($connection)),'editor denied project save/check/apply');
 wp_set_current_user(get_user_by('login','fwf-admin')->ID);
 $prerelease=true;$manifest['version']='0.1.2-alpha.1';$manifest['status']='development';$manifest['packages'][0]=[...$package,'version'=>'0.1.2-alpha.1','artifact'=>$id.'-0.1.2-alpha.1.zip'];$connection['tag']='v0.1.2-alpha.1';
 $ok(P::save($connection)===true && !is_wp_error($manager->check()),'pinned local project prerelease discovery');
 echo "Project update checks passed: $count. Mock GitHub, actual WordPress file installation/update.\n";
 } finally {
 remove_filter('pre_http_request',$mock,10);
 foreach($settings as $key=>$value){$value===null?delete_option($key):update_option($key,$value);}
 wp_delete_file($zipPath);
 // Files/user/snapshot cleanup runs in a subsequent PHP request after WP shutdown recovery.
 }
