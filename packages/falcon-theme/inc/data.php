<?php
namespace FalconTheme;
function identity(): array {
    return class_exists('FalconWF\\Settings')?\FalconWF\Settings::identity():['name'=>'','contact'=>''];
}
function siteName(): string { return get_bloginfo('name'); }
function contentDetails(int $id): void {
    if (!class_exists('FalconWF\\Content\\Schema')) { return; }
    $type=get_post_type($id); $definitions=\FalconWF\Content\Schema::custom($type); $values=\FalconWF\Content\Schema::values($id,$type);
    if (!$definitions) { return; }
    if (($definitions['cover_image']['public']??true) && ($values['cover_image']??0)) { echo wp_get_attachment_image($values['cover_image'],'large',false,['class'=>'entry-cover']); }
    echo '<dl class="content-details">';
    foreach ($definitions as $key=>$d) {
        if (array_key_exists('public',$d) && !$d['public']) { continue; }
        $value=$values[$key]; if ($key==='cover_image' || $value==='' || $value===0) { continue; }
        if ($d['kind']==='relationship') {
            $related=get_post((int)$value);
            if (!$related || $related->post_password!=='' || ($related->post_status!=='publish' && !current_user_can('edit_post',$related->ID))) { continue; }
            $text='<a href="'.esc_url(get_permalink($related)).'">'.esc_html($related->post_title).'</a>';
        } elseif ($d['kind']==='url') { $text='<a href="'.esc_url($value,['http','https']).'">'.esc_html($value).'</a>'; }
        else { $text=esc_html((string)($d['choices'][$value]??$value)); }
        echo '<dt>'.esc_html($d['label']).'</dt><dd>'.$text.'</dd>';
    }
    echo '</dl>';
}
