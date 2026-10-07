<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\AI\ProviderClient;
use FalconWF\Bootstrap;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if(!defined('FWF_OPENAI_API_KEY')){define('FWF_OPENAI_API_KEY','fixture-not-a-real-key');}
$content=Bootstrap::instance()->content;
$draft=$content->create('post',['title'=>'Original title','body'=>'Private body not selected']);
$before=get_option('fwf_outbound',[]);$budget=get_option('fwf_ai_budget',[]);$called=0;
$mock=static function($pre,$args,$url) use (&$called) {
 if($url!=='https://api.openai.com/v1/responses'){return new WP_Error('fixture_network','No external network allowed in provider fixture.');}
 $called++;$body=json_decode($args['body'],true);$input=json_decode($body['input'],true);
 if($body['store']!==false || $input['field']!=='title' || $input['text']!=='Original title' || str_contains($args['body'],'Private body')){throw new RuntimeException('Context minimization failed.');}
 return ['headers'=>[],'response'=>['code'=>200,'message'=>'OK'],'body'=>wp_json_encode(['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'Reviewed title']]]],'usage'=>['input_tokens'=>10,'output_tokens'=>3,'total_tokens'=>13]])];
};
add_filter('pre_http_request',$mock,10,3);
try {
 update_option('fwf_outbound',['enabled'=>true,'model'=>'fixture-model','fields'=>['title']],false);delete_option('fwf_ai_budget');
 $provider=new ProviderClient($content);
 $denied=$provider->suggest($draft['id'],'body','Rewrite');if(!is_wp_error($denied) || $called!==0){throw new RuntimeException('Field scope denial failed.');}
 $proposal=$provider->suggest($draft['id'],'title','Rewrite title');if(is_wp_error($proposal)){throw new RuntimeException($proposal->get_error_message());}
 if($content->get($draft['id'])['fields']['title']!=='Original title'){throw new RuntimeException('Provider mutated draft automatically.');}
 $applied=$provider->apply($proposal['proposal']);if(is_wp_error($applied) || $applied['fields']['title']!=='Reviewed title' || $applied['status']!=='draft'){throw new RuntimeException('Human apply failed.');}
 if(!is_wp_error($provider->apply($proposal['proposal']))){throw new RuntimeException('Proposal was replayed.');}
 // Return title to initial so the context fixture remains deterministic.
 $content->edit($draft['id'],$applied['revision'],['title'=>'Original title']);
 $proposal=$provider->suggest($draft['id'],'title','Rewrite title');
 $current=$content->get($draft['id']);$content->edit($draft['id'],$current['revision'],['title'=>'Changed by human']);
 if(!is_wp_error($provider->apply($proposal['proposal']))){throw new RuntimeException('Stale proposal accepted.');}
 delete_option('fwf_proposal_'.$proposal['proposal']);
 update_option('fwf_outbound',['enabled'=>false,'model'=>'fixture-model','fields'=>['title']],false);
 $beforeCalled=$called;if(!is_wp_error($provider->suggest($draft['id'],'title','Rewrite')) || $called!==$beforeCalled){throw new RuntimeException('Disconnect did not stop provider requests.');}
 echo 'PASS: outbound allowed context, denied field, proposal-only, human apply, replay/stale rejection and disconnect (mock provider only)' . "\n";
} finally {
 remove_filter('pre_http_request',$mock,10);update_option('fwf_outbound',$before,false);update_option('fwf_ai_budget',$budget,false);wp_delete_post($draft['id'],true);
}
