<?php
namespace FalconWF\Content;
use FalconWF\Packages\Lock;
/** Persistent, validated definitions. Stored values survive disabling definitions. */
final class Definitions {
    public const KINDS=['text'=>'Text','textarea'=>'Textarea','integer'=>'Number (integer)','select'=>'Select','date'=>'Date','url'=>'URL','image'=>'Image','relationship'=>'Relationship'];
    public static function init(): void {
        if (get_option('fwf_builder',false)!==false) { return; }
        $project=Schema::preset('fwf_project');
        foreach ($project as &$field) { $field['public']=true; } unset($field);
        if (add_option('fwf_builder',['types'=>['fwf_project'=>['label'=>'Project','slug'=>'project','model'=>'post','active'=>true]],'groups'=>['project_details'=>['label'=>'Detail Project','types'=>['fwf_project'],'fields'=>$project,'active'=>true]],'taxonomies'=>['fwf_project_cat'=>['label'=>'Kategori Project','slug'=>'kategori-project','types'=>['fwf_project'],'model'=>'category','active'=>true]]],'',false)) {
            update_option('fwf_modules',['projects'],false); update_option('fwf_rewrite_pending',true,false);
        }
    }
    public static function all(): array { return (array)get_option('fwf_builder',['types'=>[],'groups'=>[],'taxonomies'=>[]]); }
    public static function revision(): string { return hash('sha256',wp_json_encode(self::all())); }
    public static function types(): array { return array_merge(['post','page'],array_keys(self::all()['types'])); }
    public static function fields(string $type): array {
        $fields=[]; foreach (self::all()['groups'] as $g) { if ($g['active'] && in_array($type,$g['types'],true)) { $fields+=$g['fields']; } } return $fields;
    }
    public static function save(string $section,string $key,array $input,string $revision): true|\WP_Error {
        if (!in_array($section,['types','groups','taxonomies'],true) || !preg_match('/^[a-z][a-z0-9_]{1,31}$/D',$key)) { return new \WP_Error('FWF_VALIDATION','Key definisi tidak valid.'); }
        $owner=Lock::acquire('builder'); if (is_wp_error($owner)) { return $owner; }
        try {
            if (!hash_equals(self::revision(),$revision)) { return new \WP_Error('FWF_CONFLICT','Definisi telah berubah. Muat ulang sebelum menyimpan.'); }
            $all=self::all(); $old=$all[$section][$key]??null;
            $label=sanitize_text_field($input['label']??'');
            if (!$label || strlen($label)>100) { return new \WP_Error('FWF_VALIDATION','Nama wajib diisi, maksimal 100 karakter.'); }
            $d=['label'=>$label,'active'=>!empty($input['active'])];
            if ($section!=='groups') {
                $slug=$input['slug']??'';
                if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]{1,59}$/D',$slug) || in_array($slug,['wp-admin','wp-json','feed','page','author','category','tag','search'],true)) { return new \WP_Error('FWF_VALIDATION','Slug tidak valid atau dicadangkan.'); }
                foreach (['types','taxonomies'] as $s) { foreach ($all[$s] as $k=>$existing) { if (($s!==$section || $k!==$key) && $existing['slug']===$slug) { return new \WP_Error('FWF_VALIDATION','Slug sudah digunakan.'); } } }
                $d['slug']=$slug;
            }
            if ($section==='types') {
                if (in_array($key,['fwf_publication','fwf_learning'],true) || !preg_match('/^fwf_[a-z0-9_]{1,16}$/D',$key) || (!$old && post_type_exists($key))) { return new \WP_Error('FWF_VALIDATION','Key jenis bentrok; gunakan fwf_ dan maksimal 20 karakter.'); }
                $d['model']=$input['model']??''; if (!in_array($d['model'],['post','page'],true)) { return new \WP_Error('FWF_VALIDATION','Pilih model Posts atau Pages.'); }
            } else {
                $d['types']=array_values(array_unique((array)($input['types']??[])));
                if (!$d['types'] || count(array_filter($d['types'],'is_string'))!==count($d['types']) || array_diff($d['types'],self::types())) { return new \WP_Error('FWF_VALIDATION','Pilih lokasi jenis konten yang tersedia.'); }
                if ($section==='taxonomies') {
                    if (!preg_match('/^fwf_[a-z0-9_]{1,28}$/D',$key) || (!$old && taxonomy_exists($key))) { return new \WP_Error('FWF_VALIDATION','Key taksonomi bentrok; gunakan awalan fwf_.'); }
                    $d['model']=$input['model']??''; if (!in_array($d['model'],['category','tag'],true)) { return new \WP_Error('FWF_VALIDATION','Pilih kategori atau tag.'); }
                } else {
                    $d['fields']=[]; $rows=$input['fields']??[];
                    if (!is_array($rows) || count($rows)>100) { return new \WP_Error('FWF_VALIDATION','Maksimal 100 field per kelompok.'); }
                    $contracts=(array)get_option('fwf_field_contracts',[]);
                    foreach ($rows as $row) {
                        if (!is_array($row)) { return new \WP_Error('FWF_VALIDATION','Field tidak valid.'); }
                        $fkey=$row['key']??''; $kind=$row['kind']??''; $flabel=sanitize_text_field($row['label']??'');
                        if (!is_string($fkey) || !preg_match('/^[a-z][a-z0-9_]{1,39}$/D',$fkey) || in_array($fkey,Schema::keysNative(),true) || isset($d['fields'][$fkey]) || !is_string($kind) || !isset(self::KINDS[$kind]) || !$flabel || strlen($flabel)>100) { return new \WP_Error('FWF_VALIDATION','Label, key atau tipe field tidak valid/duplikat.'); }
                        $f=['label'=>$flabel,'kind'=>$kind,'help'=>sanitize_text_field($row['help']??''),'required'=>!empty($row['required']),'public'=>!empty($row['public'])];
                        $numeric=in_array($kind,['integer','image','relationship'],true); $raw=$row['default']??'';
                        if ($numeric && $raw!=='' && (!is_scalar($raw) || !preg_match('/^\d+$/D',(string)$raw))) { return new \WP_Error('FWF_VALIDATION','Nilai awal angka harus bilangan bulat positif atau nol.'); }
                        if (!is_scalar($raw)) { return new \WP_Error('FWF_VALIDATION','Nilai awal field harus skalar.'); }
                        $f['default']=$numeric?(int)$raw:sanitize_text_field($raw);
                        if ($kind==='integer') { $previous=$old['fields'][$fkey]??[]; $f['min']=$previous['min']??0; $f['max']=$previous['max']??2147483647; if ($f['default']>$f['max']) { return new \WP_Error('FWF_VALIDATION','Angka terlalu besar.'); } }
                        if ($kind==='select') {
                            $f['choices']=[]; foreach (explode("\n",(string)($row['choices']??'')) as $line) {
                                if (!trim($line)) { continue; } $pair=explode(':',$line,2); $v=trim($pair[0]); $l=sanitize_text_field(trim($pair[1]??$v));
                                if (!preg_match('/^[a-zA-Z0-9_-]{1,40}$/D',$v) || !$l || isset($f['choices'][$v])) { return new \WP_Error('FWF_VALIDATION','Pilihan select memakai key: Label yang unik.'); } $f['choices'][$v]=$l;
                            }
                            if (!$f['choices'] || !isset($f['choices'][$f['default']])) { return new \WP_Error('FWF_VALIDATION','Nilai awal select harus salah satu pilihan.'); }
                        }
                        if ($kind==='relationship') { $f['target']=$row['target']??''; if (!in_array($f['target'],self::types(),true)) { return new \WP_Error('FWF_VALIDATION','Target relasi tidak tersedia.'); } }
                        foreach ($all['groups'] as $other) { foreach ($other['fields'] as $okey=>$of) { if ($okey===$fkey) { $contracts[$fkey]=[$of['kind'],$of['target']??'']; } } }
                        $contract=[$kind,$f['target']??''];
                        if (isset($contracts[$fkey]) && $contracts[$fkey]!==$contract) { return new \WP_Error('FWF_VALIDATION','Tipe/target key tersimpan tidak boleh berubah. Gunakan key baru.'); }
                        foreach ($all['groups'] as $gkey=>$g) { if ($gkey!==$key && array_intersect($g['types'],$d['types']) && isset($g['fields'][$fkey])) { return new \WP_Error('FWF_VALIDATION','Field key sudah dipakai kelompok lain pada lokasi ini.'); } }
                        $contracts[$fkey]=$contract; $d['fields'][$fkey]=$f;
                    }
                    // Validate defaults through the same value validator, without required checks.
                    foreach ($d['fields'] as $fkey=>$f) { $valid=Schema::validateCustom($fkey,$f['default'],$f,false); if (is_wp_error($valid)) { return $valid; } }
                    update_option('fwf_field_contracts',$contracts,false);
                }
            }
            $all[$section][$key]=$d; update_option('fwf_builder',$all,false);
            if (self::all()!==$all) { return new \WP_Error('FWF_INTERNAL','Definisi gagal disimpan.'); }
            if ($section==='types' && $key==='fwf_project') { $modules=(array)get_option('fwf_modules',[]); $modules=array_values(array_diff($modules,['projects'])); if ($d['active']) { $modules[]='projects'; } update_option('fwf_modules',$modules,false); }
            update_option('fwf_rewrite_pending',true,false); return true;
        } finally { Lock::release('builder',$owner); }
    }
}
