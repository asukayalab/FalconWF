<?php
// Explicitly invoked local acceptance rehearsal, real public GitHub transport. Not part of offline CI.
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Updates\ProjectManager as P;
use FalconWF\Updates\Monitor as M;
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
require_once ABSPATH.'wp-admin/includes/theme.php';
$mode=$args[0]??'';$owner=$args[1]??'';$version=$args[2]??'';$key='fwf_live_project_trial';$id='falcon-distribution-trial';$state=get_option($key,null);
if(!preg_match('/^[a-f0-9-]{36}$/D',$owner)){throw new RuntimeException('Owner UUID required.');}
$ok=static function($value,$message){if(!$value){throw new RuntimeException($message);}echo 'PASS: '.$message."\n";};
$keys=['fwf_project_connection','fwf_project_candidate','fwf_update_observations','fwf_blueprints'];
$context=static function($page,$menu) {
 $settings=[];foreach(['stylesheet','template','show_on_front','page_on_front','page_for_posts','fwf_blueprints','fwf_builder','fwf_field_contracts','fwf_design_'.get_stylesheet()] as $name){$settings[$name]=get_option($name,null);}
 return ['page'=>get_post($page,ARRAY_A),'fields'=>get_post_meta($page),'terms'=>wp_get_object_terms($page,'category',['fields'=>'ids']),'menu'=>array_map(static fn($item)=>(array)$item,wp_get_nav_menu_items($menu)?:[]),'settings'=>$settings];
};
if($mode==='cleanup'){
 if(!$state){echo "No live trial state.\n";return;}
 if(($state['owner']??'')!==$owner){throw new RuntimeException('Live trial owner mismatch.');}
 $theme=wp_get_theme($id);if($theme->exists()){$ok(get_stylesheet()!==$id,'live trial theme never activated');$result=delete_theme($id);$ok(!is_wp_error($result),'owned trial theme removed');}
 wp_delete_nav_menu($state['menu']);wp_delete_post($state['page'],true);wp_delete_term($state['term'],'category');
 foreach($state['saved'] as $name=>$value){$value===null?delete_option($name):update_option($name,$value,false);}
 $ok(!get_post($state['page']) && !wp_get_theme($id)->exists(),'owned content/theme cleaned');
 delete_option($key);echo "Live trial cleanup complete.\n";return;
}
if($mode==='inspect'){if(($state['owner']??'')!==$owner){throw new RuntimeException('Owner mismatch.');}$now=$context($state['page'],$state['menu']);foreach($now as $name=>$value){$before=$state['before'][$name];if($name==='menu'){$before=array_map(static fn($item)=>(array)$item,$before);}echo $name.': '.($value===$before?'same':'changed')."\n";}return;}
if(!in_array($mode,['install','update'],true) || !preg_match('/^\d+\.\d+\.\d+-(alpha|beta|rc)\.[1-9]\d*$/D',$version)){throw new RuntimeException('Explicit live trial step/version required.');}
if($mode==='install'){
 $ok(!$state && !wp_get_theme($id)->exists(),'new isolated live trial target');
 $saved=[];foreach($keys as $name){$saved[$name]=get_option($name,null);}
 $page=wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>'Live trial '.$owner,'post_content'=>'Human content preserved across live GitHub design releases.']);$ok(is_int($page) && $page>0,'owned draft fixture created');
 update_post_meta($page,'_fwf_trial_value','Human field value');update_post_meta($page,'_wp_page_template','page-about.php');
 $term=wp_insert_term('Live trial '.$owner,'category');$ok(!is_wp_error($term),'owned taxonomy fixture created');wp_set_object_terms($page,[$term['term_id']],'category');
 $menu=wp_create_nav_menu('Live trial '.$owner);$ok(!is_wp_error($menu),'owned menu fixture created');wp_update_nav_menu_item($menu,0,['menu-item-title'=>'Trial About','menu-item-object-id'=>$page,'menu-item-object'=>'page','menu-item-type'=>'post_type','menu-item-status'=>'publish']);
 $blueprints=get_option('fwf_blueprints',[]);$blueprints[$id]=['roles'=>['about'=>$page],'owned'=>[],'status'=>'applied','version'=>$version];update_option('fwf_blueprints',$blueprints,false);
 $state=['owner'=>$owner,'page'=>$page,'menu'=>$menu,'term'=>$term['term_id'],'saved'=>$saved,'before'=>$context($page,$menu)];add_option($key,$state,'',false);
 $result=P::save(['repo'=>'asukayalab/falcon-wf-project-trial','project_id'=>'','theme_id'=>'','tag'=>'','channel'=>'testing']);$ok($result===true,is_wp_error($result)?$result->get_error_message():'actual public manifest identity discovered and saved');
}
if(($state['owner']??'')!==$owner){throw new RuntimeException('Live trial owner mismatch.');}
$connection=P::connection();$ok($connection['theme_id']===$id && $connection['project_id']===$id,'actual remote project identity matches isolated target');
$manager=new P();if($mode==='update'){$review=get_option('fwf_project_candidate');M::run();$observation=M::status()['project']??[];$ok(($observation['packages'][0]['version']??'')===$version,'periodic project discovery finds next actual release');$ok(get_option('fwf_project_candidate')===$review,'real periodic discovery preserves human review');ob_start();M::notice();$notice=ob_get_clean();$ok(str_contains($notice,$version) && str_contains($notice,'Pembaruan Falcon tersedia'),'admin notification advertises real project update');}
$package=$manager->check();$ok(!is_wp_error($package),is_wp_error($package)?$package->get_error_message():'real GitHub release check succeeds');$ok($package['version']===$version,'automatic channel selects expected real release');
$result=$manager->apply(true);$ok($result===true,is_wp_error($result)?$result->get_error_message():'actual WordPress install/update succeeds from GitHub ZIP');
wp_clean_themes_cache();$ok(wp_get_theme($id)->get('Version')===$version,'installed child version verified');
$ok($context($state['page'],$state['menu'])===$state['before'],'Page/body/fields/terms/menu/blueprint/template/homepage/theme preserved');
$manifest=json_decode(file_get_contents(get_theme_root().'/'.$id.'/falcon-blueprint.json'),true);$ok($manifest['project_id']===$id && $manifest['version']===$version,'actual installed blueprint matches remote release');
$ok(!is_wp_error($manager->check()),'same release can be checked again');$ok(is_wp_error($manager->apply(true)),'same-version write rejected');
echo "Live project $mode complete: $version; real public GitHub metadata and ZIP, native WordPress upgrader.\n";
