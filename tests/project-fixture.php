<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Reference fixture local only.'); }
$mode=$args[0]??'';$owner=$args[1]??'';$key='fwf_reference_test';
if (!preg_match('/^[a-f0-9-]{36}$/D',$owner)) { throw new RuntimeException('Fixture owner required.'); }
require_once ABSPATH.'wp-admin/includes/plugin.php';require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/theme.php';
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$themeRoot=get_theme_root().'/falcon-reference';
$save=static function ($snapshot) use ($key) { update_option($key,$snapshot,false); };
if ($mode==='setup') {
    $settings=[];foreach(['stylesheet','template','current_theme','theme_switched','theme_mods_falcon-reference','theme_mods_falcon-theme','show_on_front','page_on_front','page_for_posts','blog_public','fwf_builder','fwf_modules','fwf_field_contracts','fwf_rewrite_pending','fwf_maintenance','fwf_seo','fwf_design_falcon-reference','active_plugins'] as $name){$settings[$name]=['exists'=>get_option($name,null)!==null,'value'=>get_option($name,null)];}
    $files=[];if(is_dir($themeRoot)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($themeRoot,FilesystemIterator::SKIP_DOTS)) as $file){if($file->isLink()){throw new RuntimeException('Reference tree symlink refused.');}$files[substr($file->getPathname(),strlen($themeRoot)+1)]=file_get_contents($file->getPathname());}}
    $snapshot=['owner'=>$owner,'settings'=>$settings,'files'=>$files,'theme_existed'=>is_dir($themeRoot),'ids'=>[]];
    if(!add_option($key,$snapshot,'',false)){throw new RuntimeException('Prior reference snapshot requires recovery.');}
    $manifest=json_decode(file_get_contents('/artifacts/projects/project-manifest.json'),true);$package=$manifest['packages'][0];
    $zip='/artifacts/projects/'.$package['artifact'];if(!hash_equals($package['sha256'],hash_file('sha256',$zip))){throw new RuntimeException('Project hash mismatch.');}
    foreach($package['compatibility'] as $id=>$range){$version=$id==='falcon-theme'?wp_get_theme('falcon-theme')->get('Version'):get_plugin_data(WP_PLUGIN_DIR.'/falcon-wf/falcon-wf.php')['Version'];if(version_compare($version,$range['min'],'<') || version_compare($version,$range['max_exclusive'],'>=')){throw new RuntimeException('Project compatibility mismatch.');}}
    require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
    $result=(new Theme_Upgrader(new WP_Ajax_Upgrader_Skin()))->install($zip,['overwrite_package'=>true]);if($result!==true){throw new RuntimeException('Reference install failed.');}
    if(get_stylesheet()!==$settings['stylesheet']['value']){throw new RuntimeException('Install switched theme.');}
    delete_option('fwf_builder');FalconWF\Content\Definitions::init();$definitions=FalconWF\Content\Definitions::all();$definitions['groups']['project_details']['fields']['location']['public']=false;update_option('fwf_builder',$definitions,false);
    $app=FalconWF\Bootstrap::instance();$app->modules->setActive(['projects']);$app->modules->register();
    update_option('fwf_seo',['mode'=>'falcon'],false);update_option('fwf_maintenance',false,false);update_option('blog_public','1');
    $insert=static function($post) use (&$snapshot,$save){$id=wp_insert_post($post,true);if(is_wp_error($id)){throw new RuntimeException($id->get_error_message());}$snapshot['ids'][]=$id;$save($snapshot);return $id;};
    $home=$insert(['post_type'=>'page','post_title'=>'Reference Beranda','post_content'=>'<p>Reference homepage editable content.</p>','post_status'=>'publish']);
    $page=$insert(['post_type'=>'page','post_title'=>'Reference Tentang','post_content'=>'<h2>Reference section</h2><p>Reference ordinary page body.</p>','post_status'=>'publish']);
    $projects=[];
    foreach(['published'=>'publish','draft'=>'draft','private'=>'private','password'=>'publish'] as $name=>$status){
        $post=$app->content->create('fwf_project',['title'=>'Reference '.$name,'body'=>'Reference body '.$name,'summary'=>'Reference summary '.$name,'location'=>'REFERENCE_PRIVATE_LOCATION','project_year'=>2026,'project_stage'=>'concept']);if(is_wp_error($post)){throw new RuntimeException($post->get_error_message());}
        $id=$post['id'];$snapshot['ids'][]=$id;$save($snapshot);$projects[$name]=$id;
        $changed=wp_update_post(['ID'=>$id,'post_status'=>$status,'post_password'=>$name==='password'?'fixture-only-password':''],true);if(is_wp_error($changed) || get_post_status($id)!==$status){throw new RuntimeException('Fixture status transition failed.');}
    }
    switch_theme('falcon-reference');update_option('show_on_front','page');update_option('page_on_front',$home);update_option('page_for_posts',0);
    echo 'FWF_REFERENCE_JSON:'.wp_json_encode(['home'=>home_url('/'),'page'=>get_permalink($page),'archive'=>get_post_type_archive_link('fwf_project'),'single'=>get_permalink($projects['published']),'draft'=>get_permalink($projects['draft']),'private'=>get_permalink($projects['private']),'password'=>get_permalink($projects['password']),'ids'=>$snapshot['ids']])."\n";
} else {
    $snapshot=get_option($key,false);if(!$snapshot || !hash_equals($snapshot['owner'],$owner)){throw new RuntimeException('Snapshot owner mismatch.');}
    if($mode==='hide-site'){update_option('blog_public','0');}
    elseif($mode==='design'){ $result=FalconWF\Settings::saveDesign(['ink'=>'#135724','body_font'=>'system','h1'=>'68','spacing'=>'32'],get_stylesheet(),FalconTheme\Design::revision()); if(is_wp_error($result)){throw new RuntimeException($result->get_error_message());} }
    elseif($mode==='deactivate'){deactivate_plugins('falcon-wf/falcon-wf.php');}
    elseif($mode==='dynamic-home'){update_option('show_on_front','posts');update_option('blog_public','1');}
    elseif($mode==='parent'){switch_theme('falcon-theme');update_option('show_on_front','posts');update_option('blog_public','1');}
    elseif($mode==='restore'){
        // Restore exactly the original options without activation/switch hooks.
        foreach($snapshot['settings'] as $name=>$state){$state['exists']?update_option($name,$state['value']):delete_option($name);}
        foreach($snapshot['ids'] as $id){wp_delete_post($id,true);}
        $deleted=delete_theme('falcon-reference');if(is_wp_error($deleted)){throw new RuntimeException('Reference tree restore failed.');}
        if($snapshot['theme_existed']){foreach($snapshot['files'] as $relative=>$bytes){$target=$themeRoot.'/'.$relative;wp_mkdir_p(dirname($target));if(file_put_contents($target,$bytes)===false){throw new RuntimeException('Reference file restore failed.');}}}
        wp_clean_themes_cache();delete_option($key);echo "Reference fixture restored.\n";
    }else{throw new RuntimeException('Unknown mode.');}
}
