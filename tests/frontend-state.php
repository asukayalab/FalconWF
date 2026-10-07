<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
require_once ABSPATH.'wp-admin/includes/plugin.php';
$mode=$args[0]??'';
if($mode==='maintenance-on'){update_option('fwf_maintenance',true,false);}
elseif($mode==='maintenance-off'){update_option('fwf_maintenance',false,false);}
elseif($mode==='deactivate'){deactivate_plugins('falcon-wf/falcon-wf.php');}
elseif($mode==='activate'){$r=activate_plugin('falcon-wf/falcon-wf.php');if(is_wp_error($r)){throw new RuntimeException('Activation failed.');}}
elseif($mode==='identity-fixture'){
 $snapshot=['blogname'=>get_option('blogname'),'blogdescription'=>get_option('blogdescription')];update_option('fwf_frontend_test_snapshot',$snapshot,false);
 update_option('blogname','WP Site Fixture');update_option('blogdescription','WP Tagline Fixture');
}elseif($mode==='identity-restore'){
 $snapshot=get_option('fwf_frontend_test_snapshot',[]);foreach($snapshot as $key=>$value){update_option($key,$value);}delete_option('fwf_frontend_test_snapshot');
}else{throw new RuntimeException('Unknown fixture state.');}
echo 'Fixture state changed';
