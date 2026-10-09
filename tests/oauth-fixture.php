<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
$mode=$args[0]??'';$actor=absint($args[1]??0);$user=get_userdata($actor);
if(!$user || !str_starts_with($user->user_login,'fwf-test-')){throw new RuntimeException('Disposable actor required.');}
$key='fwf_test_oauth_'.$actor;$state=get_option($key,false);
require_once ABSPATH.'wp-admin/includes/user.php';
wp_set_current_user(get_user_by('login','fwf-admin')->ID);
if($mode==='setup'){
    if($state){throw new RuntimeException('Prior OAuth fixture requires cleanup.');}
    $owner=wp_insert_user(['user_login'=>'fwf-test-oauth-'.bin2hex(random_bytes(6)),'user_pass'=>wp_generate_password(48,true),'role'=>'administrator']);if(is_wp_error($owner)){throw new RuntimeException('Fixture owner failed.');}
    $state=['owner'=>$owner,'client'=>null];add_option($key,$state,'',false);wp_set_current_user($owner);
    $client=FalconWF\AI\OAuth::addClient('ChatGPT fixture','https://chatgpt.com/connector_platform_oauth_redirect');if(is_wp_error($client)){throw new RuntimeException('Client registration failed.');}
    $state['client']=$client['client_id'];update_option($key,$state,false);
    echo wp_json_encode(['client'=>$client['client_id'],'redirect'=>$client['redirect'],'resource'=>FalconWF\AI\OAuth::resource(),'issuer'=>FalconWF\AI\OAuth::issuer(),'metadata'=>rest_url('falcon-wf/v1/oauth/resource'),'server'=>rest_url('falcon-wf/v1/oauth/server'),'token'=>rest_url('falcon-wf/v1/oauth/token'),'revoke'=>rest_url('falcon-wf/v1/oauth/revoke'),'authorize'=>admin_url('admin-post.php?action=fwf_oauth_authorize')]);
}elseif($state){
    if($mode==='code'){
        wp_set_current_user($state['owner']);$p=json_decode($args[2]??'',true);$r=FalconWF\AI\OAuth::code($p,$actor);echo wp_json_encode(is_wp_error($r)?['error'=>$r->get_error_code()]:['code'=>$r]);
    }elseif($mode==='hold-family'){
        $r=get_option('fwf_oauth_access_'.hash('sha256',$args[2]??''),false);if(!is_array($r) || $r['actor']!==$actor){throw new RuntimeException('Owned access required.');}
        $target='oauth_session_'.$r['session'];$lock=FalconWF\Packages\Lock::acquire($target);if(is_wp_error($lock)){throw new RuntimeException('Family lock failed.');}
        try{echo "FAMILY_LOCK_READY\n";flush();sleep(8);}finally{FalconWF\Packages\Lock::release($target,$lock);}
    }elseif($mode==='drop-refresh'){
        $r=get_option('fwf_oauth_access_'.hash('sha256',$args[2]??''),false);if(!is_array($r) || $r['actor']!==$actor){throw new RuntimeException('Owned access required.');}delete_option('fwf_oauth_refresh_'.$r['refresh_hash']);echo 'Owned refresh removed';
    }elseif($mode==='grant-write-failure'){
        $g=get_option('fwf_agent_'.$actor);$filter=static fn($value,$old)=>$old;add_filter('pre_update_option_fwf_agent_'.$actor,$filter,10,2);try{$result=FalconWF\AI\Policy::grant($actor,$g['uuid'],$g['scope']);}finally{remove_filter('pre_update_option_fwf_agent_'.$actor,$filter);}
        echo wp_json_encode(['error'=>is_wp_error($result)?$result->get_error_code():null]);
    }elseif($mode==='client-write-failure'){
        $filter=static fn($value,$old)=>$old;add_filter('pre_update_option_fwf_oauth_clients',$filter,10,2);try{$result=FalconWF\AI\OAuth::addClient('Rejected fixture','https://chatgpt.com/fixture');}finally{remove_filter('pre_update_option_fwf_oauth_clients',$filter);}
        echo wp_json_encode(['error'=>is_wp_error($result)?$result->get_error_code():null]);
    }elseif($mode==='rotate-grant'){$g=get_option('fwf_agent_'.$actor);FalconWF\AI\Policy::grant($actor,$g['uuid'],$g['scope']);echo 'Grant rotated';}
    elseif($mode==='expire-code' || $mode==='expire-access'){$type=$mode==='expire-code'?'code':'access';$name='fwf_oauth_'.$type.'_'.hash('sha256',$args[2]??'');$r=get_option($name,false);if(!$r || $r['actor']!==$actor){throw new RuntimeException('Owned credential required.');}$r['expires']=time()-1;update_option($name,$r,false);echo 'Credential expired';}
    elseif($mode==='demote-owner'){get_userdata($state['owner'])->set_role('editor');echo 'Owner demoted';}
    elseif($mode==='restore-owner'){get_userdata($state['owner'])->set_role('administrator');echo 'Owner restored';}
    elseif($mode==='password-revoke'){$g=get_option('fwf_agent_'.$actor);WP_Application_Passwords::delete_application_password($actor,$g['uuid']);echo 'Password revoked';}
    elseif($mode==='password-renew'){$g=get_option('fwf_agent_'.$actor);$pair=WP_Application_Passwords::create_new_application_password($actor,['name'=>'OAuth fixture renewed']);if(is_wp_error($pair)){throw new RuntimeException('Password renewal failed.');}FalconWF\AI\Policy::grant($actor,$pair[1]['uuid'],$g['scope']);echo 'Password and grant renewed';}
    elseif($mode==='remove-client'){FalconWF\AI\OAuth::removeClient($state['client']);echo 'Client removed';}
    elseif($mode==='stored'){
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT option_name,option_value,autoload FROM '.$wpdb->options.' WHERE option_name LIKE %s',$wpdb->esc_like('fwf_oauth_').'%'),ARRAY_A);$owned=[];
        foreach($rows as $row){$v=maybe_unserialize($row['option_value']);if(is_array($v) && ($v['actor']??0)===$actor){$owned[]=$row;}}
        echo wp_json_encode($owned);
    }elseif($mode==='cleanup'){
        FalconWF\AI\OAuth::removeClient($state['client']);$extra=absint($args[2]??0);$extraUser=get_userdata($extra);if($extraUser && str_starts_with($extraUser->user_login,'fwf-webtest-')){foreach(FalconWF\AI\OAuth::clients() as $id=>$c){if($c['owner']===$extra){FalconWF\AI\OAuth::removeClient($id);}}}global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT option_name,option_value FROM '.$wpdb->options.' WHERE option_name LIKE %s',$wpdb->esc_like('fwf_oauth_').'%'),ARRAY_A);
        foreach($rows as $row){$v=maybe_unserialize($row['option_value']);if(is_array($v) && ($v['actor']??0)===$actor){delete_option($row['option_name']);}}
        wp_delete_user($state['owner']);delete_option($key);echo 'Owned OAuth fixture cleaned';
    }else{throw new RuntimeException('Unknown mode.');}
}else{throw new RuntimeException('Fixture state missing.');}
