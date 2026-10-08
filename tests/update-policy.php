<?php
// Standalone policy unit check; no WordPress/database or external provider acceptance.
class WP_Error { public function __construct(public string $code, public string $message) {} }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function wp_get_environment_type(): string { return $GLOBALS['fixture_environment']; }
require '/var/www/html/wp-content/plugins/falcon-wf/src/Updates/UpdateManager.php';
foreach (['local','staging','development','production'] as $environment) {
    $GLOBALS['fixture_environment']=$environment;
    $allowed=in_array($environment,['local','staging'],true);
    if (is_wp_error(FalconWF\Updates\UpdateManager::validateSelection('v0.1.0-alpha.3'))===$allowed || is_wp_error(FalconWF\Updates\UpdateManager::validateSelection('')) || is_wp_error(FalconWF\Updates\UpdateManager::validateSelection('*',true))===$allowed) { throw new RuntimeException('Release environment policy failed: '.$environment); }
}
echo "PASS: release policy unit check: prerelease local/staging only; stable in all environments\n";

// Isolated discovery unit checks, same installed transport class; not a live provider/host test.
function wp_remote_retrieve_response_code($r){return $r['response']['code'];}
function wp_remote_retrieve_body($r){return $r['body'];}
function wp_safe_remote_get($url,$args){
    if(isset($args['headers']['Authorization'])){throw new RuntimeException('Public discovery sent credential.');}
    $GLOBALS['discovery_calls'][]=$url;$mode=$GLOBALS['discovery_mode'];$body=[];
    if(str_contains($url,'/releases?')){
        if($mode==='network'){return new WP_Error('fixture','Network refused.');}
        if($mode==='malformed'){$body=['error'=>'invalid'];}
        elseif($mode==='empty'){$body=[];}
        elseif($mode==='bound' || ($mode==='paged' && str_ends_with($url,'page=1'))){$body=array_fill(0,100,['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.1.0-alpha.9']);}
        else{$body=[['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.1.0-alpha.9'],['draft'=>false,'prerelease'=>true,'tag_name'=>'v0.1.0-alpha.10'],['draft'=>true,'prerelease'=>true,'tag_name'=>'v99.0.0-alpha.1'],['draft'=>false,'prerelease'=>false,'tag_name'=>'v99.0.0'],['draft'=>false,'prerelease'=>true,'tag_name'=>'invalid']];}
    }elseif(str_contains($url,'/releases/tags/')){
        $tag=rawurldecode(substr($url,strrpos($url,'/')+1));$body=['draft'=>false,'prerelease'=>true,'tag_name'=>$tag,'assets'=>[['name'=>'release-manifest.json','id'=>1]]];
    }elseif(str_ends_with($url,'/assets/1')){$body=['schema'=>1];}
    else{throw new RuntimeException('Unexpected discovery endpoint.');}
    return ['response'=>['code'=>200],'body'=>json_encode($body)];
}
require '/var/www/html/wp-content/plugins/falcon-wf/src/Updates/GitHubClient.php';
$GLOBALS['fixture_environment']='staging';$client=new FalconWF\Updates\GitHubClient('fixture/core');
foreach(['normal','paged','empty','network','malformed','bound'] as $mode){
    $GLOBALS['discovery_mode']=$mode;$GLOBALS['discovery_calls']=[];$r=$client->release('*');
    if(in_array($mode,['normal','paged'],true)){
        if(is_wp_error($r) || $r['tag']!=='v0.1.0-alpha.10'){throw new RuntimeException('Numeric latest discovery failed: '.$mode);}
        if($mode==='paged' && !str_contains(implode(' ',$GLOBALS['discovery_calls']),'page=2')){throw new RuntimeException('Pagination omitted.');}
    }elseif(!is_wp_error($r)){throw new RuntimeException('Failed discovery accepted: '.$mode);}
}
$GLOBALS['fixture_environment']='production';$GLOBALS['discovery_calls']=[];
if(!is_wp_error($client->release('*')) || $GLOBALS['discovery_calls']){throw new RuntimeException('Production trial reached transport.');}
echo "PASS: automatic discovery unit checks: numeric order, draft/stable skip, pagination, empty/network/malformed/bounded refusal and production no-transport.\n";
