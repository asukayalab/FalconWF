<?php
namespace FalconWF\Content;
use FalconWF\Modules\Registry;
use FalconWF\Packages\Lock;
use FalconWF\Audit\Logger;
final class Repository {
    private array $nativeWrites = [];
    public function __construct(private Registry $registry) {}
    public function types(): array { return $this->registry->types(); }
    public function get(int $id): array|\WP_Error {
        $p = get_post($id);
        if (!$p || $p->post_password !== '' || !in_array($p->post_type,$this->types(),true) || !in_array($p->post_status,['publish','draft'],true)) { return new \WP_Error('FWF_NOT_FOUND','Konten tidak tersedia.'); }
        return ['id'=>$p->ID, 'type'=>$p->post_type, 'status'=>$p->post_status, 'author'=>(int)$p->post_author,
            'fields'=>['title'=>$p->post_title,'body'=>$p->post_content,'summary'=>$p->post_excerpt]+Schema::values($p->ID,$p->post_type),
            'revision'=>hash('sha256', wp_json_encode([$p->ID,$p->post_status,$p->post_title,$p->post_content,$p->post_excerpt,Schema::values($p->ID,$p->post_type)]))];
    }
    private function saveMeta(int $id,array $meta): true|\WP_Error {
        foreach ($meta as $key=>$value) {
            update_post_meta($id,$key,wp_slash($value));
            if (!metadata_exists('post',$id,$key) || (string)get_post_meta($id,$key,true)!==(string)$value) {
                wp_save_post_revision($id); Logger::write('content_edit','failed',$id);
                return new \WP_Error('FWF_INTERNAL','Sebagian perubahan mungkin tersimpan. Periksa konten dan revisi sebelum mencoba lagi.',['object_id'=>$id]);
            }
        }
        return true;
    }
    public static function metadataRevision(int $id,string $type): string {
        return hash('sha256',wp_json_encode([Schema::custom($type),Schema::values($id,$type)]));
    }
    public static function nativeRevision(int $id): string {
        $post=get_post($id);
        return hash('sha256',wp_json_encode([$post?->to_array(),$post?self::metadataRevision($id,$post->post_type):null]));
    }
    /** Reserve the schema and object before WordPress can write the native form. */
    public function prepareNative(int $id,string $metaRevision,string $nativeRevision,array $fields): true|\WP_Error {
        if (isset($this->nativeWrites[$id])) { return true; }
        if (!current_user_can('edit_post',$id) || in_array('fwf_agent',(array)wp_get_current_user()->roles,true)) { return new \WP_Error('FWF_PERMISSION','Tidak diizinkan.'); }
        $builder=Lock::acquire('builder'); if (is_wp_error($builder)) { return $builder; }
        $owner=Lock::acquire('content_'.$id);
        if (is_wp_error($owner)) { Lock::release('builder',$builder); return $owner; }
        $this->nativeWrites[$id]=['owner'=>$owner,'builder'=>$builder,'staged'=>false];
        $post=get_post($id);
        $error=null;
        if (!$post || !Schema::custom($post->post_type)) { $error=new \WP_Error('FWF_NOT_FOUND','Field tidak tersedia.'); }
        elseif (!hash_equals(self::metadataRevision($id,$post->post_type),$metaRevision) || !hash_equals(self::nativeRevision($id),$nativeRevision)) {
            $error=new \WP_Error('FWF_CONFLICT','Konten, field atau definisi berubah. Muat ulang editor sebelum menyimpan.');
        } else {
            foreach (Schema::custom($post->post_type) as $key=>$definition) {
                if (!array_key_exists($key,$fields)) { $error=new \WP_Error('FWF_VALIDATION','Field belum dikirim: '.$definition['label']); break; }
            }
            if (!$error && array_diff(array_keys($fields),array_keys(Schema::custom($post->post_type)))) { $error=new \WP_Error('FWF_VALIDATION','Custom field tidak dikenal.'); }
            if (!$error) {
                $data=Schema::validate($fields,$post->post_type);
                if (is_wp_error($data)) { $error=$data; }
                else { $this->nativeWrites[$id]['meta']=$data['meta']; }
            }
        }
        if ($error) { $this->cancelNative($id); return $error; }
        return true;
    }
    /** Persist validated metadata before a native status transition can publish. */
    public function stageNative(int $id): true|\WP_Error {
        if (!isset($this->nativeWrites[$id])) { return new \WP_Error('FWF_CONTRACT','Penyimpanan belum diperiksa.'); }
        if ($this->nativeWrites[$id]['staged']) { return true; }
        $audit=Logger::write('content_native','started',$id); if (is_wp_error($audit)) { $this->cancelNative($id); return $audit; }
        $result=$this->saveMeta($id,$this->nativeWrites[$id]['meta']);
        if (is_wp_error($result)) { $this->cancelNative($id); return $result; }
        $this->nativeWrites[$id]['staged']=true;
        return true;
    }
    public function completeNative(int $id): void {
        if (empty($this->nativeWrites[$id]['staged'])) { return; }
        try {
            wp_save_post_revision($id);
            $audit=Logger::write('content_native','succeeded',$id);
            if (is_wp_error($audit)) { set_transient('fwf_fields_notice_'.get_current_user_id(),'Konten tersimpan, tetapi audit final gagal. Periksa sebelum mencoba lagi.',120); }
        } finally { $this->cancelNative($id); }
    }
    public function cancelNative(int $id): void {
        if (!isset($this->nativeWrites[$id])) { return; }
        $write=$this->nativeWrites[$id]; unset($this->nativeWrites[$id]);
        Lock::release('content_'.$id,$write['owner']); Lock::release('builder',$write['builder']);
    }
    public function closeNativeWrites(): void {
        foreach ($this->nativeWrites as $id=>$write) {
            if ($write['staged']) {
                Logger::write('content_native','incomplete',$id);
                set_transient('fwf_fields_notice_'.get_current_user_id(),'Penyimpanan utama belum selesai. Sebagian field mungkin tersimpan; periksa konten sebelum mencoba lagi.',120);
            }
            $this->cancelNative($id);
        }
    }
    public function saveFields(int $id,string $expected,array $fields): true|\WP_Error {
        if (!current_user_can('edit_post',$id) || in_array('fwf_agent',(array)wp_get_current_user()->roles,true)) { return new \WP_Error('FWF_PERMISSION','Tidak diizinkan.'); }
        $owner=Lock::acquire('content_'.$id); if (is_wp_error($owner)) { return $owner; }
        try {
            $p=get_post($id); if (!$p || !Schema::custom($p->post_type)) { return new \WP_Error('FWF_NOT_FOUND','Field tidak tersedia.'); }
            if (!hash_equals(self::metadataRevision($id,$p->post_type),$expected)) { return new \WP_Error('FWF_CONFLICT','Field atau definisi berubah. Muat ulang editor sebelum menyimpan field.'); }
            if (array_diff(array_keys($fields),array_keys(Schema::custom($p->post_type)))) { return new \WP_Error('FWF_VALIDATION','Custom field tidak dikenal.'); }
            $data=Schema::validate($fields,$p->post_type); if (is_wp_error($data)) { return $data; }
            $audit=Logger::write('content_fields','started',$id); if (is_wp_error($audit)) { return $audit; }
            $saved=$this->saveMeta($id,$data['meta']); if (is_wp_error($saved)) { return $saved; }
            wp_save_post_revision($id); $logged=Logger::write('content_fields','succeeded',$id); return is_wp_error($logged)?$logged:true;
        } finally { Lock::release('content_'.$id,$owner); }
    }
    public function create(string $type, array $fields): array|\WP_Error {
        if (!in_array($type,$this->types(),true) || !current_user_can(get_post_type_object($type)->cap->create_posts)) { return new \WP_Error('FWF_PERMISSION','Jenis konten atau izin create tidak tersedia.'); }
        $data = Schema::validate($fields,$type); if (is_wp_error($data)) { return $data; }
        foreach (Schema::custom($type) as $key=>$d) { if (!empty($d['required']) && !array_key_exists($key,$fields)) { return new \WP_Error('FWF_VALIDATION','Field wajib diisi: '.$d['label']); } }
        $audit=Logger::write('draft_create','started'); if (is_wp_error($audit)) { return $audit; }
        $id = wp_insert_post(wp_slash(array_merge($data['post'],['post_type'=>$type,'post_status'=>'draft','post_author'=>get_current_user_id()])),true);
        if (is_wp_error($id)) { Logger::write('draft_create','failed'); return new \WP_Error('FWF_INTERNAL','Draft gagal disimpan.'); }
        $saved=$this->saveMeta($id,$data['meta']); if (is_wp_error($saved)) { return $saved; }
        wp_save_post_revision($id);
        $logged=Logger::write('draft_create','succeeded',$id);
        if (is_wp_error($logged)) { return new \WP_Error('FWF_AUDIT','Draft tersimpan; audit final gagal. Jangan buat ulang tanpa memeriksa konten.',['object_id'=>$id]); }
        return $this->get($id);
    }
    public function edit(int $id, string $expected, array $fields, bool $draftOnly=true): array|\WP_Error {
        $owner=Lock::acquire('content_' . $id); if (is_wp_error($owner)) { return $owner; }
        try {
            $current=$this->get($id); if (is_wp_error($current)) { return $current; }
            if (($draftOnly && $current['status']!=='draft') || !current_user_can('edit_post',$id)) { return new \WP_Error('FWF_PERMISSION','Status konten atau izin edit tidak tersedia.'); }
            $data=Schema::validate($fields,$current['type']); if (is_wp_error($data)) { return $data; }
            if (!hash_equals($current['revision'],$expected)) { return new \WP_Error('FWF_CONFLICT','Konten berubah; baca ulang dan review revisi baru.'); }
            $audit=Logger::write('content_edit','started',$id); if (is_wp_error($audit)) { return $audit; }
            $result=wp_update_post(wp_slash(array_merge($data['post'],['ID'=>$id])),true);
            if (is_wp_error($result)) { return new \WP_Error('FWF_INTERNAL','Draft gagal disimpan.'); }
            $saved=$this->saveMeta($id,$data['meta']); if (is_wp_error($saved)) { return $saved; }
            wp_save_post_revision($id);
            $logged=Logger::write('content_edit','succeeded',$id);
            if (is_wp_error($logged)) { return new \WP_Error('FWF_AUDIT','Draft berubah; audit final gagal. Periksa sebelum retry.',['object_id'=>$id]); }
            return $this->get($id);
        } finally { Lock::release('content_' . $id,$owner); }
    }
}
