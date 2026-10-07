<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if(!defined('DISALLOW_FILE_MODS')){define('DISALLOW_FILE_MODS',true);}
$installer=new FalconWF\Installer\ThemeInstaller(WP_PLUGIN_DIR.'/falcon-wf');
if(!is_wp_error($installer->install()) || !is_wp_error((new FalconWF\Updates\UpdateManager())->update('falcon-theme',true))){throw new RuntimeException('Immutable environment allowed code writes.');}
echo 'PASS: immutable environment refuses dashboard code writes' . "\n";

$connection=get_option('fwf_project_connection',null);
try {
 update_option('fwf_project_connection',['repo'=>'fixture/client-design','project_id'=>'project-fixture','theme_id'=>'falcon-project-fixture','tag'=>''],false);
 $denied=(new FalconWF\Updates\ProjectManager())->apply(true);
 if(!is_wp_error($denied) || !in_array($denied->get_error_code(),['FWF_FILESYSTEM','FWF_PERMISSION'],true)){throw new RuntimeException('Immutable project boundary failed.');}
 echo "PASS: immutable project install refused before transport/file writes\n";
} finally { $connection===null?delete_option('fwf_project_connection'):update_option('fwf_project_connection',$connection,false); }
