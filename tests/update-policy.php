<?php
// Isolated transport/policy checks; no live GitHub acceptance.
class WP_Error { public function __construct(public string $code,public string $message){} }
function is_wp_error($v):bool{return $v instanceof WP_Error;}
require '/var/www/html/wp-content/plugins/falcon-wf/src/Updates/UpdateManager.php';
use FalconWF\Updates\UpdateManager as U;
foreach(['local','staging','development','production'] as $environment){
 $GLOBALS['fixture_environment']=$environment;
 foreach(['','v1.0.0','v1.1.0-alpha.3'] as $tag){if(is_wp_error(U::validateSelection($tag))){throw new RuntimeException('Explicit release selection rejected: '.$environment);}}
 if(is_wp_error(U::validateSelection('*',true)) || !is_wp_error(U::validateSelection('*')) || !is_wp_error(U::validateSelection('../bad'))){throw new RuntimeException('Selection validation failed.');}
}
echo "PASS: all three release choices work in every environment; manual wildcard/invalid tags refused.\n";
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_safe_remote_get($url,$args){
 if(isset($args['headers']['Authorization'])){throw new RuntimeException('Public discovery sent credential');}
 $GLOBALS['discovery_calls'][]=$url;$mode=$GLOBALS['discovery_mode'];$body=[];
 if(str_contains($url,'/releases?')){
  if($mode==='network'){return new WP_Error('fixture','Network refused');}
  if($mode==='malformed'){$body=['error'=>'invalid'];}
  elseif($mode==='empty'){$body=[];}
  elseif($mode==='bound' || ($mode==='paged' && str_ends_with($url,'page=1'))){$body=array_fill(0,100,['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.2.0-alpha.9']);}
  else{$body=[['draft'=>false,'prerelease'=>false,'tag_name'=>'v0.1.1'],['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.2.0-alpha.9'],['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.2.0-alpha.10'],['draft'=>true,'prerelease'=>false,'tag_name'=>'v99.0.0'],['draft'=>false,'prerelease'=>false,'tag_name'=>'v0.1.0'],['draft'=>false,'prerelease'=>true,'tag_name'=>'invalid']];if($mode==='stablehigher'){$body[]=['draft'=>false,'prerelease'=>false,'tag_name'=>'v0.2.0'];}}
 }elseif(str_contains($url,'/releases/tags/')){
  $tag=rawurldecode(substr($url,strrpos($url,'/')+1));$body=['draft'=>false,'prerelease'=>str_contains($tag,'-'),'tag_name'=>$tag,'assets'=>[['name'=>'release-manifest.json','id'=>1]]];
 }elseif(str_ends_with($url,'/assets/1')){$body=['schema'=>1];}
 else{throw new RuntimeException('Unexpected endpoint: '.$url);}
 return ['response'=>['code'=>200],'body'=>json_encode($body)];
}
require '/var/www/html/wp-content/plugins/falcon-wf/src/Updates/GitHubClient.php';
$client=new FalconWF\Updates\GitHubClient('fixture/core');
foreach(['local','staging','development','production'] as $environment){
 $GLOBALS['fixture_environment']=$environment;
 foreach(['normal','paged','stablehigher','empty','network','malformed','bound'] as $mode){
  $GLOBALS['discovery_mode']=$mode;
  foreach([''=>'v0.1.1','*'=>$mode==='stablehigher'?'v0.2.0':'v0.2.0-alpha.10'] as $selection=>$expected){
   if($selection==='' && $mode==='stablehigher'){$expected='v0.2.0';}
   $GLOBALS['discovery_calls']=[];$r=$client->release($selection);
   if(in_array($mode,['normal','paged','stablehigher'],true)){
    if(is_wp_error($r) || $r['tag']!==$expected){throw new RuntimeException('Highest version failed: '.$environment.'/'.$mode.'/'.$selection);}
    if($mode==='paged' && !str_contains(implode(' ',$GLOBALS['discovery_calls']),'page=2')){throw new RuntimeException('Pagination omitted');}
   }elseif(!is_wp_error($r)){throw new RuntimeException('Incomplete discovery accepted');}
  }
 }
 $GLOBALS['discovery_mode']='normal';$GLOBALS['discovery_calls']=[];$pinned=$client->release('v0.1.0');
 if(is_wp_error($pinned) || $pinned['tag']!=='v0.1.0' || count($GLOBALS['discovery_calls'])!==2){throw new RuntimeException('Manual pin unexpectedly followed latest');}
}
echo "PASS: stable/all-release highest versions, stable beating prerelease, semver numeric order, drafts, pagination, failure bounds and manual pin; all environments.\n";
