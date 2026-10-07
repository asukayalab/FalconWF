<?php
namespace FalconWF\Modules;
final class Registry {
    private array $descriptors;
    public function __construct() {
        $this->descriptors = [
            'projects'=>['label'=>'Projects', 'type'=>'fwf_project', 'dependencies'=>[]],
            'publications'=>['label'=>'Publications', 'type'=>'fwf_publication', 'dependencies'=>[]],
            'learning'=>['label'=>'Learning', 'type'=>'fwf_learning', 'dependencies'=>[]],
        ];
        $this->all();
    }
    public function add(string $id, array $descriptor): true|\WP_Error {
        if (isset($this->descriptors[$id]) || !preg_match('/^[a-z][a-z0-9_-]{1,39}$/',$id) ||
            !is_string($descriptor['label']??null) || !is_string($descriptor['type']??null) ||
            !preg_match('/^fwf_[a-z0-9_]{1,16}$/',$descriptor['type']) || !is_array($descriptor['dependencies']??null) ||
            count(array_filter($descriptor['dependencies'],'is_string'))!==count($descriptor['dependencies']) ||
            in_array($descriptor['type'],array_column($this->descriptors,'type'),true)) { return new \WP_Error('FWF_CONTRACT','Descriptor modul bentrok atau tidak valid.'); }
        $this->descriptors[$id]=$descriptor; return true;
    }
    public function all(): array {
        foreach (\FalconWF\Content\Definitions::all()['types'] as $type=>$d) {
            $id=$type==='fwf_project'?'projects':$type;
            $this->descriptors[$id]=['label'=>$d['label'],'type'=>$type,'dependencies'=>[],'definition'=>$d];
        }
        return $this->descriptors;
    }
    public function active(): array { $this->all(); return array_values(array_intersect((array)get_option('fwf_modules', []), array_keys($this->descriptors))); }
    public function types(): array {
        $types = ['post', 'page'];
        foreach ($this->active() as $id) { if (isset($this->descriptors[$id]['definition']) && !$this->descriptors[$id]['definition']['active']) { continue; } $types[] = $this->descriptors[$id]['type']; }
        foreach (\FalconWF\Content\Definitions::all()['types'] as $type=>$d) { if ($d['active'] && $type!=='fwf_project') { $types[]=$type; } }
        return array_values(array_unique($types));
    }
    public function setActive(array $ids): true|\WP_Error {
        $this->all();
        if (array_diff($ids, array_keys($this->descriptors))) { return new \WP_Error('FWF_VALIDATION', 'Modul tidak dikenal.'); }
        foreach ($ids as $id) {
            if (array_diff($this->descriptors[$id]['dependencies'], $ids)) { return new \WP_Error('FWF_CONTRACT', 'Dependency modul belum aktif.'); }
        }
        $visited=[]; $visiting=[];
        $visit=function ($id) use (&$visit,&$visited,&$visiting): bool {
            if (isset($visiting[$id])) { return false; } if (isset($visited[$id])) { return true; }
            $visiting[$id]=true;
            foreach ($this->descriptors[$id]['dependencies'] as $dependency) { if (!$visit($dependency)) { return false; } }
            unset($visiting[$id]); $visited[$id]=true; return true;
        };
        foreach ($ids as $id) { if (!$visit($id)) { return new \WP_Error('FWF_CONTRACT','Cycle dependency modul ditolak.'); } }
        update_option('fwf_modules', array_values(array_unique($ids)), false);
        update_option('fwf_rewrite_pending', true, false);
        return true;
    }
    public function register(): void {
        do_action('fwf_register_modules',$this);
        $this->all();
        foreach (array_diff($this->types(),['post','page']) as $type) {
            $d=null; foreach ($this->descriptors as $descriptor) { if ($descriptor['type']===$type) { $d=$descriptor; break; } }
            if (!$d) { continue; } $def=$d['definition']??[]; $hierarchical=($def['model']??'post')==='page';
            $taxonomies=[]; foreach (\FalconWF\Content\Definitions::all()['taxonomies'] as $tax=>$t) { if ($t['active'] && in_array($type,$t['types'],true)) { $taxonomies[]=$tax; } }
            register_post_type($type,['label'=>$d['label'],'public'=>true,'show_in_rest'=>true,
                'show_in_menu'=>!in_array($type,['fwf_publication','fwf_learning'],true),'menu_position'=>21,'menu_icon'=>'dashicons-portfolio',
                'hierarchical'=>$hierarchical,'has_archive'=>!$hierarchical,'rewrite'=>['slug'=>$def['slug']??'falcon-'.$type],
                'taxonomies'=>$taxonomies,'supports'=>['title','editor','excerpt','thumbnail','revisions','custom-fields','page-attributes'],'map_meta_cap'=>true]);
        }
        foreach (\FalconWF\Content\Definitions::all()['taxonomies'] as $tax=>$d) {
            $types=array_values(array_filter($d['types'],'post_type_exists'));
            if ($d['active'] && $types) { register_taxonomy($tax,$types,['label'=>$d['label'],'public'=>true,'hierarchical'=>$d['model']==='category','show_in_rest'=>true,'show_admin_column'=>true,'rewrite'=>['slug'=>$d['slug']]]); }
        }
        \FalconWF\Content\Schema::register();
        if (get_option('fwf_rewrite_pending', false)) { flush_rewrite_rules(false); delete_option('fwf_rewrite_pending'); }
    }
}
