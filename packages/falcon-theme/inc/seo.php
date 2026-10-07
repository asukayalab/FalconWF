<?php
namespace FalconTheme;
/** Supplements native WordPress SEO; never replaces title, robots or sitemap ownership. */
final class Seo {
    public static function config(): array {
        $value=get_option('fwf_seo',[]);
        return is_array($value) && ($value['mode']??'')==='falcon'?['mode'=>'falcon']:['mode'=>'external'];
    }
    public static function revision(): string { return hash('sha256',wp_json_encode(self::config())); }
    public static function delegated(): bool {
        $known=defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION') || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION') || defined('THE_SEO_FRAMEWORK_VERSION');
        return (bool)apply_filters('fwf_seo_external_owner',$known);
    }
    public static function document(): array {
        if (self::config()['mode']!=='falcon' || self::delegated() || (string)get_option('blog_public')!=='1' || is_admin() || is_feed() || is_preview() || is_search() || is_404() || is_paged()) { return []; }
        // The bundled coming-soon page remains noindex and never gains promotional metadata.
        if (is_front_page() && locate_template('front-page.php')===get_template_directory().'/front-page.php') { return []; }
        $description='';
        if (is_singular()) {
            $post=get_queried_object();
            if (!$post instanceof \WP_Post || $post->post_status!=='publish' || $post->post_password!=='' || !is_post_type_viewable($post->post_type)) { return []; }
            $url=wp_get_canonical_url($post);
            $title=$post->post_title;
            // Explicit public excerpt only; do not execute shortcodes or copy private custom fields.
            $description=$post->post_excerpt;
        } elseif (is_front_page() && is_home()) {
            $url=home_url('/');$title=get_bloginfo('name');$description=get_bloginfo('description');
        } else { return []; }
        if (!$url) { return []; }
        $clean=static fn($text)=>trim(preg_replace('/\s+/u',' ',wp_strip_all_tags((string)$text))??'');
        return ['url'=>$url,'title'=>$clean($title),'description'=>$clean($description)];
    }
    public static function render(): void {
        $data=self::document(); if (!$data) { return; }
        if ($data['description']!=='') {
            echo '<meta name="description" content="'.esc_attr($data['description']).'">'."\n";
            echo '<meta property="og:description" content="'.esc_attr($data['description']).'">'."\n";
        }
        foreach (['type'=>'website','title'=>$data['title'],'url'=>$data['url'],'site_name'=>get_bloginfo('name')] as $key=>$value) { echo '<meta property="og:'.esc_attr($key).'" content="'.esc_attr($value).'">'."\n"; }
        // WordPress already emits the singular canonical. Only supplement latest-posts home.
        if (!is_singular()) { echo '<link rel="canonical" href="'.esc_url($data['url']).'">'."\n"; }
        $page=['@type'=>'WebPage','@id'=>$data['url'].'#webpage','url'=>$data['url'],'name'=>$data['title']];
        if ($data['description']!=='') { $page['description']=$data['description']; }
        $graph=[$page];
        if (is_front_page()) { $graph[]=['@type'=>'WebSite','@id'=>home_url('/').'#website','url'=>home_url('/'),'name'=>get_bloginfo('name')]; }
        echo '<script type="application/ld+json">'.wp_json_encode(['@context'=>'https://schema.org','@graph'=>$graph],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES).'</script>'."\n";
    }
}
add_action('wp_head',[Seo::class,'render'],20);

// Expose the same native public excerpt on Pages; no parallel SEO post-meta editor.
add_action('init',static function () { add_post_type_support('page','excerpt'); });
