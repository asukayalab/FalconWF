<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
$fixtures=[];
foreach(['administrator','editor'] as $role){
 $login='fwf-webtest-'.bin2hex(random_bytes(5));$password=wp_generate_password(48,false);
 $id=wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'role'=>$role]);
 if(is_wp_error($id)){throw new RuntimeException('Fixture creation failed.');}
 $fixtures[$role]=['id'=>$id,'login'=>$login,'password'=>$password];
}
echo wp_json_encode($fixtures);
