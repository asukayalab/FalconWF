<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
require_once ABSPATH.'wp-admin/includes/user.php';
$user=wp_insert_user(['user_login'=>'fwf-test-'.bin2hex(random_bytes(6)),'user_pass'=>wp_generate_password(48,true),'role'=>'fwf_agent']);
if(is_wp_error($user)){throw new RuntimeException('Cannot create test agent.');}
$pair=WP_Application_Passwords::create_new_application_password($user,['name'=>'FWF local protocol test']);
if(is_wp_error($pair)){throw new RuntimeException('Cannot issue local application password.');}
[$password,$item]=$pair;
$published=wp_insert_post(['post_title'=>'Allowed fixture','post_content'=>'Public fixture','post_status'=>'publish']);
$private=wp_insert_post(['post_title'=>'Private fixture','post_content'=>'Private data','post_status'=>'draft']);
$scope=['actions'=>['read_published','read_draft','create_draft','edit_draft'],'fields'=>['title','body','location','project_year'],'objects'=>[$published],'types'=>['post','fwf_project']];
$result=FalconWF\AI\Policy::grant($user,$item['uuid'],$scope);
if(is_wp_error($result)){throw new RuntimeException('Cannot grant test policy.');}
// Captured in test process memory; never print the output of this script to a user.
echo wp_json_encode(['endpoint'=>rest_url('falcon-wf/v1/mcp'),'native'=>rest_url('wp/v2/posts'),'actor'=>$user,'login'=>get_userdata($user)->user_login,'password'=>$password,'published'=>$published,'private'=>$private]);
