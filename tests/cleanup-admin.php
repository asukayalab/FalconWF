<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
require_once ABSPATH.'wp-admin/includes/user.php';
foreach($args as $id){$user=get_userdata(absint($id));if($user && str_starts_with($user->user_login,'fwf-webtest-')){wp_delete_user($user->ID);}}
echo 'Web fixtures cleaned';
