<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only'); }
require_once ABSPATH.'wp-admin/includes/post.php';
use FalconWF\Admin\PageDesign as P;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$admin=get_current_user_id();$ids=[];$file='fwf-page-design-'.wp_generate_uuid4().'.php';$path=get_stylesheet_directory().'/'.$file;global $pdCount;$pdCount=0;
function pd_ok($value,$label){global $pdCount;if(!$value){throw new RuntimeException('FAIL: '.$label);}$pdCount++;echo "PASS: $label\n";}
try {
 file_put_contents($path,"<?php\n/* Template Name: Falcon test contact */\necho 'FWF_PAGE_DESIGN_RENDER';\n");wp_clean_themes_cache();
 $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Design fixture','post_content'=>'Keep this content','post_name'=>'fwf-design-'.wp_generate_uuid4()]);$ids[]=$id;
 $before=get_post($id,ARRAY_A);$url=get_permalink($id);$theme=get_stylesheet();$home=get_option('page_on_front');
 pd_ok(isset(P::templates($id)[$file]),'real named template discovered from installed active theme');
 $revision=P::revision($id);
 pd_ok(is_wp_error(P::save((string)$id,'../escape.php',$revision,'yes')),'foreign/traversal template rejected');
 pd_ok(is_wp_error(P::save((string)$id,$file,$revision,'no')),'explicit human confirmation required');
 pd_ok(P::save((string)$id,$file,$revision,'yes')===true,'native template assigned');
 pd_ok(get_page_template_slug($id)===$file,'native editor reads dashboard assignment');
 pd_ok(array_diff_key(get_post($id,ARRAY_A),['page_template'=>true])===array_diff_key($before,['page_template'=>true]) && get_permalink($id)===$url && get_option('page_on_front')===$home && get_stylesheet()===$theme,'content URL status homepage and active theme preserved');
 pd_ok(is_wp_error(P::save((string)$id,'default',$revision,'yes')),'stale dashboard rejected after assignment');
 $GLOBALS['post']=get_post($id);$GLOBALS['wp_query']=new WP_Query(['page_id'=>$id]);
 pd_ok(get_page_template()===$path,'WordPress template hierarchy resolves assigned file');
 pd_ok(!is_wp_error(wp_update_post(['ID'=>$id,'page_template'=>'default'],true)),'native editor template update succeeds');
 pd_ok(get_page_template_slug($id)==='' && get_post_meta($id,'_wp_page_template',true)==='default','dashboard reads native editor assignment');
 $revision=P::revision($id);wp_update_post(['ID'=>$id,'post_title'=>'Changed by human']);
 pd_ok(is_wp_error(P::save((string)$id,$file,$revision,'yes')),'stale content change rejected');
 $other=wp_insert_user(['user_login'=>'fwf-design-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(32),'role'=>'editor']);$user=$other;
 update_post_meta($id,'_edit_lock',time().':'.$other);
 pd_ok(is_wp_error(P::save((string)$id,$file,P::revision($id),'yes')),'another human editor lock respected');delete_post_meta($id,'_edit_lock');
 wp_set_current_user($other);pd_ok(is_wp_error(P::save((string)$id,$file,P::revision($id),'yes')),'editor cannot use system template assignment boundary');wp_set_current_user($admin);
 update_post_meta($id,'_wp_page_template','removed-template.php');
 ob_start();P::render();$html=ob_get_clean();pd_ok(str_contains($html,'Template lama tidak tersedia') && str_contains($html,'Edit isi'),'missing template surfaced without silently resetting');
 pd_ok(P::save((string)$id,'default',P::revision($id),'yes')===true,'human can explicitly restore default after missing template');
 pd_ok(P::save((string)$id,'default',P::revision($id),'yes')===true,'unchanged default is idempotent');
 $post=wp_insert_post(['post_type'=>'post','post_status'=>'draft','post_title'=>'Not a Page']);$ids[]=$post;
 pd_ok(is_wp_error(P::save((string)$post,'default',P::revision($post),'yes')),'non Page target refused');
 pd_ok(is_wp_error(P::save([$id],$file,'x','yes')),'malformed target refused');
 for($i=0;$i<21;$i++){$ids[]=wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>'Pagination fixture']);}
 ob_start();P::render();$html=ob_get_clean();pd_ok(str_contains($html,'paged=2') && !str_contains($html,'%25%23'),'pagination links contain usable page numbers');
} finally {
 wp_set_current_user($admin);foreach($ids as $id){wp_delete_post($id,true);}if(isset($user) && !is_wp_error($user)){require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);}
 if(is_file($path)){unlink($path);}wp_clean_themes_cache();wp_reset_query();
}
echo "Page design: $pdCount checks passed.\n";
