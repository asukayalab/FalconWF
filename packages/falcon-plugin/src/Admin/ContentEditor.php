<?php
namespace FalconWF\Admin;
use FalconWF\Content\Schema;
use FalconWF\Content\Repository;
final class ContentEditor {
    public function __construct(private Repository $content) {}
    public function register(): void {
        // Gutenberg saves legacy metaboxes after its REST write. Keep one form boundary.
        add_filter('use_block_editor_for_post_type',static fn($use,$type)=>Schema::custom($type)?false:$use,10,2);
        add_action('load-post.php',[$this,'preflightForm']);
        add_filter('wp_insert_post_empty_content',[$this,'guardWrite'],PHP_INT_MAX,2);
        add_action('wp_after_insert_post',[$this->content,'completeNative'],PHP_INT_MAX,1);
        add_action('shutdown',[$this->content,'closeNativeWrites']);
        add_action('rest_api_init',function () {
            foreach (Schema::types() as $type) { add_filter('rest_pre_insert_'.$type,[$this,'guardRest'],10,2); }
        });
        add_action('publish_future_post',[$this,'guardScheduled'],1);
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
        echo '<input type="hidden" name="fwf_meta_revision" value="'.esc_attr(Repository::metadataRevision($id,$object['type'])).'"><input type="hidden" name="fwf_content_revision" value="'.esc_attr(Repository::nativeRevision($id)).'">';
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
        if ($native) { echo '<p class="description">Field dan konten diperiksa sebelum Save/Update. Jika field tidak valid atau revisi berubah, penyimpanan ditolak. Jenis dengan field Falcon memakai editor klasik.</p>'; }
    }
    private function formFields(string $type): array|\WP_Error {
        $fields=wp_unslash($_POST['fields']??null);
        if (!is_array($fields)) { return new \WP_Error('FWF_VALIDATION','Payload field tidak valid.'); }
        foreach (Schema::custom($type) as $key=>$definition) {
            if (isset($fields[$key]) && in_array($definition['kind'],['integer','image','relationship'],true) && is_string($fields[$key]) && preg_match('/^\d+$/D',$fields[$key])) { $fields[$key]=(int)$fields[$key]; }
        }
        return $fields;
    }
    private function prepareForm(int $id): true|\WP_Error {
        $post=get_post($id);
        if (!$post || !current_user_can('edit_post',$id) || in_array('fwf_agent',(array)wp_get_current_user()->roles,true)) { return new \WP_Error('FWF_PERMISSION','Tidak diizinkan.'); }
        $nonce=$_POST['fwf_fields_nonce']??null;
        if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce),'fwf_native_fields_'.$id)) { return new \WP_Error('FWF_PERMISSION','Nonce field tidak valid. Muat ulang editor.'); }
        $fields=$this->formFields($post->post_type); if (is_wp_error($fields)) { return $fields; }
        $meta=$_POST['fwf_meta_revision']??null; $native=$_POST['fwf_content_revision']??null;
        if (!is_string($meta) || !is_string($native)) { return new \WP_Error('FWF_CONFLICT','Revisi form tidak tersedia. Muat ulang editor.'); }
        return $this->content->prepareNative($id,wp_unslash($meta),wp_unslash($native),$fields);
    }
    private function refuse(\WP_Error $error,bool $http=false): void {
        $message=$error->get_error_message().' Penyimpanan utama ditolak.';
        set_transient('fwf_fields_notice_'.get_current_user_id(),$message,120);
        if ($http) {
            $status=match($error->get_error_code()) {'FWF_PERMISSION'=>403,'FWF_CONFLICT','FWF_LOCK'=>409,default=>400};
            wp_die(esc_html($message),'Falcon WF',['response'=>$status,'back_link'=>true]);
        }
    }
    public function preflightForm(): void {
        $action=$_POST['action']??'';
        if (isset($_POST['deletepost'])) { return; }
        if (!in_array($action,['editpost','postajaxpost'],true) || !isset($_POST['post_ID']) || !is_scalar($_POST['post_ID'])) { return; }
        $id=absint($_POST['post_ID']); $post=get_post($id);
        if (!$post || !Schema::custom($post->post_type)) { return; }
        $nonce=$_POST['_wpnonce']??null;
        if (!is_string($nonce) || !wp_verify_nonce(wp_unslash($nonce),'update-post_'.$id)) { $this->refuse(new \WP_Error('FWF_PERMISSION','Nonce WordPress tidak valid.'),true); }
        $result=$this->prepareForm($id);
        if (is_wp_error($result)) { $this->refuse($result,true); }
    }
    public function guardWrite(bool $empty,array $post): bool {
        if ($empty || !Schema::custom($post['post_type'])) { return $empty; }
        $id=(int)($post['ID']??0);
        $hasForm=isset($_POST['fwf_fields_nonce']) || isset($_POST['fwf_meta_revision']) || isset($_POST['fields']);
        if ($hasForm && $id && (!isset($_POST['post_ID']) || (int)$_POST['post_ID']===$id)) {
            $result=$this->prepareForm($id);
            if (!is_wp_error($result)) { $result=$this->content->stageNative($id); }
        } else {
            $result=Schema::validatePublication($id,$post['post_type'],$post['post_status'],$post['meta_input']??[]);
        }
        if (is_wp_error($result)) {
            $this->refuse($result,is_admin() && !wp_doing_ajax() && ($_POST['action']??'')==='editpost');
            return true;
        }
        return false;
    }
    public function guardRest($post,\WP_REST_Request $request) {
        if (is_wp_error($post)) { return $post; }
        $id=(int)($request['id']??0); $existing=$id?get_post($id):null;
        $type=$existing?->post_type??$post->post_type;
        foreach ((array)($request['meta']??[]) as $key=>$value) {
            if (str_starts_with((string)$key,'_fwf_')) { return new \WP_Error('FWF_PERMISSION','Field Falcon tidak dapat ditulis melalui REST metadata WordPress.',['status'=>403]); }
        }
        $result=Schema::validatePublication($id,$type,$post->post_status??$existing?->post_status??'draft');
        if (is_wp_error($result)) { $result->add_data(['status'=>400]); return $result; }
        return $post;
    }
    public function guardScheduled(int $id): void {
        $post=get_post($id);
        if (!$post || $post->post_status!=='future') { return; }
        $result=Schema::validatePublication($id,$post->post_type,'publish');
        if (is_wp_error($result)) {
            // The core scheduler subsequently sees a draft and cannot publish it.
            wp_update_post(['ID'=>$id,'post_status'=>'draft']);
            \FalconWF\Audit\Logger::write('scheduled_publish','refused',$id);
        }
    }
}
