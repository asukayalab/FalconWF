<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
global $wpdb;$mode=$args[0]??'';$owner=$args[1]??'';$key='fwf_test_blueprint_http';if(!preg_match('/^[a-f0-9-]{36}$/D',$owner)){throw new RuntimeException('Owner required');}
$theme='fwf-bp-http-'.substr($owner,0,8);$root=get_theme_root().'/'.$theme;
if($mode==='capture'){
 if(get_option($key,false)!==false || is_dir($root) || isset(FalconWF\Content\Definitions::all()['groups']['bp_http_about'])){throw new RuntimeException('Prior fixture collision');}
 $saved=[];foreach(['fwf_builder','fwf_field_contracts','fwf_blueprints','fwf_seo','fwf_modules','fwf_rewrite_pending','rewrite_rules'] as $name){$saved[$name]=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A);}
 add_option($key,['owner'=>$owner,'theme'=>$theme,'saved'=>$saved],'',false);mkdir($root,0755);
 file_put_contents($root.'/style.css',"/*\nTheme Name: Blueprint HTTP Fixture\nVersion: 0.1.0-alpha.1\nTemplate: falcon-theme\n*/");file_put_contents($root.'/index.php','<?php // fixture');file_put_contents($root.'/about.php',"<?php\n/* Template Name: HTTP About */\necho esc_html(FalconTheme\\field(get_the_ID(),'bp_http_hero','fallback'));\n");
 $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'Old HTTP Contact','post_content'=>'KEEP HTTP CONTENT','post_name'=>'bp-http-contact-'.substr($owner,0,8)],true);if(is_wp_error($id)){throw new RuntimeException('Page fixture creation failed');}
 $doc=['schema'=>1,'project_id'=>$theme,'version'=>'0.1.0-alpha.1','website'=>['name'=>'HTTP fixture','purpose'=>'Setup through real cookie and nonce boundary'],'pages'=>[['key'=>'about','title'=>'HTTP About','slug'=>'bp-http-about-'.substr($owner,0,8),'template'=>'about.php'],['key'=>'contact','title'=>'HTTP Contact','slug'=>'bp-http-contact-'.substr($owner,0,8),'template'=>'default']],'definitions'=>['groups'=>['bp_http_about'=>['label'=>'HTTP About fields','active'=>true,'types'=>['page'],'page_roles'=>['about'],'fields'=>[['key'=>'bp_http_hero','kind'=>'text','label'=>'Hero heading','default'=>'HTTP Hero','public'=>true]]]]]];
 file_put_contents($root.'/falcon-blueprint.json',wp_json_encode($doc));$state=get_option($key);$state['existing']=$id;update_option($key,$state,false);wp_clean_themes_cache();echo wp_json_encode(['theme'=>$theme,'existing'=>$id]);
}else{
 $state=get_option($key);if(!is_array($state) || $state['owner']!==$owner){throw new RuntimeException('Owner mismatch');}
 if($mode==='read'){$bp=FalconWF\Content\Blueprint::state($theme);echo wp_json_encode(['state'=>$bp,'existing_body'=>get_post($state['existing'])->post_content,'about_status'=>isset($bp['roles']['about'])?get_post_status($bp['roles']['about']):null,'hero'=>isset($bp['roles']['about'])?get_post_meta($bp['roles']['about'],'_fwf_bp_http_hero',true):null]);}
 elseif($mode==='cleanup'){
  $bp=FalconWF\Content\Blueprint::state($theme);foreach($bp['roles']??[] as $role=>$id){if(get_post_meta($id,'_fwf_blueprint_role',true)===$theme.':'.$role){wp_delete_post($id,true);}}
  wp_delete_post($state['existing'],true);foreach($state['saved'] as $name=>$row){$wpdb->delete($wpdb->options,['option_name'=>$name]);if($row){$wpdb->insert($wpdb->options,$row);}}delete_option($key);wp_cache_flush();foreach(glob($root.'/*')?:[] as $file){unlink($file);}rmdir($root);wp_clean_themes_cache();echo 'Owned blueprint HTTP fixture restored';
 }
}
