<?php
if (wp_get_environment_type()!=='local') { throw new RuntimeException('Demo seeding only allowed locally.'); }
$app=FalconWF\Bootstrap::instance(); $admin=get_user_by('login','fwf-admin'); wp_set_current_user($admin->ID);
$app->modules->setActive(['projects']); $app->modules->register();
require_once ABSPATH.'wp-admin/includes/media.php';require_once ABSPATH.'wp-admin/includes/image.php';require_once ABSPATH.'wp-admin/includes/file.php';
$root='/fwf-examples/content'; $demo=json_decode(file_get_contents($root.'/demo.json'),true,512,JSON_THROW_ON_ERROR);
$ids=[]; $images=[]; $result=[];
foreach ($demo['items'] as $item) {
    if ($item['type']!=='fwf_project') { continue; }
    $existing=get_posts(['post_type'=>$item['type'],'post_status'=>'any','meta_key'=>'_fwf_demo_key','meta_value'=>$item['key'],'posts_per_page'=>1]);
    if ($existing) { $ids[$item['key']]=$existing[0]->ID; $result[]=['id'=>$existing[0]->ID,'url'=>get_permalink($existing[0]),'title'=>$existing[0]->post_title,'preserved'=>true]; continue; }
    if (!isset($images[$item['asset']])) {
        $imagePosts=get_posts(['post_type'=>'attachment','post_status'=>'inherit','meta_key'=>'_fwf_demo_asset','meta_value'=>$item['asset'],'posts_per_page'=>1]);
        if ($imagePosts) { $images[$item['asset']]=$imagePosts[0]->ID; }
        else {
            $temp=wp_tempnam($item['asset']);copy($root.'/assets/'.$item['asset'],$temp);
            $image=media_handle_sideload(['name'=>$item['asset'],'tmp_name'=>$temp],0,'Ilustrasi AI untuk konten demo Falcon WF');
            if(is_wp_error($image)){@unlink($temp);throw new RuntimeException($image->get_error_message());}
            update_post_meta($image,'_fwf_demo_asset',$item['asset']);update_post_meta($image,'_wp_attachment_image_alt',$item['asset']==='community-garden.png'?'Ilustrasi AI kebun komunitas dan paviliun belajar fiktif':'Ilustrasi AI meja kerja berisi sketsa, buku catatan dan tanaman');$images[$item['asset']]=$image;
        }
    }
    $fields=$item['fields'];$fields['cover_image']=$images[$item['asset']];
    if(isset($item['relation'])){$fields['related_project']=$ids[$item['relation']];}
    $created=$app->content->create($item['type'],$fields);if(is_wp_error($created)){throw new RuntimeException($created->get_error_message());}
    $id=$created['id'];update_post_meta($id,'_fwf_demo_key',$item['key']);set_post_thumbnail($id,$images[$item['asset']]);
    wp_update_post(['ID'=>$id,'post_status'=>'publish']);$ids[$item['key']]=$id;$result[]=['id'=>$id,'url'=>get_permalink($id),'title'=>$fields['title']];
}
$term=term_exists('bangunan','fwf_project_cat');
if (!$term) { $term=wp_insert_term('Bangunan','fwf_project_cat',['slug'=>'bangunan']); }
if (is_wp_error($term)) { throw new RuntimeException($term->get_error_message()); }
foreach ($ids as $id) { if (!has_term((int)$term['term_id'],'fwf_project_cat',$id)) { wp_set_object_terms($id,[(int)$term['term_id']],'fwf_project_cat',true); } }
$pages=get_posts(['post_type'=>'page','post_status'=>'any','meta_key'=>'_fwf_demo_key','meta_value'=>'karya-bangunan','posts_per_page'=>1]);
if (!$pages) {
    $page=wp_insert_post(['post_type'=>'page','post_title'=>'Karya Bangunan','post_name'=>'karya-bangunan','post_content'=>'<!-- wp:paragraph --><p>Contoh listing Project dalam kategori Bangunan. Seluruh karya berikut adalah konten demo fiktif.</p><!-- /wp:paragraph --><!-- wp:shortcode -->[falcon_listing type="fwf_project" taxonomy="fwf_project_cat" term="bangunan" limit="12"]<!-- /wp:shortcode -->','post_status'=>'publish'],true);
    if (is_wp_error($page)) { throw new RuntimeException($page->get_error_message()); } update_post_meta($page,'_fwf_demo_key','karya-bangunan');
} else { $page=$pages[0]->ID; }
$result[]=['id'=>$page,'url'=>get_permalink($page),'title'=>'Karya Bangunan'];
echo wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
