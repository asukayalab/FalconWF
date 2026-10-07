<?php
namespace FalconWF\Admin;
use FalconWF\Content\Schema;
use FalconWF\Content\Repository;
final class ContentEditor {
    public function __construct(private Repository $content) {}
    public function register(): void {
        add_action('save_post',[$this,'saveNative'],20,2);
        add_action('admin_notices',function () {
            $n=get_transient('fwf_fields_notice_'.get_current_user_id()); if (!$n) { return; } delete_transient('fwf_fields_notice_'.get_current_user_id());
            echo '<div class="notice notice-error"><p>'.esc_html($n).'</p></div>';
        });
        add_action('add_meta_boxes',function ($type) {
            if (!Schema::custom($type)) { return; }
            remove_meta_box('postcustom',$type,'normal');
            add_meta_box('fwf-fields','Falcon WF · Custom Fields',function ($post) { $this->render($post->ID,true); },$type,'normal','high');
        });
        add_filter('post_row_actions',function ($actions,$post) {
                if (Schema::custom($post->post_type) && current_user_can('edit_post',$post->ID)) { $actions['fwf_fields']='<a href="'.esc_url($this->url($post->ID)).'">Field terstruktur</a>'; } return $actions;
        },10,2);
    }
    public function url(int $id): string { return admin_url('post.php?post='.$id.'&action=edit'); }
    public function render(int $id,bool $native=true): void {
        $object=$this->content->get($id);
        if ($native && is_wp_error($object) && ($post=get_post($id))) { $object=['type'=>$post->post_type,'fields'=>['title'=>$post->post_title]+Schema::values($id,$post->post_type),'revision'=>'']; }
        if (is_wp_error($object) || !current_user_can('edit_post',$id)) { echo '<div class="notice notice-error"><p>Konten tidak tersedia atau tidak diizinkan.</p></div>'; return; }
        $defs=Schema::custom($object['type']);
        if (!$defs) { echo '<p>Jenis ini belum memiliki custom field.</p>'; return; }
        wp_nonce_field('fwf_native_fields_'.$id,'fwf_fields_nonce');
        echo '<input type="hidden" name="fwf_meta_revision" value="'.esc_attr(Repository::metadataRevision($id,$object['type'])).'">';
        foreach ($defs as $key=>$d) {
            $value=$object['fields'][$key]; $input='fwf-field-'.$key;
            echo '<p><label for="'.esc_attr($input).'">'.esc_html($d['label']).'</label><br>';
            if (in_array($d['kind'],['select','image','relationship'],true)) {
                $choices=$d['choices']??[0=>'— Tidak dipilih —'];
                if ($d['kind']==='image' || $d['kind']==='relationship') {
                    $posts=get_posts(['post_type'=>$d['kind']==='image'?'attachment':$d['target'],'post_status'=>$d['kind']==='image'?'inherit':['publish','draft'],'posts_per_page'=>100,'orderby'=>'date','order'=>'DESC']);
                    foreach ($posts as $p) {
                        if (($d['kind']==='image' && !wp_attachment_is_image($p->ID)) || $p->post_password!=='' || !current_user_can('edit_post',$p->ID)) { continue; }
                        $choices[$p->ID]=$p->post_title.' (#'.$p->ID.')';
                    }
                    if ($value && !isset($choices[$value])) { $choices[$value]='Pilihan tersimpan #'.$value.' (validasi ulang saat simpan)'; }
                }
                echo '<select id="'.esc_attr($input).'" name="fields['.esc_attr($key).']">';
                foreach ($choices as $v=>$label) { echo '<option value="'.esc_attr((string)$v).'" '.selected((string)$value,(string)$v,false).'>'.esc_html($label).'</option>'; } echo '</select>';
                if ($d['kind']==='image') {
                    if ($value) { echo '<span class="fwf-cover">'.wp_get_attachment_image((int)$value,'medium').'</span>'; }
                    if (current_user_can('upload_files')) { echo ' <a href="'.esc_url(admin_url('media-new.php')).'">Unggah gambar baru</a>'; }
                }
            } elseif ($d['kind']==='textarea') {
                echo '<textarea class="widefat" id="'.esc_attr($input).'" name="fields['.esc_attr($key).']" rows="4">'.esc_textarea($value).'</textarea>';
            } else {
                $inputType=match($d['kind']) {'integer'=>'number','url'=>'url','date'=>'date',default=>'text'};
                echo '<input class="widefat" id="'.esc_attr($input).'" name="fields['.esc_attr($key).']" type="'.esc_attr($inputType).'" value="'.esc_attr((string)$value).'"';
                if ($d['kind']==='integer') { echo ' min="'.esc_attr((string)($d['min']??0)).'" max="'.esc_attr((string)($d['max']??2147483647)).'" step="1"'; }
                echo '>';
            }
            if (!empty($d['help'])) { echo '<span class="description">'.esc_html($d['help']).'</span>'; }
            echo '</p>';
        }
        if ($native) { echo '<p class="description">Field disimpan bersama tombol Save/Update WordPress. Jika validasi field gagal, isi utama WordPress tetap mengikuti penyimpanan native.</p>'; }
    }
    public function saveNative(int $id,\WP_Post $post): void {
        if (wp_is_post_revision($id) || wp_is_post_autosave($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !isset($_POST['fwf_fields_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['fwf_fields_nonce'])),'fwf_native_fields_'.$id) || !current_user_can('edit_post',$id)) { return; }
        $fields=wp_unslash($_POST['fields']??[]); if (!is_array($fields)) { return; }
        foreach (Schema::custom($post->post_type) as $key=>$d) {
            if (isset($fields[$key]) && in_array($d['kind'],['integer','image','relationship'],true) && is_string($fields[$key]) && preg_match('/^\d+$/D',$fields[$key])) { $fields[$key]=(int)$fields[$key]; }
            if (!empty($d['required']) && !array_key_exists($key,$fields)) { set_transient('fwf_fields_notice_'.get_current_user_id(),'Field wajib belum dikirim: '.$d['label'],120); return; }
        }
        if (!$fields) { return; }
        $result=$this->content->saveFields($id,sanitize_text_field(wp_unslash($_POST['fwf_meta_revision']??'')),$fields);
        if (is_wp_error($result)) { set_transient('fwf_fields_notice_'.get_current_user_id(),$result->get_error_message().' Nilai field belum diterapkan.',120); }
    }
}
