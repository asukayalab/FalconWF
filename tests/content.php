<?php
if(wp_get_environment_type()!=='local'){throw new RuntimeException('Local only.');}
use FalconWF\Content\Schema;
use FalconWF\Packages\Lock;
$app=FalconWF\Bootstrap::instance();wp_set_current_user(get_user_by('login','fwf-admin')->ID);global $count;$count=0;$ids=[];
function content_ok($x,$label){global $count;if(!$x){throw new RuntimeException('FAIL: '.$label);}$count++;echo 'PASS: '.$label."\n";}
try {
 $app->modules->setActive(['projects','publications','learning']);$app->modules->register();
 $p=$app->content->create('fwf_project',['title'=>'Structured fixture','location'=>'<b>Demo</b>','project_year'=>2026,'project_stage'=>'concept']);
 content_ok(!is_wp_error($p),'typed custom fields create');$id=$p['id'];$ids[]=$id;
 content_ok($p['fields']['project_year']===2026 && $p['fields']['location']==='Demo','integer roundtrip and text sanitization');
 content_ok(is_wp_error(Schema::validate(['project_year'=>'2026'],'fwf_project')),'API rejects string masquerading as integer');
 content_ok(is_wp_error(Schema::validate(['project_stage'=>'invalid'],'fwf_project')),'select enum enforced');
 content_ok(is_wp_error(Schema::validate(['publication_date'=>'2026-02-30'],'fwf_publication')),'invalid calendar date refused');
 content_ok(is_wp_error(Schema::validate(['publication_url'=>'javascript:alert(1)'],'fwf_publication')),'unsafe URL refused');
 content_ok(is_wp_error(Schema::validate(['publication_url'=>'https://user:secret@example.com'],'fwf_publication')),'URL credential refused');
 content_ok(is_wp_error(Schema::validate(['location'=>'x'],'post')),'cross-type field refused');
 content_ok(is_wp_error(Schema::validate(['cover_image'=>99999999],'fwf_project')),'missing media refused');
 content_ok(is_wp_error(Schema::validate(['related_project'=>$id],'fwf_project')),'relation unavailable on wrong type');
 $pub=$app->content->create('fwf_publication',['title'=>'Related fixture','related_project'=>$id,'publication_date'=>'2026-10-07']);$ids[]=$pub['id'];
 content_ok(!is_wp_error($pub) && $pub['fields']['related_project']===$id,'authorized typed relation persists');
 $r=$app->content->edit($id,$p['revision'],['location'=>'Changed']);
 content_ok(!is_wp_error($r) && $r['revision']!==$p['revision'],'metadata-only edit changes conflict hash');
 content_ok(is_wp_error($app->content->edit($id,$p['revision'],['location'=>'Stale'])),'metadata-only stale write refused');
 $revisionIds=wp_get_post_revisions($id);$old=0;foreach($revisionIds as $rev){if(get_post_meta($rev->ID,'_fwf_location',true)==='Demo'){$old=$rev->ID;break;}}
 content_ok($old>0,'native revision contains custom metadata');
 wp_restore_post_revision($old);$restored=$app->content->get($id);
 content_ok($restored['fields']['location']==='Demo','native revision restore restores metadata');
 $before=$restored['revision'];$bad=$app->content->edit($id,$before,['location'=>'Should not persist','project_year'=>9999]);
 content_ok(is_wp_error($bad) && $app->content->get($id)['revision']===$before,'invalid multi-field payload writes nothing');
 $owner=Lock::acquire('content_'.$id);$blocked=$app->content->edit($id,$before,['location'=>'Concurrent']);Lock::release('content_'.$id,$owner);
 content_ok(is_wp_error($blocked) && $app->content->get($id)['revision']===$before,'concurrent custom writer refused');
 wp_update_post(['ID'=>$id,'post_status'=>'publish']);$published=$app->content->get($id);
 content_ok(is_wp_error($app->content->edit($id,$published['revision'],['location'=>'Agent'])),'default mutation cannot edit published metadata');
 $human=$app->content->edit($id,$published['revision'],['location'=>'Human edit'],false);
 content_ok(!is_wp_error($human) && $human['status']==='publish','explicit human fields edit preserves publication state');
 $failMeta=static function($check,$object,$key){return $key==='_fwf_location'?false:$check;};
 add_filter('update_post_metadata',$failMeta,10,3);
 $failure=$app->content->edit($id,$human['revision'],['location'=>'DB failure'],false);remove_filter('update_post_metadata',$failMeta,10);
 content_ok(is_wp_error($failure) && $failure->get_error_code()==='FWF_INTERNAL' && $failure->get_error_data()['object_id']===$id,'metadata persistence failure cannot report success');
 $registered=get_registered_meta_keys('post','fwf_project');content_ok($registered['_fwf_location']['revisions_enabled'] && !$registered['_fwf_location']['show_in_rest'],'revisioned metadata hidden from native REST');
 $fsFilter=static fn()=>'ftpext';add_filter('filesystem_method',$fsFilter,PHP_INT_MAX);
 $readiness=(new FalconWF\Installer\ThemeInstaller(WP_PLUGIN_DIR.'/falcon-wf'))->readiness();remove_filter('filesystem_method',$fsFilter,PHP_INT_MAX);
 content_ok(is_wp_error($readiness) && $readiness->get_error_code()==='FWF_FILESYSTEM','credential-based filesystem directed to native installer');
 $installer=new FalconWF\Installer\ThemeInstaller(WP_PLUGIN_DIR.'/falcon-wf');
 content_ok(!is_wp_error($installer->readiness()),'local preflight bundle/permissions/disk passes');
 $manifestPath=WP_PLUGIN_DIR.'/falcon-wf/installer-manifest.json';$bundlePath=WP_PLUGIN_DIR.'/falcon-wf/bundles/falcon-theme.zip';$manifestBytes=file_get_contents($manifestPath);$zipBytes=file_get_contents($bundlePath);
 try {
  $z=new ZipArchive();$temp=wp_tempnam('attack.zip');$z->open($temp,ZipArchive::OVERWRITE);$z->addFromString('falcon-theme/../escape.php','<?php');$z->addFromString('falcon-theme/style.css',"Version: 0.1.0-alpha.2\n");$z->addFromString('falcon-theme/index.php','<?php');$z->close();
  copy($temp,$bundlePath);$manifest=json_decode($manifestBytes,true);$manifest['theme']['sha256']=hash_file('sha256',$bundlePath);file_put_contents($manifestPath,wp_json_encode($manifest));
  content_ok(is_wp_error(FalconWF\Packages\Verifier::bundle(WP_PLUGIN_DIR.'/falcon-wf')),'hash-valid traversal ZIP refused before extraction');unlink($temp);
 } finally {file_put_contents($manifestPath,$manifestBytes);file_put_contents($bundlePath,$zipBytes);}
 echo "Structured content/preflight assertions passed: $count\n";
}finally{foreach($ids as $id){wp_delete_post($id,true);}}
