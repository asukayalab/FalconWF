<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
require_once ABSPATH.'wp-admin/includes/user.php';
$posts=get_posts(['post_type'=>'fwf_project','post_status'=>'any','s'=>'Native HTTP fixture','posts_per_page'=>100]);$count=0;
foreach($posts as $p){if($p->post_title==='Native HTTP fixture' && get_post_meta($p->ID,'_fwf_location',true)==='Native HTTP changed' && !get_post_meta($p->ID,'_fwf_demo_key',true)){wp_delete_post($p->ID,true);$count++;}}
foreach(get_users(['search'=>'fwf-webtest-*','search_columns'=>['user_login']]) as $user){if(str_starts_with($user->user_login,'fwf-webtest-')){wp_delete_user($user->ID);$count++;}}
echo "Local abandoned test fixtures removed: $count\n";
