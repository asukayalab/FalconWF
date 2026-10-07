<?php
namespace FalconWF\Content;
/** Public listings intentionally exclude drafts and password protected content. */
final class Listing {
    public function register(): void { add_shortcode('falcon_listing',[$this,'render']); }
    public function render(array|string $attributes=[]): string {
        $a=shortcode_atts(['type'=>'fwf_project','taxonomy'=>'','term'=>'','limit'=>12],is_array($attributes)?$attributes:[],'falcon_listing');
        $type=sanitize_key($a['type']); $obj=get_post_type_object($type);
        if (!$obj || !$obj->public || !in_array($type,\FalconWF\Bootstrap::instance()->content->types(),true)) { return ''; }
        $query=['post_type'=>$type,'post_status'=>'publish','has_password'=>false,'posts_per_page'=>max(1,min(50,(int)$a['limit'])),'no_found_rows'=>true,'ignore_sticky_posts'=>true];
        if ($a['taxonomy'] || $a['term']) {
            $tax=sanitize_key($a['taxonomy']); $terms=array_values(array_unique(array_filter(array_map('sanitize_title',explode(',',(string)$a['term'])))));
            if ((!$terms && $a['term']!=='*') || count($terms)>200 || !is_object_in_taxonomy($type,$tax)) { return ''; }
            $query['tax_query']=$a['term']==='*'?[['taxonomy'=>$tax,'operator'=>'EXISTS']]:[['taxonomy'=>$tax,'field'=>'slug','terms'=>$terms,'operator'=>'IN']];
        }
        $posts=get_posts($query); if (!$posts) { return '<p class="fwf-listing-empty">Belum ada konten.</p>'; }
        $html='<div class="fwf-listing">';
        foreach ($posts as $p) {
            $values=Schema::values($p->ID,$type); $defs=Schema::custom($type);
            $cover=!empty($defs['cover_image']['public'])?(int)($values['cover_image']??0):0;
            $html.='<article><a href="'.esc_url(get_permalink($p)).'">'.($cover?wp_get_attachment_image($cover,'medium'):'').'<h3>'.esc_html(get_the_title($p)).'</h3></a><p>'.esc_html(wp_trim_words(wp_strip_all_tags($p->post_excerpt?:$p->post_content),25)).'</p></article>';
        }
        return $html.'</div>';
    }
}
