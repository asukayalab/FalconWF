<?php
// Opt-in live transport probe; does not install packages or change site options.
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Updates\GitHubClient;
use FalconWF\Updates\UpdateManager;
require_once ABSPATH.'wp-admin/includes/file.php';
$tag=$args[0]??'';
if($tag!=='*' && !preg_match('/^v\d+\.\d+\.\d+-alpha\.[1-9]\d*$/D',$tag)){throw new RuntimeException('Explicit published alpha tag required.');}
$requests=0;$guard=static function($pre,$args,$url) use (&$requests){
 if(str_starts_with($url,'https://api.github.com/') || str_starts_with($url,'https://release-assets.githubusercontent.com/')){
  if(isset($args['headers']['Authorization'])){throw new RuntimeException('Live public probe must remain anonymous.');}$requests++;
 }
 return $pre;
};
add_filter('pre_http_request',$guard,1,3);
try{
 $client=new GitHubClient('asukayalab/FalconWF');$release=$client->release($tag);
 if(is_wp_error($release)){throw new RuntimeException($release->get_error_message());}
 $packages=UpdateManager::validate($release['manifest'],$release['tag']);
 if(is_wp_error($packages)){throw new RuntimeException($packages->get_error_message());}
 foreach($packages as $package){
  $path=$client->asset($release['assets'][$package['artifact']]??0,true);
  if(is_wp_error($path)){throw new RuntimeException($path->get_error_message());}
  try{$verified=UpdateManager::verifyZip($path,$package);if(is_wp_error($verified)){throw new RuntimeException($verified->get_error_message());}}
  finally{wp_delete_file($path);}
  echo 'PASS: live anonymous '.$package['id'].' '.$package['version']." download/hash/ZIP verification; no installation.\n";
 }
 echo 'Discovered release: '.$release['tag'].".\n";
 echo 'Anonymous HTTP requests: '.$requests.".\n";
}finally{remove_filter('pre_http_request',$guard,1);}
