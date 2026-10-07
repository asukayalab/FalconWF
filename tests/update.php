<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Updates\UpdateManager;
use FalconWF\Updates\GitHubClient;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if(!defined('FWF_GITHUB_TOKEN')){define('FWF_GITHUB_TOKEN','fixture-not-a-real-token');}
require_once ABSPATH.'wp-admin/includes/file.php';
$oldRepo=get_option('fwf_repo','');$oldTag=get_option('fwf_update_tag','');$pluginRoot=WP_PLUGIN_DIR.'/falcon-wf';$pluginOriginal=[];
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot,FilesystemIterator::SKIP_DOTS)) as $file){$pluginOriginal[substr($file->getPathname(),strlen($pluginRoot)+1)]=file_get_contents($file->getPathname());}
$activePlugins=get_option('active_plugins');$pluginZip=wp_tempnam('fwf-plugin-update.zip');$zip=new ZipArchive();$zip->open($pluginZip,ZipArchive::CREATE|ZipArchive::OVERWRITE);
foreach($pluginOriginal as $relative=>$bytes){if($relative==='falcon-wf.php'){$bytes=preg_replace('/^[ *]*Version:.*$/m',' * Version: 0.1.1',$bytes);}$zip->addFromString('falcon-wf/'.$relative,$bytes);}$zip->close();
$root=get_theme_root().'/falcon-theme';$original=file_get_contents($root.'/style.css');$theme=get_stylesheet();
$zipPath=wp_tempnam('fwf-update-fixture.zip');$zip=new ZipArchive();$zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){$relative=substr($file->getPathname(),strlen($root)+1);$bytes=file_get_contents($file->getPathname());if($relative==='style.css'){$bytes=preg_replace('/^Version:.*$/m','Version: 0.1.1',$bytes);}$zip->addFromString('falcon-theme/'.$relative,$bytes);}$zip->close();
$package=['id'=>'falcon-theme','type'=>'theme','version'=>'0.1.1','artifact'=>'falcon-theme-0.1.1.zip','sha256'=>hash_file('sha256',$zipPath),'min_wp'=>'6.7','min_php'=>'8.3'];
$manifest=['schema'=>1,'status'=>'stable','dirty'=>false,'source_commit'=>str_repeat('a',40),'packages'=>[$package]];
$corrupt=false;$redirect=false;$prerelease=false;$duplicate=false;
$mock=static function($pre,$args,$url) use ($zipPath,$pluginZip,&$manifest,&$corrupt,&$redirect,&$prerelease,&$duplicate) {
 $body='';$code=200;$headers=[];
 if(str_ends_with($url,'/releases/latest') || str_contains($url,'/releases/tags/')){$assets=[['name'=>'release-manifest.json','id'=>1],['name'=>$manifest['packages'][0]['artifact'],'id'=>2]];if($duplicate){$assets[]=$assets[0];}$body=wp_json_encode(['draft'=>false,'prerelease'=>$prerelease,'tag_name'=>$prerelease?'v0.1.2-alpha.1':'v0.1.1','assets'=>$assets]);}
 elseif(str_ends_with($url,'/assets/1')){$body=wp_json_encode($manifest);}
 elseif(str_ends_with($url,'/assets/2')){if($redirect){$code=302;$headers=['location'=>'https://attacker.invalid/package.zip'];}else{$body=$corrupt?'corrupt':file_get_contents($manifest['packages'][0]['id']==='falcon-wf'?$pluginZip:$zipPath);}}
 else{return new WP_Error('fixture_network','External network disabled in update fixture.');}
 if(!empty($args['stream'])){file_put_contents($args['filename'],$body);$body='';}
 return ['headers'=>$headers,'response'=>['code'=>$code,'message'=>'fixture'],'body'=>$body];
};
add_filter('pre_http_request',$mock,10,3);
try {
 update_option('fwf_repo','fixture/falcon-wf',false);update_option('fwf_update_tag','',false);
 $updater=new UpdateManager();
 if(!is_wp_error(UpdateManager::validateSelection('../release'))){throw new RuntimeException('Unsafe prerelease tag accepted.');}
 $pre=$manifest;$pre['status']='development';$pre['version']='0.1.2-alpha.1';$pre['packages'][0]['version']='0.1.2-alpha.1';$pre['packages'][0]['artifact']='falcon-theme-0.1.2-alpha.1.zip';
 if(is_wp_error(UpdateManager::validate($pre,'v0.1.2-alpha.1'))){throw new RuntimeException('Explicit local prerelease denied.');}
 if(!is_wp_error(UpdateManager::validate($pre)) || !is_wp_error(UpdateManager::validate($pre,'v0.1.2-alpha.2'))){throw new RuntimeException('Prerelease channel/version mismatch accepted.');}
 $mixed=$pre;$mixed['packages'][0]=$package;if(is_wp_error(UpdateManager::validate($mixed,'v0.1.2-alpha.1'))){throw new RuntimeException('Independent component version refused.');}
 $bad=$manifest;$bad['packages'][0]['sha256']=[];if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Malformed hash accepted.');}
 $bad=$manifest;$bad['packages'][0]['min_php']=[];if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Malformed runtime accepted.');}
 $bad=$pre;$bad['dirty']=true;if(!is_wp_error(UpdateManager::validate($bad,'v0.1.2-alpha.1'))){throw new RuntimeException('Dirty prerelease accepted.');}
 $bad=$manifest;unset($bad['dirty']);if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Missing provenance accepted.');}
 $prerelease=true;$stableManifest=$manifest;$manifest=$pre;update_option('fwf_update_tag','v0.1.2-alpha.1',false);
 if(is_wp_error($updater->check())){throw new RuntimeException('Pinned prerelease discovery failed.');}
 update_option('fwf_update_tag','v0.1.2-alpha.2',false);if(!is_wp_error($updater->check()) || get_option('fwf_release_candidate',false)!==false){throw new RuntimeException('Tag mismatch or stale candidate accepted.');}
 update_option('fwf_update_tag','',false);if(!is_wp_error($updater->check())){throw new RuntimeException('Prerelease metadata accepted from stable endpoint.');}
 $prerelease=false;$manifest=$stableManifest;$duplicate=true;if(!is_wp_error($updater->check())){throw new RuntimeException('Duplicate release asset accepted.');}$duplicate=false;
 if(is_wp_error($updater->check())){throw new RuntimeException('Stable discovery failed.');}

 if(!is_wp_error($updater->update('falcon-theme',false))){throw new RuntimeException('Backup confirmation bypassed.');}
 $bad=$manifest;$bad['packages'][0]['type']='plugin';if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Wrong type accepted.');}
 $bad=$manifest;$bad['packages'][0]['min_php']='99.0';if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Incompatible runtime accepted.');}
 $bad=$manifest;$bad['status']='development';if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Development release accepted as stable.');}
 if(!is_wp_error((new GitHubClient('https://attacker.invalid'))->release())){throw new RuntimeException('Repo URL injection accepted.');}
 $redirect=true;$denied=$updater->update('falcon-theme',true);if(!is_wp_error($denied) || file_get_contents($root.'/style.css')!==$original){throw new RuntimeException('Foreign redirect altered current theme.');}
 $redirect=false;$corrupt=true;$denied=$updater->update('falcon-theme',true);if(!is_wp_error($denied) || file_get_contents($root.'/style.css')!==$original){throw new RuntimeException('Corrupt package altered current theme.');}
 $corrupt=false;$checked=$updater->check();if(is_wp_error($checked)){throw new RuntimeException($checked->get_error_message());}
 if(is_wp_error($updater->check())){throw new RuntimeException('Release discovery before theme apply failed.');}
 $result=$updater->update('falcon-theme',true);if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}
 wp_clean_themes_cache();if(wp_get_theme('falcon-theme')->get('Version')!=='0.1.1' || get_stylesheet()!==$theme){throw new RuntimeException('Valid update lost active theme or version.');}
 if(!is_wp_error($updater->update('falcon-theme',true))){throw new RuntimeException('Same-version update accepted.');}
 $zip=new ZipArchive();$zip->open($zipPath);$zip->addFromString('falcon-theme/style.css',preg_replace('/^Version:.*$/m','Version: 0.1.2-alpha.1',$original));$zip->close();
 $manifest=$pre;$manifest['packages'][0]['sha256']=hash_file('sha256',$zipPath);$prerelease=true;update_option('fwf_update_tag','v0.1.2-alpha.1',false);
 if(is_wp_error($updater->check())){throw new RuntimeException('Release discovery before theme apply failed.');}
 $result=$updater->update('falcon-theme',true);if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}
 wp_clean_themes_cache();if(wp_get_theme('falcon-theme')->get('Version')!=='0.1.2-alpha.1' || get_stylesheet()!==$theme){throw new RuntimeException('Prerelease upgrader lost theme/version.');}
 $manifest=$stableManifest;$prerelease=false;update_option('fwf_update_tag','',false);
 if(!is_wp_error($updater->update('falcon-theme',true))){throw new RuntimeException('Stable channel downgraded newer prerelease.');}
 $manifest['packages']=[['id'=>'falcon-wf','type'=>'plugin','version'=>'0.1.1','artifact'=>'falcon-wf-0.1.1.zip','sha256'=>hash_file('sha256',$pluginZip),'min_wp'=>'6.7','min_php'=>'8.3']];
 if(!is_wp_error($updater->update('falcon-wf',true))){throw new RuntimeException('Changed unreviewed component accepted.');}
 if(is_wp_error($updater->check())){throw new RuntimeException('Plugin discovery failed.');}
 $denyInstall=static function($result,$extra){return ($extra['plugin']??'')==='falcon-wf/falcon-wf.php'?new WP_Error('fixture_install_failure','Deliberate local pre-install failure.'):$result;};
 add_filter('upgrader_pre_install',$denyInstall,5,2);
 try{$failed=$updater->update('falcon-wf',true);}finally{remove_filter('upgrader_pre_install',$denyInstall,5);}
 if(!is_wp_error($failed) || file_get_contents($pluginRoot.'/falcon-wf.php')!==$pluginOriginal['falcon-wf.php'] || get_option('active_plugins')!==$activePlugins){throw new RuntimeException('Failed plugin upgrade altered original code/activation.');}
 $result=$updater->update('falcon-wf',true);if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}
 $installed=get_plugin_data($pluginRoot.'/falcon-wf.php');if($installed['Version']!=='0.1.1' || get_option('active_plugins')!==$activePlugins || get_stylesheet()!==$theme){throw new RuntimeException('Plugin self-update changed activation/theme or wrong version.');}
 if(!is_wp_error($updater->update('falcon-wf',true))){throw new RuntimeException('Plugin same-version update accepted.');}
 echo 'PASS: pinned staging prerelease/channel/provenance/tag/duplicate-asset checks; actual plugin self-update preserving activation; updater manifest/runtime/backup checks, foreign redirect and corruption refusal, actual WP theme upgrader and no downgrade (mock GitHub only)' . "\n";
} finally {
 remove_filter('pre_http_request',$mock,10);foreach($pluginOriginal as $relative=>$bytes){file_put_contents($pluginRoot.'/'.$relative,$bytes);}wp_clean_plugins_cache();wp_delete_file($pluginZip);update_option('fwf_update_tag',$oldTag,false);file_put_contents($root.'/style.css',$original);wp_clean_themes_cache();update_option('fwf_repo',$oldRepo,false);delete_option('fwf_release_candidate');wp_delete_file($zipPath);
}
