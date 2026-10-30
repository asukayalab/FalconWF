<?php
namespace FalconWF\Admin;

/** Native WordPress template assignment; no parallel mapping or content writer. */
final class PageDesign {
    public static function revision(int $id): string {
        $post=get_post($id);
        return hash('sha256',wp_json_encode([$post?->post_modified_gmt,$post?->post_status,$post?->post_content,$post?->post_title,$post?->post_name,$post?->post_parent,$post?->post_excerpt,get_post_meta($id,'_wp_page_template',true),get_stylesheet(),wp_get_theme()->get('Version'),self::templates($id)]));
    }
    public static function templates(int $id): array {
        return wp_get_theme()->get_page_templates(get_post($id),'page');
    }
    public static function save(mixed $id,mixed $template,mixed $revision,mixed $confirm): true|\WP_Error {
        if (!current_user_can('fwf_manage_system') || !is_string($id) || !ctype_digit($id) || (int)$id<1 || !current_user_can('edit_post',(int)$id)) {
            return new \WP_Error('FWF_PERMISSION','Tidak diizinkan mengubah desain halaman ini.');
        }
        $id=(int)$id;$post=get_post($id);
        if (!$post || $post->post_type!=='page' || in_array($post->post_status,['trash','auto-draft'],true)) { return new \WP_Error('FWF_VALIDATION','Page tidak tersedia.'); }
        if (!is_string($template) || ($template!=='default' && (validate_file($template)!==0 || !array_key_exists($template,self::templates($id)) || !locate_template([$template],false,false)))) { return new \WP_Error('FWF_VALIDATION','Template tidak tersedia pada theme aktif. Muat ulang daftar.'); }
        if (!is_string($revision) || !hash_equals(self::revision($id),$revision)) { return new \WP_Error('FWF_CONFLICT','Halaman atau theme berubah. Muat ulang dan review pilihan.'); }
        if ($confirm!=='yes') { return new \WP_Error('FWF_CONFIRM','Konfirmasi perubahan tampilan diperlukan.'); }
        if (wp_check_post_lock($id)) { return new \WP_Error('FWF_CONFLICT','Halaman sedang diedit pengguna lain. Coba lagi setelah selesai.'); }
        $lease=\FalconWF\Packages\Lock::acquire('page_design_'.$id);
        if (is_wp_error($lease)) { return $lease; }
        try {
            if (!hash_equals(self::revision($id),$revision)) { return new \WP_Error('FWF_CONFLICT','Halaman berubah. Muat ulang dan review.'); }
            $old=get_post_meta($id,'_wp_page_template',true);
            if ($old===$template || ($old==='' && $template==='default')) { return true; }
            // Compare existing native assignment; never write title/body/slug/status.
            $saved=$old==='' && !metadata_exists('post',$id,'_wp_page_template')
                ? add_post_meta($id,'_wp_page_template',$template,true)
                : update_post_meta($id,'_wp_page_template',$template,$old);
            if (!$saved || get_post_meta($id,'_wp_page_template',true)!==$template) { return new \WP_Error('FWF_CONFLICT','Pilihan belum tersimpan atau berubah bersamaan. Muat ulang dan periksa.'); }
            return true;
        } finally { \FalconWF\Packages\Lock::release('page_design_'.$id,$lease); }
    }
    public static function render(): void {
        $page=isset($_GET['paged']) && is_scalar($_GET['paged'])?max(1,absint($_GET['paged'])):1;
        $query=new \WP_Query(['post_type'=>'page','post_status'=>['publish','draft','pending','private','future'],'posts_per_page'=>20,'paged'=>$page,'orderby'=>'ID','order'=>'ASC']);
        echo '<p>Hubungkan Page dengan template dari <strong>theme aktif yang sudah terpasang</strong>. Pilihan ini sama dengan Template di editor Page; isi, URL dan status publikasi tetap.</p>';
        echo '<p>Theme aktif: <strong>'.esc_html(wp_get_theme()->get('Name')).'</strong>. Perubahan pada halaman terbit langsung memengaruhi tampilan. Template khusus beranda mengikuti aturan WordPress dan dapat mengesampingkan pilihan Page.</p>';
        if (!$query->posts) { echo '<p>Belum ada Page pada daftar ini. Buat halaman melalui menu Pages WordPress.</p>'; }
        foreach ($query->posts as $post) {
            $id=$post->ID;if (!current_user_can('edit_post',$id)) { continue; }
            $templates=self::templates($id);$current=get_post_meta($id,'_wp_page_template',true)?:'default';
            echo '<section class="card"><h2>'.esc_html(get_the_title($id)?:'(Tanpa judul)').'</h2><p>Status: '.esc_html($post->post_status).' · ID '.esc_html((string)$id).'</p>';
            echo '<p><code>'.esc_html(get_permalink($id)).'</code></p><p><a href="'.esc_url(get_edit_post_link($id,'raw')).'">Edit isi</a>';
            $url=$post->post_status==='publish'?get_permalink($id):get_preview_post_link($id);
            if ($url) { echo ' · <a href="'.esc_url($url).'">'.($post->post_status==='publish'?'Lihat halaman':'Preview').'</a>'; }
            echo '</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            echo '<input type="hidden" name="action" value="fwf_action"><input type="hidden" name="operation" value="save_page_design"><input type="hidden" name="screen" value="pages"><input type="hidden" name="page_id" value="'.esc_attr((string)$id).'"><input type="hidden" name="revision" value="'.esc_attr(self::revision($id)).'">';
            echo str_replace('id="_wpnonce"','id="fwf-page-nonce-'.$id.'"',wp_nonce_field('fwf_save_page_design','_wpnonce',true,false));
            echo '<input type="hidden" name="list_page" value="'.esc_attr((string)$page).'">';
            echo '<p><label for="fwf-template-'.$id.'">Template halaman</label><br><select id="fwf-template-'.$id.'" name="template"><option value="default"'.selected($current,'default',false).'>Default WordPress</option>';
            if ($current!=='default' && !isset($templates[$current])) { echo '<option value="'.esc_attr($current).'" selected disabled>Template lama tidak tersedia: '.esc_html($current).'</option>'; }
            foreach ($templates as $file=>$label) { echo '<option value="'.esc_attr($file).'"'.selected($current,$file,false).'>'.esc_html($label).'</option>'; }
            echo '</select></p>';
            if (!$templates) { echo '<p>Theme aktif belum menyediakan template Page tambahan. File PHP biasa tidak otomatis menjadi pilihan template.</p>'; }
            echo '<p><label><input type="checkbox" name="confirm" value="yes" required> Saya menyetujui perubahan tampilan halaman ini.</label></p>';
            submit_button('Apply desain halaman','primary','submit',false);echo '</form></section>';
        }
        echo wp_kses_post((string)paginate_links(['base'=>str_replace('999999','%#%',add_query_arg('paged',999999,admin_url('admin.php?page=falcon-wf-pages'))),'format'=>'','current'=>$page,'total'=>$query->max_num_pages]));
    }
}
