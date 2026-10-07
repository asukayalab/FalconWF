<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if(!defined('DISALLOW_FILE_MODS')){define('DISALLOW_FILE_MODS',true);}
$installer=new FalconWF\Installer\ThemeInstaller(WP_PLUGIN_DIR.'/falcon-wf');
if(!is_wp_error($installer->install()) || !is_wp_error((new FalconWF\Updates\UpdateManager())->update('falcon-theme',true))){throw new RuntimeException('Immutable environment allowed code writes.');}
echo 'PASS: immutable environment refuses dashboard code writes' . "\n";
