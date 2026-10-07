<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
$actor=absint($args[0]??0);$mode=$args[1]??'revoke';
$user=get_userdata($actor);
if(!$user || !str_starts_with($user->user_login,'fwf-test-')){throw new RuntimeException('Not a disposable fixture user.');}
FalconWF\AI\Policy::revoke($actor);
if($mode==='password') { WP_Application_Passwords::delete_all_application_passwords($actor); }
if($mode==='delete') {
 foreach(get_posts(['author'=>$actor,'post_type'=>'any','post_status'=>'any','numberposts'=>-1]) as $p){wp_delete_post($p->ID,true);}
 foreach(array_slice($args,2) as $id){wp_delete_post(absint($id),true);}
 require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($actor);
}
echo 'Fixture '.$mode.' completed';
