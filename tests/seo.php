<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Local only.'); }
use FalconTheme\Seo as S;
$count=0;$ids=[];$old=get_option('fwf_seo',null);$public=get_option('blog_public');$user=get_current_user_id();
$ok=static function($pass,$name) use (&$count) { if (!$pass) { throw new RuntimeException('FAIL: '.$name); } $count++;echo "PASS: $name\n"; };
$render=static function() { ob_start();S::render();return ob_get_clean(); };
try {
 $admin=get_users(['role'=>'administrator','number'=>1]);wp_set_current_user($admin[0]->ID);
 $ok(post_type_supports('page','excerpt'),'Pages expose native excerpt for human metadata input');
 delete_option('fwf_seo');update_option('blog_public',1);
 $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>'SEO fixture','post_content'=>'SECRET BODY','post_excerpt'=>'Visible summary & detail.']);$ids[]=$id;
 $query=static function($id) { global $wp_query;$wp_query=new WP_Query(['page_id'=>$id]); };
 $query($id);$ok($render()==='','additional metadata disabled by default');
 $revision=S::revision();$ok(FalconWF\Settings::saveSeo('falcon',$revision)===true,'human opt-in persists');
 $ok(is_wp_error(FalconWF\Settings::saveSeo('external',$revision)),'stale settings rejected');
 $ok(is_wp_error(FalconWF\Settings::saveSeo(['falcon'],S::revision())),'invalid mode rejected');
 $owner=FalconWF\Packages\Lock::acquire('seo');try { $ok(is_wp_error(FalconWF\Settings::saveSeo('external',S::revision())),'concurrent SEO write refused'); } finally { FalconWF\Packages\Lock::release('seo',$owner); }
 $html=$render();$ok(substr_count($html,'name="description"')===1 && str_contains($html,'Visible summary &amp; detail.') && !str_contains($html,'SECRET BODY'),'one explicit public excerpt without body leakage');
 $ok(!str_contains($html,'rel="canonical"') && !str_contains($html,'<title') && !str_contains($html,'name="robots"'),'singular native canonical/title/robots left to WordPress');
 preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$html,$match);$schema=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);
 $ok($schema['@graph'][0]['@type']==='WebPage' && $schema['@graph'][0]['url']===get_permalink($id),'schema describes actual public page');
 $delegate=static fn()=>true;add_filter('fwf_seo_external_owner',$delegate);$ok($render()==='','external metadata owner suppresses all Falcon output');remove_filter('fwf_seo_external_owner',$delegate);
 foreach (['draft','private'] as $status) { wp_update_post(['ID'=>$id,'post_status'=>$status]);$query($id);$ok($render()==='',$status.' has no promotional metadata'); }
 wp_update_post(['ID'=>$id,'post_status'=>'publish','post_password'=>'fixture']);$query($id);$ok($render()==='','password page never exposes metadata even to administrator');
 wp_update_post(['ID'=>$id,'post_password'=>'','post_excerpt'=>'']);$query($id);$ok(!str_contains($render(),'name="description"'),'missing excerpt does not copy arbitrary body');
 update_option('blog_public',0);$ok($render()==='','private site suppresses Falcon metadata');update_option('blog_public',1);
 global $wp_query;$wp_query->is_preview=true;$ok($render()==='','preview suppressed');$wp_query->is_preview=false;$wp_query->is_feed=true;$ok($render()==='','feed suppressed');$wp_query->is_feed=false;$wp_query->is_search=true;$ok($render()==='','search suppressed');$wp_query->is_search=false;$wp_query->is_404=true;$ok($render()==='','404 suppressed');$wp_query->is_404=false;$wp_query->is_paged=true;$ok($render()==='','pagination suppressed');
 $query($id);wp_update_post(['ID'=>$id,'post_title'=>'</script><b>Safe</b>','post_excerpt'=>'Text </script><b>public</b>']);$query($id);$ok(substr_count($render(),'</script>')===1 && !str_contains($render(),'<b>'),'HTML and script boundaries escaped');
 wp_set_current_user(0);$ok(is_wp_error(FalconWF\Settings::saveSeo('external',S::revision())),'unauthorized direct persistence denied');wp_set_current_user($admin[0]->ID);
 $ok(FalconWF\Settings::saveSeo('external',S::revision())===true && $render()==='','disable preserves content while removing output');
 update_option('fwf_seo',['mode'=>['falcon']]);$ok($render()==='','corrupt option fails closed');
 $ok(function_exists('wp_sitemaps_get_server') && has_action('wp_head','rel_canonical')!==false,'native sitemap and canonical owners remain registered');
 update_option('fwf_seo',['mode'=>'falcon']);$query($id);if (!defined('WPSEO_VERSION')) { define('WPSEO_VERSION','fixture-only'); }$ok(S::delegated() && $render()==='','known SEO plugin signal delegates output without installing plugin');
 echo "SEO contract checks passed: $count.\n";
} finally {
 foreach ($ids as $id) { wp_delete_post($id,true); }
 $old===null?delete_option('fwf_seo'):update_option('fwf_seo',$old,false);update_option('blog_public',$public);wp_set_current_user($user);
}
