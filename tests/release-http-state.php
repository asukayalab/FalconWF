<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local harness only');}
$key='fwf_release_http_state';$mode=$args[0]??'';$owner=$args[1]??'';
if(!preg_match('/^[a-f0-9-]{36}$/D',$owner)){throw new RuntimeException('Owner required');}
if($mode==='capture'){
 $saved=[];foreach(['fwf_repo','fwf_update_channel','fwf_update_tag','fwf_release_candidate','fwf_project_connection','fwf_project_candidate','fwf_update_observations'] as $name){$saved[$name]=get_option($name,null);}
 if(!add_option($key,['owner'=>$owner,'saved'=>$saved],'',false)){throw new RuntimeException('Existing fixture state preserved');}
 echo "Captured release HTTP state\n";
}elseif($mode==='cleanup'){
 $state=get_option($key);if(($state['owner']??'')!==$owner){throw new RuntimeException('Owner mismatch');}
 foreach($state['saved'] as $name=>$value){$value===null?delete_option($name):update_option($name,$value,false);if(get_option($name,null)!==$value){throw new RuntimeException('State restore failed');}}
 delete_option($key);echo "Release HTTP state restored and verified\n";
}else{throw new RuntimeException('Unknown mode');}
