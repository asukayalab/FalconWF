<?php
namespace FalconWF\Content;
/** One contract for human forms, repository, revisions and scoped agent fields. */
final class Schema {
    public const FIELDS = ['title'=>'post_title', 'body'=>'post_content', 'summary'=>'post_excerpt'];
    public static function preset(string $type): array {
        $common=['cover_image'=>['label'=>'Gambar sampul','kind'=>'image','default'=>0,'help'=>'Pilih gambar dari Media Library.']];
        return match ($type) {
            'fwf_project'=>$common + [
                'location'=>['label'=>'Lokasi','kind'=>'text','default'=>'','max'=>200],
                'project_year'=>['label'=>'Tahun proyek','kind'=>'integer','default'=>0,'min'=>0,'max'=>2100],
                'project_stage'=>['label'=>'Tahap proyek','kind'=>'select','default'=>'concept','choices'=>['concept'=>'Konsep','development'=>'Pengembangan','completed'=>'Selesai']],
            ],
            'fwf_publication'=>$common + [
                'publication_date'=>['label'=>'Tanggal publikasi','kind'=>'date','default'=>''],
                'publication_url'=>['label'=>'Tautan sumber','kind'=>'url','default'=>''],
                'related_project'=>['label'=>'Proyek terkait','kind'=>'relationship','default'=>0,'target'=>'fwf_project'],
            ],
            'fwf_learning'=>$common + [
                'level'=>['label'=>'Tingkat materi','kind'=>'select','default'=>'beginner','choices'=>['beginner'=>'Pemula','intermediate'=>'Menengah','advanced'=>'Lanjutan']],
                'duration_minutes'=>['label'=>'Durasi (menit)','kind'=>'integer','default'=>0,'min'=>0,'max'=>10080],
                'related_project'=>['label'=>'Proyek terkait','kind'=>'relationship','default'=>0,'target'=>'fwf_project'],
            ],
            default=>[],
        };
    }
    public static function custom(string $type): array {
        if (in_array($type,['fwf_publication','fwf_learning'],true)) { return self::preset($type); }
        return Definitions::fields($type);
    }
    public static function keysNative(): array { return array_keys(self::FIELDS); }
    public static function types(): array { return array_values(array_unique(array_merge(Definitions::types(),\FalconWF\Bootstrap::instance()?->content->types()??[]))); }
    public static function keys(?string $type=null): array {
        $keys=array_keys(self::FIELDS);
        foreach ($type===null?self::types():[$type] as $t) { $keys=array_merge($keys,array_keys(self::custom($t))); }
        return array_values(array_unique($keys));
    }
    public static function values(int $id,string $type): array {
        $values=[];
        foreach (self::custom($type) as $key=>$d) {
            $value=metadata_exists('post',$id,'_fwf_'.$key)?get_post_meta($id,'_fwf_'.$key,true):$d['default'];
            $values[$key]=in_array($d['kind'],['integer','image','relationship'],true)?(int)$value:(string)$value;
        }
        return $values;
    }
    public static function validate(array $fields,string $type='post'): array|\WP_Error {
        if (!$fields || array_diff(array_keys($fields),self::keys($type))) { return new \WP_Error('FWF_VALIDATION','Field tidak tersedia untuk jenis konten ini.'); }
        $result=['post'=>[],'meta'=>[]]; $custom=self::custom($type);
        foreach ($fields as $key=>$value) {
            if (isset(self::FIELDS[$key])) {
                if (!is_string($value) || strlen($value)>32000 || ($key==='title' && strlen($value)>300)) { return new \WP_Error('FWF_VALIDATION','Nilai field terlalu panjang atau tipe tidak valid.'); }
                $result['post'][self::FIELDS[$key]]=$key==='title'?sanitize_text_field($value):wp_kses_post($value);
                continue;
            }
            $clean=self::validateCustom($key,$value,$custom[$key]);
            if (is_wp_error($clean)) { return $clean; }
            $result['meta']['_fwf_'.$key]=$clean;
        }
        return $result;
    }
    public static function validateCustom(string $key,mixed $value,array $d,bool $required=true): mixed {
        $kind=$d['kind']; $clean=$value; $valid=true;
        if (in_array($kind,['integer','image','relationship'],true)) {
            $valid=is_int($value) && $value>=($d['min']??0) && $value<=($d['max']??PHP_INT_MAX);
            if ($valid && $value>0 && $kind!=='integer') {
                $p=get_post($value);
                $valid=$p && $p->post_password==='' && ($kind==='image'?wp_attachment_is_image($value):$p->post_type===$d['target']) &&
                ($kind==='image'?$p->post_status==='inherit':in_array($p->post_status,['publish','draft'],true)) &&
                ($p->post_status==='publish' || current_user_can('edit_post',$value));
                if ($valid && $kind==='image' && $p->post_parent) {
                $parent=get_post($p->post_parent);
                $valid=$parent && $parent->post_password==='' && ($parent->post_status==='publish' || current_user_can('edit_post',$parent->ID));
                }
            }
        } else {
            $valid=is_string($value) && strlen($value)<=($d['max']??2000);
            if ($valid) {
                $clean=$kind==='textarea'?sanitize_textarea_field($value):sanitize_text_field($value);
                if ($kind==='url' && $value!=='') { $valid=(bool)filter_var($value,FILTER_VALIDATE_URL) && in_array(strtolower((string)wp_parse_url($value,PHP_URL_SCHEME)),['https','http'],true) && !wp_parse_url($value,PHP_URL_USER) && !wp_parse_url($value,PHP_URL_PASS); $clean=esc_url_raw($value,['http','https']); }
                if ($kind==='select') { $valid=array_key_exists($value,$d['choices']); }
                if ($kind==='date' && $value!=='') { $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value); $valid=$date && $date->format('Y-m-d')===$value; }
            }
        }
        if (!$valid) { return new \WP_Error('FWF_VALIDATION','Nilai tidak valid: '.$d['label'].'.'); }
        if ($required && !empty($d['required']) && ($clean==='' || $clean===0)) { return new \WP_Error('FWF_VALIDATION','Field wajib diisi: '.$d['label'].'.'); }
        return $clean;
    }
    public static function register(): void {
        foreach (self::types() as $type) {
            if (!post_type_exists($type)) { continue; }
            foreach (self::custom($type) as $key=>$d) {
                register_post_meta($type,'_fwf_'.$key,['single'=>true,'type'=>is_int($d['default'])?'integer':'string','default'=>$d['default'],
                    'show_in_rest'=>false,'revisions_enabled'=>true,'auth_callback'=>static fn($allowed,$meta,$id)=>current_user_can('edit_post',$id)]);
            }
        }
    }
    public static function jsonProperties(): array {
        $props=array_fill_keys(array_keys(self::FIELDS),['type'=>'string']);
        foreach (self::types() as $type) {
            foreach (self::custom($type) as $key=>$d) { $props[$key]=['type'=>is_int($d['default'])?'integer':'string','description'=>$d['label']]; }
        }
        return $props;
    }
}
