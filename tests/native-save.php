<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
use FalconWF\Content\Definitions as D;
use FalconWF\Content\Repository as R;
use FalconWF\Content\Schema as S;
use FalconWF\Packages\Lock;
$app=FalconWF\Bootstrap::instance();wp_set_current_user(get_user_by('login','fwf-admin')->ID);
global $checks;
$builder=D::all();$contracts=get_option('fwf_field_contracts',[]);$ids=[];$checks=0;
function native_ok($value,$label) { global $checks;if(!$value){throw new RuntimeException('FAIL: '.$label);}$checks++;echo "PASS: $label\n"; }
function native_form($id,$changes=[]) {
    $type=get_post_type($id);
    return ['post_ID'=>$id,'fwf_fields_nonce'=>wp_create_nonce('fwf_native_fields_'.$id),'fwf_meta_revision'=>R::metadataRevision($id,$type),'fwf_content_revision'=>R::nativeRevision($id),'fields'=>array_replace(S::values($id,$type),$changes)];
}
function native_write($id,$form,$changes=[]) {
    $_POST=$form;
    try { return wp_update_post(['ID'=>$id]+$changes,true); }
    finally { $_POST=[]; }
}
try {
    $group=['label'=>'Native guard fixture','active'=>1,'types'=>['post','page','fwf_project'],'fields'=>[['key'=>'native_required','label'=>'Required native fixture','kind'=>'text','default'=>'','required'=>1]]];
    native_ok(!is_wp_error(D::save('groups','native_guard',$group,D::revision())),'native required group registered through shared definitions');$app->modules->register();
    foreach (['post','page','fwf_project'] as $type) {
        $id=wp_insert_post(['post_type'=>$type,'post_title'=>'Native guard fixture','post_content'=>'Original body','post_excerpt'=>'Original summary','post_status'=>'draft'],true);$ids[]=$id;
        native_ok(is_int($id) && $id>0,$type.' incomplete draft remains available');
        native_ok(!apply_filters('use_block_editor_for_post_type',true,$type),'single native form selected for '.$type.' with Falcon fields');
        $before=R::nativeRevision($id);$form=native_form($id);
        $result=native_write($id,$form,['post_title'=>'Must not save','post_content'=>'Must not save','post_excerpt'=>'Must not save','post_status'=>'publish']);
        native_ok(is_wp_error($result) && R::nativeRevision($id)===$before,$type.' invalid required field blocks title body summary and publish');
        $result=native_write($id,native_form($id,['native_required'=>'Valid']),['post_status'=>'publish']);
        native_ok(!is_wp_error($result) && get_post_status($id)==='publish' && get_post_meta($id,'_fwf_native_required',true)==='Valid',$type.' valid native field and publish succeed');
    }
    $id=$ids[2];wp_update_post(['ID'=>$id,'post_status'=>'draft']);
    $stale=native_form($id,['native_required'=>'Stale']);$app->content->saveFields($id,R::metadataRevision($id,'fwf_project'),['native_required'=>'Other editor']);$before=R::nativeRevision($id);
    native_ok(is_wp_error(native_write($id,$stale,['post_title'=>'Stale title','post_status'=>'publish'])) && R::nativeRevision($id)===$before,'stale metadata rejects entire native update');
    $stale=native_form($id);wp_update_post(['ID'=>$id,'post_content'=>'Concurrent native body']);$before=R::nativeRevision($id);
    native_ok(is_wp_error(native_write($id,$stale,['post_content'=>'Old editor body','post_status'=>'publish'])) && R::nativeRevision($id)===$before,'native content conflict rejects old form even when metadata unchanged');
    $stale=native_form($id);$changed=$group;$changed['fields'][0]['help']='Changed schema';D::save('groups','native_guard',$changed,D::revision());$before=R::nativeRevision($id);
    native_ok(is_wp_error(native_write($id,$stale,['post_status'=>'publish'])) && R::nativeRevision($id)===$before,'definition conflict refuses publish before write');
    foreach (['fwf_fields_nonce'=>null,'fwf_content_revision'=>null,'fields'=>'malformed'] as $key=>$bad) {
        $form=native_form($id);$form[$key]=$bad;$before=R::nativeRevision($id);
        native_ok(is_wp_error(native_write($id,$form,['post_status'=>'publish'])) && R::nativeRevision($id)===$before,'invalid native boundary refuses '.$key);
    }
    $form=native_form($id);$before=R::nativeRevision($id);wp_set_current_user(0);
    native_ok(is_wp_error(native_write($id,$form,['post_status'=>'publish'])) && R::nativeRevision($id)===$before,'unauthorized actor cannot save native fields or publish');
    wp_set_current_user(get_user_by('login','fwf-admin')->ID);
    $form=native_form($id);$form['fields']['unknown']='unexpected';$before=R::nativeRevision($id);
    native_ok(is_wp_error(native_write($id,$form,['post_status'=>'publish'])) && R::nativeRevision($id)===$before,'unknown submitted field rejects full write');
    $owner=Lock::acquire('content_'.$id);$before=R::nativeRevision($id);
    try { native_ok(is_wp_error(native_write($id,native_form($id),['post_status'=>'publish'])) && R::nativeRevision($id)===$before,'concurrent content writer blocks native publish'); }
    finally { Lock::release('content_'.$id,$owner); }
    $owner=Lock::acquire('builder');
    try { native_ok(is_wp_error(native_write($id,native_form($id),['post_status'=>'publish'])),'concurrent schema writer blocks native publish'); }
    finally { Lock::release('builder',$owner); }
    $fail=static fn($check,$object,$key)=>$object===$id && $key==='_fwf_native_required'?false:$check;
    add_filter('update_post_metadata',$fail,10,3);$before=R::nativeRevision($id);
    try { native_ok(is_wp_error(native_write($id,native_form($id,['native_required'=>'Persistence failure']),['post_status'=>'publish'])) && R::nativeRevision($id)===$before,'metadata persistence failure blocks native publish and body write'); }
    finally { remove_filter('update_post_metadata',$fail,10); }
    native_ok(get_option('fwf_lock_content_'.$id,false)===false && get_option('fwf_lock_builder',false)===false,'failed native writes release owned locks');
    update_post_meta($id,'_fwf_native_required','');$before=R::nativeRevision($id);
    native_ok(is_wp_error(wp_update_post(['ID'=>$id,'post_status'=>'publish'],true)) && R::nativeRevision($id)===$before,'programmatic quick or bulk publication cannot bypass required fields');
    foreach (['publish','future','private'] as $status) {
        $request=new WP_REST_Request('POST','/wp/v2/fwf_project/'.$id);$request->set_body_params(['title'=>'REST must not save','content'=>'REST must not save','status'=>$status]);
        $response=rest_do_request($request);
        native_ok($response->get_status()===400 && R::nativeRevision($id)===$before,'native REST '.$status.' refuses invalid stored schema before write');
    }
    $request=new WP_REST_Request('POST','/wp/v2/fwf_project');$request->set_body_params(['title'=>'REST create must not publish','status'=>'publish']);
    native_ok(rest_do_request($request)->get_status()===400,'native REST create publication requires valid field contract');
    $request=new WP_REST_Request('POST','/wp/v2/fwf_project/'.$id);$request->set_body_params(['title'=>'Forged metadata must not write','status'=>'publish','meta'=>['_fwf_native_required'=>'Forged valid value']]);
    native_ok(rest_do_request($request)->get_status()===403 && R::nativeRevision($id)===$before,'native REST protected metadata cannot masquerade as stored valid publication fields');
    $request=new WP_REST_Request('POST','/wp/v2/fwf_project/'.$id);$request->set_body_params(['content'=>'Draft must not partially write','status'=>'draft','meta'=>['_fwf_native_required'=>'Forged']]);
    native_ok(rest_do_request($request)->get_status()===403 && R::nativeRevision($id)===$before,'native REST protected metadata refusal precedes draft title or body write');
    native_ok(!is_wp_error(native_write($id,native_form($id,['native_required'=>'Scheduled']),['post_status'=>'future','post_date'=>date('Y-m-d H:i:s',time()+3600),'post_date_gmt'=>gmdate('Y-m-d H:i:s',time()+3600)])),'valid native scheduled save succeeds');
    update_post_meta($id,'_fwf_native_required','');do_action('publish_future_post',$id);
    native_ok(get_post_status($id)==='draft','scheduled publication returns invalid content to draft before core publisher');
    native_ok(apply_filters('use_block_editor_for_post_type',true,'attachment'),'editor choice unchanged for type without Falcon fields');
    echo "Native save assertions passed: $checks\n";
} finally {
    $_POST=[];$app->content->closeNativeWrites();
    foreach ($ids as $id) { wp_delete_post($id,true); }
    update_option('fwf_builder',$builder,false);update_option('fwf_field_contracts',$contracts,false);update_option('fwf_rewrite_pending',true,false);
    delete_transient('fwf_fields_notice_'.get_current_user_id());
}
