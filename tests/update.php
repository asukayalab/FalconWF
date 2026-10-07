<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Updates\UpdateManager;
use FalconWF\Updates\GitHubClient;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if(!defined('FWF_GITHUB_TOKEN')){define('FWF_GITHUB_TOKEN','fixture-not-a-real-token');}
require_once ABSPATH.'wp-admin/includes/file.php';
$oldRepo=get_option('fwf_repo','');$root=get_theme_root().'/falcon-theme';$original=file_get_contents($root.'/style.css');$theme=get_stylesheet();
$zipPath=wp_tempnam('fwf-update-fixture.zip');$zip=new ZipArchive();$zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE);
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){$relative=substr($file->getPathname(),strlen($root)+1);$bytes=file_get_contents($file->getPathname());if($relative==='style.css'){$bytes=preg_replace('/^Version:.*$/m','Version: 0.1.1',$bytes);}$zip->addFromString('falcon-theme/'.$relative,$bytes);}$zip->close();
$package=['id'=>'falcon-theme','type'=>'theme','version'=>'0.1.1','artifact'=>'falcon-theme-0.1.1.zip','sha256'=>hash_file('sha256',$zipPath),'min_wp'=>'6.7','min_php'=>'8.3'];
$manifest=['schema'=>1,'status'=>'stable','dirty'=>false,'source_commit'=>str_repeat('a',40),'packages'=>[$package]];
$corrupt=false;$redirect=false;
$mock=static function($pre,$args,$url) use ($zipPath,&$manifest,&$corrupt,&$redirect) {
 $body='';$code=200;$headers=[];
 if(str_ends_with($url,'/releases/latest')){$body=wp_json_encode(['draft'=>false,'prerelease'=>false,'tag_name'=>'fixture-0.1.1','assets'=>[['name'=>'release-manifest.json','id'=>1],['name'=>'falcon-theme-0.1.1.zip','id'=>2]]]);}
 elseif(str_ends_with($url,'/assets/1')){$body=wp_json_encode($manifest);}
 elseif(str_ends_with($url,'/assets/2')){if($redirect){$code=302;$headers=['location'=>'https://attacker.invalid/package.zip'];}else{$body=$corrupt?'corrupt':file_get_contents($zipPath);}}
 else{return new WP_Error('fixture_network','External network disabled in update fixture.');}
 if(!empty($args['stream'])){file_put_contents($args['filename'],$body);$body='';}
 return ['headers'=>$headers,'response'=>['code'=>$code,'message'=>'fixture'],'body'=>$body];
};
add_filter('pre_http_request',$mock,10,3);
try {
 update_option('fwf_repo','fixture/falcon-wf',false);
 $updater=new UpdateManager();
 if(!is_wp_error($updater->update('falcon-theme',false))){throw new RuntimeException('Backup confirmation bypassed.');}
 $bad=$manifest;$bad['packages'][0]['type']='plugin';if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Wrong type accepted.');}
 $bad=$manifest;$bad['packages'][0]['min_php']='99.0';if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Incompatible runtime accepted.');}
 $bad=$manifest;$bad['status']='development';if(!is_wp_error(UpdateManager::validate($bad))){throw new RuntimeException('Development release accepted as stable.');}
 if(!is_wp_error((new GitHubClient('https://attacker.invalid'))->release())){throw new RuntimeException('Repo URL injection accepted.');}
 $redirect=true;$denied=$updater->update('falcon-theme',true);if(!is_wp_error($denied) || file_get_contents($root.'/style.css')!==$original){throw new RuntimeException('Foreign redirect altered current theme.');}
 $redirect=false;$corrupt=true;$denied=$updater->update('falcon-theme',true);if(!is_wp_error($denied) || file_get_contents($root.'/style.css')!==$original){throw new RuntimeException('Corrupt package altered current theme.');}
 $corrupt=false;$checked=$updater->check();if(is_wp_error($checked)){throw new RuntimeException($checked->get_error_message());}
 $result=$updater->update('falcon-theme',true);if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());}
 wp_clean_themes_cache();if(wp_get_theme('falcon-theme')->get('Version')!=='0.1.1' || get_stylesheet()!==$theme){throw new RuntimeException('Valid update lost active theme or version.');}
 if(!is_wp_error($updater->update('falcon-theme',true))){throw new RuntimeException('Same-version update accepted.');}
 echo 'PASS: updater manifest/runtime/backup checks, foreign redirect and corruption refusal, actual WP theme upgrader and no downgrade (mock GitHub only)' . "\n";
} finally {
 remove_filter('pre_http_request',$mock,10);file_put_contents($root.'/style.css',$original);wp_clean_themes_cache();update_option('fwf_repo',$oldRepo,false);delete_option('fwf_release_candidate');wp_delete_file($zipPath);
}
