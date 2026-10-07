<?php
if(wp_get_environment_type()!=='local' || !get_option('fwf_test_site_state',false)) { throw new RuntimeException('State guard required.'); }
update_option('blogname','Intentional failed test fixture');
update_option('fwf_identity',['name'=>'Intentional failed fixture','contact'=>''],false);
update_option('fwf_modules',[],false);
update_option('fwf_maintenance',true,false);
delete_option('fwf_menu_order');
throw new RuntimeException('Intentional child failure to verify harness restoration.');
