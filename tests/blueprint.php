<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
require_once ABSPATH.'wp-admin/includes/post.php';
use FalconWF\Content\Blueprint as B;
use FalconWF\Content\Definitions as D;
use FalconWF\Content\Schema as S;
use FalconWF\Content\Repository as R;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);global $wpdb;
$owner=wp_generate_uuid4();$theme='fwf-blueprint-'.substr($owner,0,8);$dir=get_theme_root().'/'.$theme;$ids=[];$count=0;
$keys=['fwf_builder','fwf_field_contracts','fwf_blueprints','fwf_seo','fwf_modules','fwf_rewrite_pending','rewrite_rules'];$saved=[];foreach($keys as $key){$saved[$key]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$key),ARRAY_A);}
$ok=static function($v,$label)use(&$count){if(!$v){throw new RuntimeException('FAIL: '.$label);}$count++;echo "PASS: $label\n";};$must=static function($v){if(is_wp_error($v)){throw new RuntimeException($v->get_error_message());}return $v;};
$filter=static fn()=>$theme;$originalTheme=get_option('stylesheet');$originalHome=get_option('page_on_front');
$doc=['schema'=>1,'project_id'=>$theme,'version'=>'0.1.0-alpha.1','website'=>['name'=>'Fixture site','purpose'=>'Contact and About, editable content'],'pages'=>[['key'=>'about','title'=>'About fixture','slug'=>'bp-about-'.substr($owner,0,8),'template'=>'contact.php'],['key'=>'contact','title'=>'Contact fixture','slug'=>'bp-contact-'.substr($owner,0,8),'template'=>'contact.php']],'definitions'=>['groups'=>['bp_about'=>['label'=>'About fields','active'=>true,'types'=>['page'],'page_roles'=>['about'],'fields'=>[['key'=>'bp_hero','label'=>'Hero heading','kind'=>'text','default'=>'Separate hero','public'=>true],['key'=>'bp_private','label'=>'Private note','kind'=>'text','default'=>'Hidden fixture','public'=>false]]]],'taxonomies'=>['fwf_bp_tags'=>['label'=>'Blueprint tags','active'=>true,'types'=>['page'],'slug'=>'bp-tags-'.substr($owner,0,8),'model'=>'tag']]],'terms'=>[['taxonomy'=>'fwf_bp_tags','slug'=>'studio','name'=>'Studio']],'seo'=>['mode'=>'external','notes'=>'Do not change global indexing']];
$write=static function()use(&$doc,$dir){file_put_contents($dir.'/falcon-blueprint.json',wp_json_encode($doc));};
$input=static function($current,$targets,$modes,$definitions=null){$defs=[];foreach($current['document']['definitions']??[] as $section=>$items){foreach($items as $key=>$d){$defs[$section.'/'.$key]='new';}}return ['revision'=>$current['revision'],'targets'=>$targets,'modes'=>$modes,'definitions'=>$definitions??$defs,'terms'=>'yes','seo'=>'package'];};
if(isset(D::all()['groups']['bp_about']) || isset(D::all()['taxonomies']['fwf_bp_tags']) || taxonomy_exists('fwf_bp_tags')){throw new RuntimeException('Fixture definition collision');}
try{
 mkdir($dir,0755);file_put_contents($dir.'/style.css',"/*\nTheme Name: Blueprint Fixture\nVersion: 0.1.0-alpha.1\nTemplate: falcon-theme\n*/");file_put_contents($dir.'/index.php','<?php // fixture');file_put_contents($dir.'/contact.php',"<?php\n/* Template Name: Fixture Contact */\n");$write();wp_clean_themes_cache();
 add_filter('stylesheet',$filter);add_filter('option_stylesheet',$filter);wp_set_template_globals();
 $existing=$must(wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Old Contact','post_content'=>'KEEP BODY','post_name'=>'old-contact-'.substr($owner,0,8)],true));$ids[]=$existing;$before=get_post($existing,ARRAY_A);$url=get_permalink($existing);
 $current=$must(B::discover());$ok($current['document']['project_id']===$theme,'installed blueprint discovered without changing active theme option');
 $review=$must(B::review($input($current,['about'=>'new','contact'=>(string)$existing],['about'=>'new','contact'=>'keep'])));
 $ok(!get_posts(['post_type'=>'page','name'=>$doc['pages'][0]['slug']]),'review creates no Page');
 $ok(is_wp_error(B::apply($review,false)),'apply requires explicit human confirmation');
 $must(B::apply($review,true));$state=B::state($theme);$about=$state['roles']['about'];$ids[]=$about;
 $ok(get_post_status($about)==='draft' && get_post_meta($about,'_wp_page_template',true)==='contact.php','new role creates draft and points named template');
 $ok(get_post($existing,ARRAY_A)===$before && get_permalink($existing)===$url,'existing keep preserves body title slug status and native assignment');
 $ok(S::values($about,'page')['bp_hero']==='Separate hero' && !isset(S::values($existing,'page')['bp_hero']),'custom field applies only to selected About Page');
 $repo=FalconWF\Bootstrap::instance()->content;$object=$must($repo->get($about));$must($repo->edit($about,$object['revision'],['bp_hero'=>'Human value']));
 $ok(get_post_meta($about,'_fwf_bp_hero',true)==='Human value','existing repository edits Page-scoped field');
 $ok(is_wp_error(S::validate(['bp_hero'=>'Foreign write'],'page',$existing)),'unrelated Page cannot write scoped field');
 $unrelated=$must($repo->create('page',['title'=>'Unrelated draft']));$ids[]=$unrelated['id'];$ok(isset($unrelated['id']),'unrelated draft creation does not require About fields');
 $ok(FalconTheme\field($about,'bp_hero','fallback')==='Human value' && FalconTheme\field($about,'bp_private','fallback')==='fallback','theme helper renders only applicable public fields');
 $ok(B::pageUrl($theme,'contact')===$url && B::pageUrl($theme,'about')==='','role URLs resolve native published Page only');
 $adminId=get_current_user_id();wp_set_current_user(0);$ok(FalconTheme\field($about,'bp_hero','fallback')==='fallback','anonymous cannot render draft field through helper');wp_set_current_user($adminId);
 $ok(term_exists('studio','fwf_bp_tags')!==null,'missing taxonomy and tag created via native owners');
 $ok($wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name='stylesheet'")===$originalTheme && get_option('page_on_front')===$originalHome,'setup does not switch theme or homepage');
 $ok(is_wp_error(B::apply($review,true)),'stale review cannot replay after setup');
 $current=$must(B::discover());$again=$must(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing],['about'=>'new','contact'=>'keep'])));$must(B::apply($again,true));
 $ok(count(get_posts(['post_type'=>'page','post_status'=>'any','name'=>$doc['pages'][0]['slug']]))===1,'repeat setup does not duplicate About');
 $doc['definitions']['groups']['bp_about']['fields'][0]['default']='New package default';$write();$current=$must(B::discover());$newReview=$must(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing],['about'=>'new','contact'=>'keep'])));$must(B::apply($newReview,true));
 $ok(S::values($about,'page')['bp_hero']==='Human value','new package default preserves stored human value');
 $current=$must(B::discover());$stale=$must(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing],['about'=>'new','contact'=>'keep'])));wp_update_post(['ID'=>$about,'post_title'=>'Human changed title']);$ok(is_wp_error(B::apply($stale,true)),'human change invalidates reviewed plan');
 $bad=$doc;$bad['options']=['arbitrary'=>'not allowed'];$ok(is_wp_error(B::validateDocument($bad)),'arbitrary option injection rejected');$bad=$doc;$bad['pages'][]=$bad['pages'][0];$ok(is_wp_error(B::validateDocument($bad)),'duplicate role rejected');
 $doc['definitions']['groups']['bp_about']['fields'][0]['kind']='integer';$doc['definitions']['groups']['bp_about']['fields'][0]['default']=0;$write();$current=$must(B::discover());$ok(is_wp_error(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing],['about'=>'new','contact'=>'keep']))),'stored field kind cannot be silently migrated');
 $doc['definitions']['groups']['bp_about']['fields'][0]['kind']='text';$doc['definitions']['groups']['bp_about']['fields'][0]['default']='';$write();

 wp_update_post(['ID'=>$about,'post_status'=>'publish']);
 $doc['definitions']['groups']['bp_about']['fields'][]=['key'=>'bp_required','label'=>'Required fixture','kind'=>'text','required'=>true,'default'=>'','public'=>true];$write();
 $current=$must(B::discover());$ok(is_wp_error(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing],['about'=>'new','contact'=>'keep']))),'required empty field cannot invalidate existing published Page');
 array_pop($doc['definitions']['groups']['bp_about']['fields']);$write();wp_update_post(['ID'=>$about,'post_status'=>'draft']);
 $bad=$doc;$bad['definitions']['groups']['bp_about']['types']=['page','project'];unset($bad['definitions']['groups']['bp_about']['page_roles']);$ok(is_wp_error(B::validateDocument($bad)),'mixed global Page group cannot bypass role scope');
 $doc['pages'][]=['key'=>'team','title'=>'Team fixture','slug'=>'bp-team-'.substr($owner,0,8),'template'=>'contact.php'];$doc['definitions']['groups']['bp_about']['label']='Changed package label';$write();
 $current=$must(B::discover());$partialReview=$must(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing,'team'=>'new'],['about'=>'new','contact'=>'keep','team'=>'new'])));
 $deny=static fn($new,$old)=>$old;add_filter('pre_update_option_fwf_builder',$deny,10,2);try{$failed=B::apply($partialReview,true);}finally{remove_filter('pre_update_option_fwf_builder',$deny,10);}
 $partial=B::state($theme);$team=$partial['roles']['team']??0;if($team){$ids[]=$team;}
 $ok(is_wp_error($failed) && $partial['status']==='partial' && $team && get_post_status($team)==='draft','partial setup checkpoints newly created draft without deletion');
 $current=$must(B::discover());$retry=$must(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing,'team'=>(string)$team],['about'=>'new','contact'=>'keep','team'=>'new'])));$must(B::apply($retry,true));
 $ok(count(get_posts(['post_type'=>'page','post_status'=>'any','name'=>$doc['pages'][2]['slug']]))===1 && B::state($theme)['status']==='ready','review/retry resumes partial setup without duplicate Page');
 $global=$doc['definitions']['groups']['bp_about'];unset($global['page_roles']);$global['pages']=[];$must(D::save('groups','bp_about',$global,D::revision()));
 $current=$must(B::discover());$ok(is_wp_error(B::review($input($current,['about'=>(string)$about,'contact'=>(string)$existing,'team'=>(string)$team],['about'=>'new','contact'=>'keep','team'=>'new']))),'global existing Page group cannot be silently narrowed by package');
 $admin=get_current_user_id();$editor=wp_insert_user(['user_login'=>'bp-editor-'.$owner,'user_pass'=>wp_generate_password(32),'role'=>'editor']);wp_set_current_user($editor);$ok(is_wp_error(B::discover()),'editor cannot inspect/apply system blueprint');wp_set_current_user($admin);
 echo "Blueprint checks passed: $count. Local filtered theme context; no active-theme option changed.\n";
}finally{
 remove_filter('stylesheet',$filter);remove_filter('option_stylesheet',$filter);wp_set_template_globals();
 foreach(array_unique($ids) as $id){wp_delete_post($id,true);}if(taxonomy_exists('fwf_bp_tags')){foreach(get_terms(['taxonomy'=>'fwf_bp_tags','hide_empty'=>false]) as $term){wp_delete_term($term->term_id,'fwf_bp_tags');}unregister_taxonomy('fwf_bp_tags');}
 if(isset($editor)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($editor);}
 foreach($saved as $key=>$row){$wpdb->delete($wpdb->options,['option_name'=>$key]);if($row){$wpdb->insert($wpdb->options,$row);}}wp_cache_flush();
 foreach(glob($dir.'/*')?:[] as $file){unlink($file);}if(is_dir($dir)){rmdir($dir);}wp_clean_themes_cache();
}
