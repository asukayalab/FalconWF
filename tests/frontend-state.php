<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
require_once ABSPATH.'wp-admin/includes/plugin.php';
$mode=$args[0]??'';
if($mode==='maintenance-on'){update_option('fwf_maintenance',true,false);}
elseif($mode==='maintenance-off'){update_option('fwf_maintenance',false,false);}
elseif($mode==='deactivate'){deactivate_plugins('falcon-wf/falcon-wf.php');}
elseif($mode==='activate'){$r=activate_plugin('falcon-wf/falcon-wf.php');if(is_wp_error($r)){throw new RuntimeException('Activation failed.');}}
elseif($mode==='identity-fixture'){
 $snapshot=[];foreach(['blogname','blogdescription','fwf_maintenance','active_plugins'] as $key){$snapshot[$key]=['exists'=>get_option($key,null)!==null,'value'=>get_option($key,null)];}
 if(!add_option('fwf_frontend_test_snapshot',$snapshot,'',false)){throw new RuntimeException('Previous frontend snapshot requires recovery.');}
 update_option('blogname','WP Site Fixture');update_option('blogdescription','WP Tagline Fixture');
}elseif($mode==='identity-restore'){
 $snapshot=get_option('fwf_frontend_test_snapshot',null);if($snapshot===null){throw new RuntimeException('Frontend snapshot missing.');}
 foreach($snapshot as $key=>$state){$state['exists']?update_option($key,$state['value']):delete_option($key);}delete_option('fwf_frontend_test_snapshot');
}else{throw new RuntimeException('Unknown fixture state.');}
echo 'Fixture state changed';
