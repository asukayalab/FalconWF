<?php
namespace FalconWF\Content;
use FalconWF\Packages\Lock;
use FalconWF\Admin\PageDesign;
/** Declarative project setup; Definitions, WordPress and PageDesign remain write owners. */
final class Blueprint {
    public const FILE='falcon-blueprint.json';
    private static function fail(string $message): never { throw new \RuntimeException($message); }
    private static function must(mixed $value): mixed { if(is_wp_error($value)){self::fail($value->get_error_message());}return $value; }
    private static function allowed(): void {
        if(is_multisite() || !current_user_can('fwf_manage_system') || !current_user_can('fwf_manage_modules') || in_array('fwf_agent',(array)wp_get_current_user()->roles,true)){self::fail('Pengelola sistem dan Content Builder diperlukan.');}
    }
    public static function validateDocument(array $doc): true|\WP_Error {
        $schema=json_decode((string)file_get_contents(dirname(__DIR__,2).'/resources/project-blueprint.schema.json'),true);
        $valid=rest_validate_value_from_schema($doc,$schema,'blueprint');if(is_wp_error($valid)){return $valid;}
        $keys=array_column($doc['pages'],'key');$slugs=array_column($doc['pages'],'slug');
        if(count(array_unique($keys))!==count($keys) || count(array_unique($slugs))!==count($slugs)){return new \WP_Error('FWF_BLUEPRINT','Peran atau slug halaman pada blueprint duplikat.');}
        foreach($doc['pages'] as $page){if(trim($page['title'])==='' || in_array($page['slug'],$schema['properties']['pages']['items']['properties']['slug']['not']['enum'],true) || ($page['template']!=='default' && (validate_file($page['template'])!==0 || !str_ends_with($page['template'],'.php')))){return new \WP_Error('FWF_BLUEPRINT','Judul/template halaman blueprint tidak valid.');}}
        foreach($doc['definitions']??[] as $section=>$definitions){foreach($definitions as $key=>$definition){
            if(!preg_match('/^[a-z][a-z0-9_]{1,31}$/D',$key)){return new \WP_Error('FWF_BLUEPRINT','Key definisi blueprint tidak valid.');}
            if(isset($definition['page_roles']) && ($section!=='groups' || ($definition['types']??null)!==['page'] || !$definition['page_roles'] || array_diff($definition['page_roles'],$keys))){return new \WP_Error('FWF_BLUEPRINT','Lokasi peran halaman pada field group tidak valid.');}
            if($section==='groups' && in_array('page',$definition['types']??[],true) && empty($definition['page_roles'])){return new \WP_Error('FWF_BLUEPRINT','Field Page paket harus menyebut page_roles agar tidak muncul di semua halaman.');}
        }}
        return true;
    }
    public static function load(): array|\WP_Error {
        try{
            self::allowed();$theme=wp_get_theme();$root=$theme->get_stylesheet_directory();$path=$root.'/'.self::FILE;
            if($theme->get('Template')!=='falcon-theme' || !is_file($path)){return new \WP_Error('FWF_BLUEPRINT_UNAVAILABLE','Theme aktif belum membawa blueprint. Instal paket proyek, review lalu aktifkan theme sebagai aksi manusia.');}
            if(is_link($root) || is_link($path) || filesize($path)>262144){self::fail('File blueprint tidak aman atau melebihi256KiB.');}
            if(version_compare(wp_get_theme('falcon-theme')->get('Version'),'0.1.0-alpha.7','<')){self::fail('Perbarui Falcon Theme minimal alpha.7 sebelum memakai blueprint.');}
            $raw=file_get_contents($path);$doc=json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($doc)){self::fail('Blueprint harus object JSON.');}self::must(self::validateDocument($doc));
            $templates=$theme->get_page_templates(null,'page');foreach($doc['pages'] as $page){if($page['template']!=='default' && !isset($templates[$page['template']])){self::fail('Template '.$page['template'].' belum terdaftar pada theme aktif.');}}
            return ['document'=>$doc,'digest'=>hash('sha256',$raw),'theme'=>get_stylesheet(),'theme_version'=>$theme->get('Version')];
        }catch(\Throwable $e){return new \WP_Error('FWF_BLUEPRINT',$e->getMessage());}
    }
    public static function state(string $project): array { return get_option('fwf_blueprints',[])[$project]??['roles'=>[],'owned'=>[],'status'=>'new']; }
    public static function pageUrl(string $project,string $role): string {
        $id=self::state($project)['roles'][$role]??0;$post=is_int($id)?get_post($id):null;
        return $post && $post->post_type==='page' && $post->post_status==='publish' && $post->post_password===''?(string)get_permalink($id):'';
    }
    private static function persist(string $project,array $state): void {
        $all=get_option('fwf_blueprints',[]);$all[$project]=$state;update_option('fwf_blueprints',$all,false);if(get_option('fwf_blueprints')!==$all){self::fail('Status setup gagal disimpan. Periksa halaman yang mungkin sudah dibuat sebelum mencoba lagi.');}
    }
    private static function pages(): array {
        $query=new \WP_Query(['post_type'=>'page','post_status'=>['publish','draft','pending','private','future'],'posts_per_page'=>501,'orderby'=>'ID','order'=>'ASC']);
        if($query->found_posts>500){self::fail('Setup ini mendukung maksimal500 Page; perkecil lingkup melalui pengelola sebelum review.');}
        $pages=[];foreach($query->posts as $post){if(current_user_can('edit_post',$post->ID)){$pages[$post->ID]=$post;}}return $pages;
    }
    public static function discover(): array|\WP_Error {
        $loaded=self::load();if(is_wp_error($loaded)){return $loaded;}
        try{
            $pages=self::pages();$revisions=[];foreach($pages as $id=>$post){$revisions[$id]=[Repository::nativeRevision($id),get_post_meta($id,'_wp_page_template',true),get_post_meta($id,'_fwf_blueprint_role',true)];}
            $doc=$loaded['document'];$terms=[];foreach($doc['terms']??[] as $term){$existing=get_term_by('slug',$term['slug'],$term['taxonomy']);$terms[]=$existing?$existing->to_array():null;}
            $loaded['pages']=$pages;$loaded['state']=self::state($doc['project_id']);
            $loaded['revision']=hash('sha256',wp_json_encode([$loaded['digest'],$loaded['theme'],$loaded['theme_version'],'@@PLUGIN_VERSION@@',wp_get_theme('falcon-theme')->get('Version'),[get_option('home'),get_option('siteurl'),get_option('permalink_structure'),get_option('page_on_front'),get_option('show_on_front')],wp_get_theme()->get_page_templates(null,'page'),Definitions::revision(),get_option('fwf_field_contracts',[]),$loaded['state'],$revisions,$terms,get_option('fwf_seo',[])]));return $loaded;
        }catch(\Throwable $e){return new \WP_Error('FWF_BLUEPRINT',$e->getMessage());}
    }
    private static function changes(array $doc,array $choices,array $roleIDs=[],bool $preview=false): array {
        $changes=[];
        foreach(['types','taxonomies','groups'] as $section){foreach($doc['definitions'][$section]??[] as $key=>$input){
            if(($choices['definitions'][$section.'/'.$key]??'keep')!=='new'){continue;}
            $roles=$input['page_roles']??[];unset($input['page_roles']);
            if($roles){
                $selected=array_filter($roles,static fn($role)=>($choices['modes'][$role]??'keep')==='new');if(!$selected){continue;}
                $input['pages']=$preview?[]:array_values(array_map(static fn($role)=>(int)$roleIDs[$role],$selected));
            }
            $changes[]=['section'=>$section,'key'=>$key,'input'=>$input];
        }}return $changes;
    }
    public static function review(array $input): array|\WP_Error {
        try{
            $current=self::must(self::discover());$doc=$current['document'];
            if(!is_string($input['revision']??null) || !hash_equals($current['revision'],$input['revision'])){self::fail('Paket atau situs berubah. Muat ulang dan review lagi.');}
            $choices=['targets'=>[],'modes'=>[],'definitions'=>[],'terms'=>($input['terms']??'')==='yes','seo'=>($input['seo']??'keep')];$used=[];
            if(!in_array($choices['seo'],['keep','package'],true) || ($choices['seo']==='package' && !isset($doc['seo']['mode']))){self::fail('Pilihan SEO tidak valid.');}
            foreach($doc['pages'] as $page){
                $key=$page['key'];$target=$input['targets'][$key]??null;$mode=$input['modes'][$key]??null;
                if(!is_string($target) || !in_array($mode,['keep','new'],true)){self::fail('Pilih target dan struktur setiap halaman.');}
                if($target==='new'){
                    if(!current_user_can('edit_pages')){self::fail('Izin membuat Page diperlukan.');}
                    foreach($current['pages'] as $post){if($post->post_name===$page['slug']){self::fail('Slug '.$page['slug'].' sudah ada; pilih halaman existing agar tidak membuat duplikat.');}}
                    $mode='new';
                }elseif(!ctype_digit($target) || !isset($current['pages'][(int)$target]) || isset($used[(int)$target])){self::fail('Target Page tidak tersedia atau dipakai dua peran.');}
                else{if($mode==='new' && wp_check_post_lock((int)$target)){self::fail('Halaman sedang diedit pengguna lain. Selesaikan edit sebelum review.');}$used[(int)$target]=true;}
                $choices['targets'][$key]=$target;$choices['modes'][$key]=$mode;
            }
            foreach($doc['definitions']??[] as $section=>$definitions){foreach($definitions as $key=>$d){$action=$input['definitions'][$section.'/'.$key]??null;if(!in_array($action,['keep','new'],true)){self::fail('Pilih tindakan tiap definisi.');}$choices['definitions'][$section.'/'.$key]=$action;}}
            $plan=self::must(Definitions::plan(self::changes($doc,$choices,[],true),Definitions::revision()));
            // A shared definition must not silently change a Page whose old structure is retained.
            foreach($doc['definitions']['groups']??[] as $key=>$group){
                if(($choices['definitions']['groups/'.$key]??'keep')!=='new'){continue;}
                $old=Definitions::all()['groups'][$key]??null;if(!$old){continue;}
                if(in_array('page',$old['types'],true) && empty($old['pages']) && !empty($group['page_roles'])){self::fail('Kelompok '.$key.' masih berlaku global untuk Page. Pertahankan atau gunakan key group baru agar Page lain tidak kehilangan struktur.');}
                $wanted=[];foreach($group['page_roles']??[] as $role){if($choices['modes'][$role]==='new' && ctype_digit($choices['targets'][$role])){$wanted[]=(int)$choices['targets'][$role];}}
                if(!empty($old['pages']) && array_diff($old['pages'],$wanted)){self::fail('Kelompok '.$key.' juga dipakai Page yang dipertahankan/di luar pilihan. Pertahankan definisinya atau gunakan key group baru.');}
            }
            $auditChanges=self::changes($doc,$choices,[],true);$auditAll=$plan['all'];
            foreach($auditChanges as $i=>$change){if($change['section']!=='groups'){continue;}$roles=$doc['definitions']['groups'][$change['key']]['page_roles']??[];if(!$roles){continue;}$ids=[];foreach($roles as $role){if($choices['modes'][$role]==='new' && ctype_digit($choices['targets'][$role])){$ids[]=(int)$choices['targets'][$role];}}if(!$ids){unset($auditChanges[$i]);}else{$auditAll['groups'][$change['key']]['pages']=$ids;}}
            self::validatePublished($auditChanges,$auditAll);
            if($choices['terms'] && !current_user_can('manage_categories')){self::fail('Izin mengelola kategori/tag diperlukan.');}
            if($choices['terms']){foreach($doc['terms']??[] as $term){if(!taxonomy_exists($term['taxonomy']) && !isset($plan['all']['taxonomies'][$term['taxonomy']])){self::fail('Taksonomi term belum tersedia: '.$term['taxonomy']);}}}
            return ['revision'=>$current['revision'],'digest'=>$current['digest'],'project'=>$doc['project_id'],'choices'=>$choices,'token'=>wp_generate_uuid4()];
        }catch(\Throwable $e){return new \WP_Error('FWF_BLUEPRINT',$e->getMessage());}
    }
    private static function validatePublished(array $changes,array $all): void {
        foreach($changes as $change){if($change['section']!=='groups'){continue;}$group=$all['groups'][$change['key']];if(!$group['active']){continue;}
            $q=['post_type'=>$group['types'],'post_status'=>['publish','private','future'],'posts_per_page'=>501];if(!empty($group['pages'])){$q['post__in']=$group['pages'];}$posts=new \WP_Query($q);if($posts->found_posts>500){self::fail('Audit field published melampaui500 object; gunakan lingkup lebih kecil.');}
            foreach($posts->posts as $post){foreach($group['fields'] as $key=>$definition){$value=metadata_exists('post',$post->ID,'_fwf_'.$key)?get_post_meta($post->ID,'_fwf_'.$key,true):$definition['default'];if(in_array($definition['kind'],['integer','image','relationship'],true)){$value=(int)$value;}self::must(Schema::validateCustom($key,$value,$definition));}}
        }
    }
    public static function apply(array $review,bool $confirmed): true|\WP_Error {
        $lease=null;$project='';$state=null;
        try{
            self::allowed();if(!$confirmed){self::fail('Konfirmasi penerapan blueprint diperlukan.');}
            $lease=self::must(Lock::acquire('blueprint'));$current=self::must(self::discover());$doc=$current['document'];$project=$doc['project_id'];
            if(($review['project']??null)!==$project || ($review['digest']??null)!==$current['digest'] || !hash_equals($current['revision'],(string)($review['revision']??''))){self::fail('Situs/paket berubah setelah review. Review ulang sebelum Apply.');}
            $rechecked=self::must(self::review(array_merge($review['choices'],['revision'=>$review['revision'],'terms'=>!empty($review['choices']['terms'])?'yes':''])));
            $choices=$rechecked['choices'];$state=$current['state'];$state['status']='applying';$state['error']='';self::persist($project,$state);$pageRevisions=[];
            foreach($doc['pages'] as $page){$role=$page['key'];$target=$choices['targets'][$role];
                if($target==='new'){
                    // Recheck slug immediately before create. Stable marker also supports recovery after a partial setup.
                    foreach(self::pages() as $post){if($post->post_name===$page['slug']){self::fail('Page dengan slug ini baru muncul. Review ulang: '.$page['slug']);}}
                    $id=self::must(wp_insert_post(wp_slash(['post_type'=>'page','post_status'=>'draft','post_title'=>$page['title'],'post_name'=>$page['slug'],'post_author'=>get_current_user_id(),'meta_input'=>['_fwf_blueprint_role'=>$project.':'.$role]]),true));
                    if(get_post_meta($id,'_fwf_blueprint_role',true)!==$project.':'.$role){self::fail('Draft dibuat tetapi penanda gagal. Periksa Page#'.$id.'; jangan membuat ulang tanpa review.');}
                }else{$id=(int)$target;}
                $pageRevisions[$role]=PageDesign::revision($id);$state['roles'][$role]=$id;self::persist($project,$state);
            }
            $changes=self::changes($doc,$choices,$state['roles']);$plan=self::must(Definitions::plan($changes,Definitions::revision()));self::validatePublished($changes,$plan['all']);self::must(Definitions::applyPlan($plan));
            foreach($changes as $change){$state['owned'][$change['section'].'/'.$change['key']]=hash('sha256',wp_json_encode($plan['all'][$change['section']][$change['key']]));}self::persist($project,$state);
            \FalconWF\Bootstrap::instance()->modules->register();
            foreach($doc['pages'] as $page){$role=$page['key'];if($choices['modes'][$role]==='new'){$id=$state['roles'][$role];self::must(PageDesign::save((string)$id,$page['template'],$pageRevisions[$role],'yes'));}}
            if($choices['terms']){foreach($doc['terms']??[] as $term){if(!term_exists($term['slug'],$term['taxonomy'])){self::must(wp_insert_term($term['name'],$term['taxonomy'],['slug'=>$term['slug'],'description'=>$term['description']??'']));}}}
            if($choices['seo']==='package'){self::must(\FalconWF\Settings::saveSeo($doc['seo']['mode'],\FalconTheme\Seo::revision()));}
            $state['status']='ready';$state['version']=$doc['version'];$state['digest']=$current['digest'];$state['error']='';self::persist($project,$state);return true;
        }catch(\Throwable $e){if($state!==null && $project!==''){$state['status']='partial';$state['error']=$e->getMessage();try{self::persist($project,$state);}catch(\Throwable $ignored){}}return new \WP_Error('FWF_BLUEPRINT',$e->getMessage().' Jika sebagian tahap tersimpan, review ulang; data tidak dihapus.');}
        finally{if(is_string($lease)){Lock::release('blueprint',$lease);}}
    }
    public static function render(): void {
        $current=self::discover();echo '<section class="fwf-panel"><h2>Setup website dari paket</h2>';
        if(is_wp_error($current)){echo '<p>'.esc_html($current->get_error_message()).'</p></section>';return;}
        $doc=$current['document'];$state=$current['state'];echo '<h3>'.esc_html($doc['website']['name']).'</h3><p>'.esc_html($doc['website']['purpose']).'</p><p>Paket '.esc_html($doc['project_id'].' / '.$doc['version']).'. Page baru menjadi draft. Isi dan URL existing dipertahankan; theme, homepage dan publish tidak diubah.</p>';
        if(!empty($state['error'])){echo '<p role="alert">Setup sebelumnya belum lengkap: '.esc_html($state['error']).'</p>';}
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fwf_action"><input type="hidden" name="operation" value="review_blueprint"><input type="hidden" name="screen" value="pages"><input type="hidden" name="revision" value="'.esc_attr($current['revision']).'">';wp_nonce_field('fwf_review_blueprint');
        foreach($doc['pages'] as $page){$role=$page['key'];$selected=$state['roles'][$role]??0;
            if(!$selected){$matches=[];foreach($current['pages'] as $id=>$post){if(get_post_meta($id,'_fwf_blueprint_role',true)===$doc['project_id'].':'.$role || $post->post_name===$page['slug'] || sanitize_title($post->post_title)===sanitize_title($page['title'])){$matches[]=$id;}}if(count($matches)===1){$selected=$matches[0];}elseif(count($matches)>1){$selected=-1;}}
            echo '<fieldset><legend><strong>'.esc_html($page['title'].' — '.$role).'</strong></legend><p>Usulan URL: /'.esc_html($page['slug']).'/ · Template: '.esc_html($page['template']).'</p><label>Hubungkan ke Page <select name="targets['.esc_attr($role).']" required><option value="">Pilih halaman / buat baru</option><option value="new"'.selected($selected,0,false).'>Buat draft baru</option>';
            foreach($current['pages'] as $id=>$post){echo '<option value="'.(int)$id.'"'.selected($selected,$id,false).'>'.esc_html($post->post_title.' (#'.$id.', '.$post->post_status.', /'.$post->post_name.'/)').'</option>';}echo '</select></label><p><label>Struktur <select name="modes['.esc_attr($role).']"><option value="keep">Pertahankan struktur existing</option><option value="new">Gunakan struktur paket</option></select></label></p></fieldset>';
        }
        $all=Definitions::all();foreach($doc['definitions']??[] as $section=>$definitions){foreach($definitions as $key=>$definition){$old=$all[$section][$key]??null;$ownership=$state['owned'][$section.'/'.$key]??null;$changed=$old && (!$ownership || $ownership!==hash('sha256',wp_json_encode($old)));
            echo '<p><label>'.esc_html($section.' / '.$key.' — '.$definition['label']).' <select name="definitions['.esc_attr($section.'/'.$key).']"><option value="keep"'.selected((bool)$old,true,false).'>Pertahankan existing / lewati</option><option value="new"'.selected(!$old,true,false).'>Gunakan definisi paket</option></select></label>'.($changed?' <strong>Existing/lokal: review sebelum mengganti.</strong>':'').'</p>';
            echo '<details><summary>Rincian definisi existing dan paket</summary><pre>'.esc_html(wp_json_encode(['existing'=>$old,'package'=>$definition],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></details>';
            if($section==='groups'){echo '<ul>';foreach($definition['fields']??[] as $field){echo '<li>'.esc_html($field['key'].' · '.$field['kind'].(!empty($field['required'])?' · wajib':'').(!empty($field['public'])?' · publik':' · privat')).'</li>';}echo '</ul>';}
        }}
        if(!empty($doc['terms'])){echo '<p><label><input type="checkbox" name="terms" value="yes" checked> Siapkan kategori/tag yang belum ada; term existing tidak diganti.</label></p><ul>';foreach($doc['terms'] as $term){echo '<li>'.esc_html($term['taxonomy'].' / '.$term['slug'].' — '.$term['name']).'</li>';}echo '</ul>';}
        if(isset($doc['seo'])){echo '<p><label>SEO <select name="seo"><option value="keep">Pertahankan pengaturan SEO</option><option value="package">Gunakan mode '.esc_html($doc['seo']['mode']).' dari paket</option></select></label></p><p>'.esc_html($doc['seo']['notes']??'').'</p>';}
        foreach($doc['instructions']??[] as $note){echo '<p>'.esc_html($note).'</p>';}submit_button('Review setup website');echo '</form>';
        $review=get_transient('fwf_blueprint_review_'.get_current_user_id());if(is_array($review) && ($review['project']??'')===$doc['project_id']){
            echo '<h3>Review penerapan</h3><ul>';foreach($doc['pages'] as $page){$role=$page['key'];echo '<li>'.esc_html($page['title'].' → '.($review['choices']['targets'][$role]==='new'?'Draft baru':(($current['pages'][(int)$review['choices']['targets'][$role]]->post_title??'Page').' #'.$review['choices']['targets'][$role])).' / '.($review['choices']['modes'][$role]==='new'?'struktur paket':'pertahankan struktur')).'</li>';}foreach($review['choices']['definitions'] as $key=>$mode){echo '<li>'.esc_html($key.' → '.($mode==='new'?'gunakan definisi paket':'pertahankan / lewati')).'</li>';}echo '</ul><p>Kategori/tag: '.($review['choices']['terms']?'siapkan yang belum ada':'lewati').'; SEO: '.esc_html($review['choices']['seo']).'. Perubahan schema dapat memengaruhi renderer publik. Tidak ada penghapusan data. Kegagalan parsial ditampilkan untuk review/retry.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fwf_action"><input type="hidden" name="operation" value="apply_blueprint"><input type="hidden" name="screen" value="pages"><input type="hidden" name="review_token" value="'.esc_attr($review['token']).'">';wp_nonce_field('fwf_apply_blueprint');echo '<p><label><input type="checkbox" name="confirm" value="yes" required> Saya menyetujui rencana ini dan telah meninjau dampak struktur/backup yang sesuai.</label></p>';submit_button('Terapkan setup website');echo '</form>';
        }echo '</section>';
    }
}
