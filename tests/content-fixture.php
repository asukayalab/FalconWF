<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
$app=FalconWF\Bootstrap::instance();wp_set_current_user(get_user_by('login','fwf-admin')->ID);
$mode=$args[0]??'';
if($mode==='create'){
 $app->modules->setActive(['projects','publications','learning']);$app->modules->register();
 $p=$app->content->create('fwf_project',['title'=>'FWF HTTP custom fixture','location'=>'Original','project_year'=>2026,'project_stage'=>'concept']);
 if(is_wp_error($p)){throw new RuntimeException('Create failed.');}echo wp_json_encode($p);
}else{
 $id=absint($args[1]??0);$p=get_post($id);if(!$p || $p->post_title!=='FWF HTTP custom fixture'){throw new RuntimeException('Not fixture.');}
 if($mode==='get'){echo wp_json_encode($app->content->get($id));}
 elseif($mode==='cleanup'){wp_delete_post($id,true);echo 'Fixture cleaned';}
 else{throw new RuntimeException('Unknown operation.');}
}
