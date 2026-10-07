<?php
// Only execute against our isolated local environment.
if (wp_get_environment_type() !== 'local') { throw new RuntimeException('Tests require local WordPress.'); }
use FalconWF\Bootstrap;
use FalconWF\Installer\ThemeInstaller;
use FalconWF\Packages\Verifier;
use FalconWF\Packages\Lock;
use FalconWF\Audit\Logger;
$app=Bootstrap::instance();
global $count; $count=0;
function fwf_assert($condition,$label) { global $count; if (!$condition) { throw new RuntimeException('FAIL: '.$label); } $count++; echo 'PASS: '.$label."\n"; }
$admin=get_user_by('login','fwf-admin'); wp_set_current_user($admin->ID);
fwf_assert($app!==null,'boot from installed ZIP');
$identityBefore=FalconWF\Settings::identity();
update_option('fwf_schema_version',0,false);
fwf_assert(FalconWF\Migrations\Runner::run()===true && FalconWF\Migrations\Runner::run()===true,'initial migration retry idempotent');
fwf_assert(FalconWF\Settings::identity()===$identityBefore,'migration preserves existing settings');
$registry=new FalconWF\Modules\Registry();
$registry->add('fixture-a',['label'=>'A','type'=>'fwf_fixture_a','dependencies'=>['fixture-b']]);
$registry->add('fixture-b',['label'=>'B','type'=>'fwf_fixture_b','dependencies'=>['fixture-a']]);
fwf_assert(is_wp_error($registry->setActive(['fixture-a'])),'missing dependency denied');
fwf_assert(is_wp_error($registry->setActive(['fixture-a','fixture-b'])),'cyclic dependency denied');
fwf_assert(Bootstrap::boot(WP_PLUGIN_DIR.'/falcon-wf/falcon-wf.php')===$app,'boot idempotent');
$themeBefore=get_stylesheet();
$installer=new ThemeInstaller(WP_PLUGIN_DIR.'/falcon-wf');
fwf_assert($installer->install()===true,'install bundled FT');
fwf_assert(get_stylesheet()===$themeBefore,'install preserves active theme');
fwf_assert($installer->install()===true,'retry same version idempotent');
fwf_assert(wp_get_theme('falcon-theme')->exists(),'FT is a real WordPress theme');
$lock=Lock::acquire('fixture'); fwf_assert(is_string($lock),'lock acquired');
fwf_assert(is_wp_error(Lock::acquire('fixture')),'concurrent writer denied');
Lock::release('fixture','wrong-owner');fwf_assert(is_wp_error(Lock::acquire('fixture')),'wrong owner cannot release lock');
Lock::release('fixture',$lock);
fwf_assert(is_wp_error(FalconWF\Settings::saveIdentity(['name'=>str_repeat('a',151)])),'invalid identity rejected');
fwf_assert(FalconWF\Settings::saveIdentity(['name'=>'Falcon WF Local','contact'=>''])===true,'identity save');
fwf_assert(is_wp_error($app->modules->setActive(['unknown'])),'unknown module denied');
$app->modules->setActive(['projects']);$app->modules->register();
fwf_assert(post_type_exists('fwf_project'),'active module registers CPT');
$created=$app->content->create('fwf_project',['title'=>'Fixture project','body'=>"Safe body <script>alert(1)</script>", 'summary'=>'Summary']);
fwf_assert(!is_wp_error($created),'create draft through shared schema');
$id=$created['id'];
fwf_assert($created['status']==='draft' && !str_contains($created['fields']['body'],'<script>'),'draft only and sanitized HTML');
fwf_assert(is_wp_error($app->content->edit($id,'stale',['title'=>'Bad'])),'stale revision denied');
fwf_assert(is_wp_error($app->content->edit($id,$created['revision'],['post_status'=>'publish'])),'unknown field/publish injection denied');
$edited=$app->content->edit($id,$created['revision'],['title'=>'Changed fixture']);
fwf_assert(!is_wp_error($edited) && $edited['revision']!==$created['revision'],'revision guard and draft edit');
fwf_assert(count(wp_get_post_revisions($id))>=1,'native content revisions exist');
$app->modules->setActive([]);fwf_assert(get_post($id)!==null,'module disable retains data');
$app->modules->setActive(['projects']);
$pub=wp_insert_post(['post_type'=>'post','post_title'=>'Public fixture','post_content'=>'Public','post_status'=>'publish']);
$protected=wp_insert_post(['post_type'=>'post','post_title'=>'Protected fixture','post_content'=>'Secret','post_status'=>'publish','post_password'=>'fixture']);
fwf_assert(is_wp_error($app->content->get($protected)),'password-protected content excluded');
$editorName='fwf-editor-'.wp_generate_password(8,false);
$editor=wp_insert_user(['user_login'=>$editorName,'user_pass'=>wp_generate_password(40,true),'role'=>'editor']);
wp_set_current_user($editor);
fwf_assert(!current_user_can('fwf_manage_system'),'editor lacks system capability');
fwf_assert(is_wp_error($installer->install()),'editor cannot install FT through service');
fwf_assert(is_wp_error($installer->activate()),'editor cannot switch FT through service');
wp_set_current_user(0);
$request=new WP_REST_Request('GET','/falcon-wf/v1/content/'.$id);
$server=rest_get_server();
fwf_assert($server->dispatch($request)->get_status()===401,'public draft REST request denied');
fwf_assert($server->dispatch(new WP_REST_Request('GET','/falcon-wf/v1/content/'.$pub))->get_status()===200,'public published REST request allowed');
fwf_assert($server->dispatch(new WP_REST_Request('GET','/falcon-wf/v1/health'))->get_status()===401,'public health details denied');
wp_set_current_user($admin->ID);
$fixture=WP_PLUGIN_DIR.'/falcon-wf/bundles/falcon-theme.zip';$bytes=file_get_contents($fixture);
try { file_put_contents($fixture,'corrupt');fwf_assert(is_wp_error(Verifier::bundle(WP_PLUGIN_DIR.'/falcon-wf')),'corrupt bundle denied before installation'); } finally { file_put_contents($fixture,$bytes); }
$style=get_theme_root().'/falcon-theme/style.css';$original=file_get_contents($style);
try {
 file_put_contents($style,preg_replace('/^Version:.*$/m','Version: 99.0.0',$original));wp_clean_themes_cache();
 fwf_assert($installer->install()===true,'newer installed FT is not downgraded');
 fwf_assert(str_contains(file_get_contents($style),'99.0.0'),'newer FT bytes preserved');
 file_put_contents($style,preg_replace('/^Version:.*$/m','Version: 0.0.1',$original));wp_clean_themes_cache();
 fwf_assert(is_wp_error($installer->install()),'older FT not silently overwritten');
} finally {file_put_contents($style,$original);wp_clean_themes_cache();}
fwf_assert($installer->activate()===true,'explicit authorized theme activation');
fwf_assert(get_stylesheet()==='falcon-theme','parent theme active');
$logger=Logger::write('fixture','succeeded',$id);
fwf_assert(is_string($logger) && count(Logger::recent())>0,'persistent allowlisted audit');
$guard=FalconWF\AI\RequestGuard::run('fixture','fixture-key-123',['title'=>'a'],fn()=>['id'=>123]);
fwf_assert(!is_wp_error($guard),'idempotency first request');
fwf_assert(FalconWF\AI\RequestGuard::run('fixture','fixture-key-123',['title'=>'a'],fn()=>['id'=>456])['id']===123,'retry reuses result');
fwf_assert(is_wp_error(FalconWF\AI\RequestGuard::run('fixture','fixture-key-123',['title'=>'b'],fn()=>['id'=>456])),'key reuse with different payload denied');
fwf_assert(is_wp_error((new FalconWF\AI\ProviderClient($app->content))->suggest($id,'title','Rewrite')),'missing provider configuration honestly denied');
FalconWF\Lifecycle::deactivate();fwf_assert(get_post($id)!==null,'deactivation retains content');
// Fixture cleanup only; installed framework remains for manual review.
foreach ([$id,$pub,$protected] as $object) { wp_delete_post($object,true); }
require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($editor);
$app->modules->setActive(['projects','publications','learning']);
echo "Runtime assertions passed: $count; WP {$GLOBALS['wp_version']}; PHP ".PHP_VERSION."\n";
