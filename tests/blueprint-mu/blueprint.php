<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only');}
$owner=getenv('FWF_BLUEPRINT_HTTP_OWNER');$state=get_option('fwf_test_blueprint_http');
if(!is_string($owner) || !is_array($state) || ($state['owner']??null)!==$owner){throw new RuntimeException('Blueprint HTTP owner mismatch');}
// Request-only theme context: never switch the persisted active theme or homepage.
add_filter('option_stylesheet',static fn()=>$state['theme']);
add_filter('option_template',static fn()=>'falcon-theme');
