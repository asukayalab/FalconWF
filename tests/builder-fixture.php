<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
$option='fwf_builder_test_snapshot';
if(($args[0]??'')==='snapshot'){
 if(get_option($option,false)!==false){throw new RuntimeException('Previous builder test snapshot requires recovery.');}
 add_option($option,['builder'=>get_option('fwf_builder'),'contracts'=>get_option('fwf_field_contracts',[]),'modules'=>get_option('fwf_modules'),'menu'=>get_option('fwf_menu_order',[])],'',false);echo 'Snapshot saved';
}elseif(($args[0]??'')==='restore'){
 $saved=get_option($option,false);if(!$saved){throw new RuntimeException('Snapshot missing.');}
 foreach(['builder'=>'fwf_builder','contracts'=>'fwf_field_contracts','modules'=>'fwf_modules','menu'=>'fwf_menu_order'] as $key=>$target){update_option($target,$saved[$key],false);}
 update_option('fwf_rewrite_pending',true,false);delete_option($option);echo 'Snapshot restored';
}else{throw new RuntimeException('Unknown operation.');}
