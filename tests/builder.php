<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only'); }
use FalconWF\Content\Definitions as D;
use FalconWF\Content\Schema as S;
use FalconWF\Content\Repository as R;
$app=FalconWF\Bootstrap::instance(); wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$snapshot=D::all();$contracts=get_option('fwf_field_contracts',[]);$modules=get_option('fwf_modules',[]);$ids=[];global $count;$count=0;
function builder_ok($x,$label){global $count;if(!$x){throw new RuntimeException('FAIL: '.$label);}$count++;echo "PASS: $label\n";}
function definition($section,$key,$data){return D::save($section,$key,$data,D::revision());}
try {
 $type=['label'=>'Fixture Documentation','slug'=>'fixture-docs','model'=>'page','active'=>1];
 builder_ok(!is_wp_error(definition('types','fwf_docs_test',$type)),'create page-like custom type');$app->modules->register();
 builder_ok(get_post_type_object('fwf_docs_test')->hierarchical && !get_post_type_object('fwf_docs_test')->has_archive,'native hierarchy enabled');
 builder_ok(get_post_type_object('fwf_project')->show_in_menu && get_post_type_object('fwf_project')->menu_position===21,'Project appears after Pages');
 builder_ok(is_wp_error(definition('types','fwf_bad',['label'=>'Bad','slug'=>'project','model'=>'post','active'=>1])),'duplicate slug refused');
 $group=['label'=>'Extra information','types'=>['post','page','fwf_docs_test'],'active'=>1,'fields'=>[
  ['key'=>'fixture_note','label'=>'Note','kind'=>'textarea','default'=>'','help'=>'Add a note'],
  ['key'=>'fixture_budget','label'=>'Budget','kind'=>'integer','default'=>'0','required'=>1],
  ['key'=>'fixture_status','label'=>'Status','kind'=>'select','default'=>'idea','choices'=>"idea: Idea\ndone: Done",'public'=>1]
 ]];
 builder_ok(!is_wp_error(definition('groups','fixture_info',$group)),'create configurable field group for Posts Pages custom type');$app->modules->register();
 builder_ok(isset(S::custom('post')['fixture_note']) && isset(S::custom('page')['fixture_budget']),'locations share one schema');
 builder_ok(is_wp_error($app->content->create('fwf_docs_test',['title'=>'Missing required'])),'AI create requires mandatory field');
 $p=$app->content->create('fwf_docs_test',['title'=>'Builder published fixture','fixture_budget'=>123,'fixture_note'=>"one\ntwo",'fixture_status'=>'done']);
 builder_ok(!is_wp_error($p) && $p['fields']['fixture_note']==="one\ntwo",'dynamic repository persists typed multiline field');$id=$p['id'];$ids[]=$id;
 builder_ok(is_wp_error(S::validate(['fixture_budget'=>'123'],'fwf_docs_test')),'dynamic integer refuses untyped agent value');
 $rev=R::metadataRevision($id,'fwf_docs_test'); $editor=new FalconWF\Admin\ContentEditor($app->content);
 $_POST=['fwf_fields_nonce'=>wp_create_nonce('fwf_native_fields_'.$id),'fwf_meta_revision'=>$rev,'fwf_content_revision'=>R::nativeRevision($id),'fields'=>['fixture_budget'=>'222','fixture_note'=>"native\ntext",'fixture_status'=>'idea']];
 wp_update_post(['ID'=>$id,'post_title'=>'Native main editor save']);$_POST=[];
 builder_ok((int)get_post_meta($id,'_fwf_fixture_budget',true)===222,'native pre-write chain saves custom fields');
 $_POST=['fwf_fields_nonce'=>wp_create_nonce('fwf_native_fields_'.$id),'fwf_meta_revision'=>$rev,'fwf_content_revision'=>R::nativeRevision($id),'fields'=>['fixture_budget'=>'333','fixture_note'=>'stale','fixture_status'=>'idea']];
 wp_update_post(['ID'=>$id,'post_title'=>'Stale main save']);$_POST=[];
 builder_ok((int)get_post_meta($id,'_fwf_fixture_budget',true)===222 && get_transient('fwf_fields_notice_'.get_current_user_id()),'native stale whole write refused with notice');delete_transient('fwf_fields_notice_'.get_current_user_id());
 ob_start();$editor->render($id,true);$html=ob_get_clean();builder_ok(str_contains($html,'fwf_fields_nonce') && !str_contains($html,'<form'),'metabox embeds fields without nested form');
 builder_ok(is_wp_error(D::save('groups','fixture_info',$group,'stale')),'builder stale revision refused');
 $changed=$group;$changed['fields'][1]['kind']='text';builder_ok(is_wp_error(definition('groups','fixture_info',$changed)),'saved field kind cannot silently change');
 builder_ok(!is_wp_error(definition('taxonomies','fwf_fixture_cat',['label'=>'Fixture Categories','slug'=>'fixture-categories','model'=>'category','types'=>['fwf_docs_test'],'active'=>1])),'create reusable taxonomy');$app->modules->register();
 $term=wp_insert_term('Buildings','fwf_fixture_cat',['slug'=>'buildings']);wp_set_object_terms($id,[$term['term_id']],'fwf_fixture_cat');wp_update_post(['ID'=>$id,'post_status'=>'publish']);
 $draft=$app->content->create('fwf_docs_test',['title'=>'Hidden draft fixture','fixture_budget'=>1]);$ids[]=$draft['id'];wp_set_object_terms($draft['id'],[$term['term_id']],'fwf_fixture_cat');
 $protected=wp_insert_post(['post_type'=>'fwf_docs_test','post_title'=>'Protected fixture','post_status'=>'publish','post_password'=>'protected','meta_input'=>['_fwf_fixture_budget'=>1]]);$ids[]=$protected;wp_set_object_terms($protected,[$term['term_id']],'fwf_fixture_cat');
 $listing=do_shortcode('[falcon_listing type="fwf_docs_test" taxonomy="fwf_fixture_cat" term="buildings"]');
 builder_ok(str_contains($listing,'Native main editor save') && !str_contains($listing,'Hidden draft') && !str_contains($listing,'Protected fixture'),'taxonomy listing excludes draft and password protected posts');
 builder_ok(do_shortcode('[falcon_listing type="fwf_docs_test" taxonomy="category" term="buildings"]')==='','listing rejects unrelated taxonomy');
 $second=wp_insert_term('Gardens','fwf_fixture_cat',['slug'=>'gardens']);
 $other=wp_insert_post(['post_type'=>'fwf_docs_test','post_title'=>'Garden published fixture','post_status'=>'publish','meta_input'=>['_fwf_fixture_budget'=>1]]);$ids[]=$other;wp_set_object_terms($other,[$second['term_id']],'fwf_fixture_cat');
 $multi=do_shortcode('[falcon_listing type="fwf_docs_test" taxonomy="fwf_fixture_cat" term="buildings,gardens" limit="12"]');
 builder_ok(str_contains($multi,'Native main editor save') && str_contains($multi,'Garden published fixture'),'multiple terms use OR listing');
 $all=do_shortcode('[falcon_listing type="fwf_docs_test" taxonomy="fwf_fixture_cat" term="*" limit="12"]');
 builder_ok(substr_count($all,'<article>')===2,'check all matches assigned terms and excludes draft/password');
 builder_ok(substr_count(do_shortcode('[falcon_listing type="fwf_docs_test" limit="1"]'),'<article>')===1,'listing honors configured limit');
 wp_delete_term($second['term_id'],'fwf_fixture_cat');

 builder_ok(isset(S::jsonProperties()['fixture_budget']),'MCP discovery includes dynamic fields');
 ob_start();\FalconTheme\contentDetails($id);$detail=ob_get_clean();builder_ok(str_contains($detail,'Status') && !str_contains($detail,'native') && !str_contains($detail,'Note'),'theme detail respects dynamic field visibility');
 $request=new WP_REST_Request('GET','/falcon-wf/v1/content/'.$id);wp_set_current_user(0);$response=rest_do_request($request);$public=$response->get_data();
 builder_ok(isset($public['fields']['fixture_status']) && !isset($public['fields']['fixture_note']),'public REST respects field visibility');wp_set_current_user(get_user_by('login','fwf-admin')->ID);
 $disabled=$group;$disabled['active']=0;builder_ok(!is_wp_error(definition('groups','fixture_info',$disabled)) && !isset(S::custom('post')['fixture_note']) && get_post_meta($id,'_fwf_fixture_note',true)==="native\ntext",'disable field group preserves values');
 wp_delete_term($term['term_id'],'fwf_fixture_cat');
 echo "Builder integration assertions passed: $count\n";
}finally{
 $_POST=[];foreach($ids as $id){wp_delete_post($id,true);}update_option('fwf_builder',$snapshot,false);update_option('fwf_field_contracts',$contracts,false);update_option('fwf_modules',$modules,false);update_option('fwf_rewrite_pending',true,false);
}
