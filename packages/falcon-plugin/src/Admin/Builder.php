<?php
namespace FalconWF\Admin;
use FalconWF\Content\Definitions;
use FalconWF\Content\Schema;
final class Builder {
    public function __construct(private string $file) {}
    public function register(): void {
        add_action('admin_post_fwf_builder',[$this,'save']);
        add_action('wp_ajax_fwf_listing_terms',[$this,'terms']);
        add_filter('custom_menu_order','__return_true');
        add_filter('menu_order',static function ($order) {
            $saved=(array)get_option('fwf_menu_order',[]); if (!$saved) { return $order; }
            return array_merge(array_values(array_intersect($saved,$order)),array_values(array_diff($order,$saved)));
        },99);
        add_action('admin_enqueue_scripts',function ($hook) {
            if (str_contains($hook,'falcon-wf-content')) { wp_enqueue_script('fwf-builder',plugins_url('assets/builder.js',$this->file),[], (string)filemtime(dirname($this->file).'/assets/builder.js'),true); }
        });
    }
    private function url(string $tab,string $key=''): string { return add_query_arg(['page'=>'falcon-wf-content','tab'=>$tab,'key'=>$key],admin_url('admin.php')); }
    private function input(string $name,string $label,string $value='',bool $required=false): void {
        echo '<label class="fwf-control">'.esc_html($label).'<input name="'.esc_attr($name).'" value="'.esc_attr($value).'" '.($required?'required':'').'></label>';
    }
    private function select(string $name,string $label,array $choices,string $value): void {
        echo '<label class="fwf-control">'.esc_html($label).'<select name="'.esc_attr($name).'">';
        foreach ($choices as $k=>$l) { echo '<option value="'.esc_attr($k).'" '.selected($k,$value,false).'>'.esc_html($l).'</option>'; } echo '</select></label>';
    }
    private function locations(array $values): void {
        echo '<fieldset data-check-scope><legend>Tampilkan pada jenis konten</legend><div class="fwf-small-actions"><button type="button" class="button button-small" data-check-all>Check all</button> <button type="button" class="button button-small" data-uncheck-all>Uncheck all</button></div>';
        foreach (Definitions::types() as $type) { $o=get_post_type_object($type); $label=$o?$o->label:(Definitions::all()['types'][$type]['label']??$type);
            echo '<label class="fwf-choice"><input type="checkbox" name="types[]" value="'.esc_attr($type).'" '.checked(in_array($type,$values,true),true,false).'> '.esc_html($label).' <code>'.esc_html($type).'</code></label>'; }
        echo '</fieldset>';
    }
    public function render(): void {
        $tab=sanitize_key($_GET['tab']??'types'); if (!in_array($tab,['types','groups','taxonomies','listing','navigation'],true)) { $tab='types'; }
        echo '<p>Buat jenis konten, susun field, dan tentukan kategori untuk listing website.</p><nav class="nav-tab-wrapper">';
        foreach (['types'=>'Jenis Konten','groups'=>'Field Groups','taxonomies'=>'Kategori & Tag','listing'=>'Listing','navigation'=>'Urutan Menu'] as $t=>$label) { echo '<a class="nav-tab '.($tab===$t?'nav-tab-active':'').'" href="'.esc_url($this->url($t)).'">'.esc_html($label).'</a>'; } echo '</nav>';
        if ($tab==='listing') { $this->listing(); return; }
        if ($tab==='navigation') { $this->navigation(); return; }
        $all=Definitions::all(); $key=sanitize_key($_GET['key']??''); $new=isset($_GET['new']);
        if (!$key && !$new) {
            echo '<p><a class="button button-primary" href="'.esc_url(add_query_arg('new','1',$this->url($tab))).'">Create New</a></p><table class="widefat striped"><thead><tr><th>Nama</th><th>Key</th><th>Detail</th><th>Status</th></tr></thead><tbody>';
            foreach ($all[$tab] as $k=>$d) {
                $detail=$tab==='types'?($d['model']==='page'?'Seperti Pages':'Seperti Posts'):implode(', ',$d['types']);
                echo '<tr><td><a href="'.esc_url($this->url($tab,$k)).'"><strong>'.esc_html($d['label']).'</strong></a></td><td><code>'.esc_html($k).'</code></td><td>'.esc_html($detail).'</td><td>'.($d['active']?'Aktif':'Nonaktif').'</td></tr>';
            }
            if (!$all[$tab]) { echo '<tr><td colspan="4">Belum ada definisi. Buat yang pertama.</td></tr>'; } echo '</tbody></table>'; return;
        }
        if ($key && !isset($all[$tab][$key])) { echo '<p>Definisi tidak ditemukan.</p>'; return; }
        $d=$all[$tab][$key]??['label'=>'','active'=>true,'slug'=>'','model'=>$tab==='taxonomies'?'category':'post','types'=>[],'fields'=>[]];
        echo '<form class="fwf-builder-form" method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="fwf_builder"><input type="hidden" name="section" value="'.esc_attr($tab).'"><input type="hidden" name="revision" value="'.esc_attr(Definitions::revision()).'">'; wp_nonce_field('fwf_builder');
        echo '<div class="fwf-builder-head"><h2>'.($key?'Edit: '.esc_html($d['label']):'Create New').'</h2><a class="button" href="'.esc_url($this->url($tab)).'">Batal</a></div><section class="fwf-panel fwf-controls">';
        $this->input('label','Nama',$d['label'],true);
        if ($key) { echo '<label class="fwf-control">Key (tetap)<input name="key" value="'.esc_attr($key).'" readonly></label>'; }
        else { $this->input('key',$tab==='groups'?'Key kelompok, misal detail_karya':'Key unik, misal fwf_produk','',true); }
        if ($tab==='types') { $this->select('model','Perilaku',['post'=>'Seperti Posts — tanpa hierarki','page'=>'Seperti Pages — induk dan anak'],$d['model']); }
        if ($tab==='taxonomies') { $this->select('model','Perilaku',['category'=>'Kategori — induk dan anak','tag'=>'Tag — tanpa hierarki'],$d['model']); }
        if ($tab!=='groups') { $this->input('slug','Slug URL, misal karya-bangunan',$d['slug'],true); }
        echo '<label class="fwf-choice"><input name="active" type="checkbox" value="1" '.checked($d['active'],true,false).'> Aktif (menonaktifkan tidak menghapus isi)</label></section>';
        if ($tab!=='types') { $this->locations($d['types']); }
        if ($tab==='groups') {
            echo '<h2>Fields</h2><p>Tambah field lalu buka barisnya untuk mengatur. Urutkan dengan geser atau tombol ↑ ↓. Key dan tipe yang telah disimpan menjadi kontrak nilai; gunakan key baru untuk mengganti tipe.</p><div class="fwf-small-actions"><button type="button" class="button" data-open-fields>Buka Semua Fields</button> <button type="button" class="button" data-close-fields>Tutup Semua Fields</button></div><div id="fwf-field-list">';
            $i=0; foreach ($d['fields'] as $fkey=>$f) { $this->field($i++,$fkey,$f,true); } echo '</div><button class="button" type="button" data-add-field>+ Add Field</button><template id="fwf-field-template">';
            $this->field('__INDEX__','',['label'=>'','kind'=>'text','default'=>''],false); echo '</template>';
        }
        echo '<div class="fwf-form-actions">'; submit_button('Simpan definisi','primary','submit',false); echo '<a class="button" href="'.esc_url($this->url($tab)).'">'.($key?'Batal Perubahan':'Batal').'</a></div></form>';
        if ($tab==='types' && $key) { echo '<p><a class="button" href="'.esc_url(add_query_arg('new',1,$this->url('groups'))).'">+ Buat Field Group</a></p>'; }
    }
    private function field(int|string $i,string $key,array $d,bool $saved): void {
        $n='fields['.$i.']';
        echo '<details class="fwf-field-row"><summary><span class="fwf-drag-handle" draggable="true" title="Geser untuk mengatur urutan" aria-label="Geser field">⠿</span><span class="fwf-field-title">'.esc_html($d['label']?:'Field baru').'</span> <code>'.esc_html($key).'</code></summary><div class="fwf-field-body"><div class="fwf-controls">';
        $this->input($n.'[label]','Field Label',$d['label'],true);
        echo '<label class="fwf-control">Field Key<input name="'.esc_attr($n.'[key]').'" value="'.esc_attr($key).'" '.($saved?'readonly':'').' required></label>';
        $this->select($n.'[kind]','Field Type',Definitions::KINDS,$d['kind']);
        $this->input($n.'[help]','Petunjuk',$d['help']??''); $this->input($n.'[default]','Nilai awal',(string)$d['default']);
        echo '<label class="fwf-control" data-kind="select">Pilihan (satu key: Label per baris)<textarea name="'.esc_attr($n.'[choices]').'" rows="4">';
        $lines=[]; foreach ($d['choices']??[] as $k=>$v) { $lines[]=$k.': '.$v; } echo esc_textarea(implode("\n",$lines)).'</textarea></label>';
        $this->select($n.'[target]','Target relasi',array_combine(Definitions::types(),Definitions::types()),$d['target']??'post');
        echo '</div><label class="fwf-choice"><input type="checkbox" name="'.esc_attr($n.'[required]').'" value="1" '.checked(!empty($d['required']),true,false).'> Wajib isi</label><label class="fwf-choice"><input type="checkbox" name="'.esc_attr($n.'[public]').'" value="1" '.checked(!empty($d['public']),true,false).'> Tampilkan pada detail publik</label><p><button type="button" class="button" data-up>↑</button> <button type="button" class="button" data-down>↓</button> <button type="button" class="button" data-duplicate>Duplicate</button> <button type="button" class="button-link-delete" data-remove>Remove</button></p></div></details>';
    }
    public function terms(): void {
        if (!current_user_can('fwf_manage_modules') || in_array('fwf_agent',(array)wp_get_current_user()->roles,true)) { wp_send_json_error(['message'=>'Tidak diizinkan.'],403); }
        check_ajax_referer('fwf_listing_terms','nonce');
        $tax=sanitize_key(wp_unslash($_POST['taxonomy']??'')); $type=sanitize_key(wp_unslash($_POST['type']??'')); $obj=get_taxonomy($tax);
        if (!$obj || !$obj->public || !is_object_in_taxonomy($type,$tax) || !in_array($type,Definitions::types(),true)) { wp_send_json_error(['message'=>'Taksonomi tidak tersedia untuk jenis konten ini.'],400); }
        $offset=min(100000,absint($_POST['offset']??0));
        $terms=get_terms(['taxonomy'=>$tax,'hide_empty'=>false,'number'=>201,'offset'=>$offset,'orderby'=>'name','order'=>'ASC']);
        if (is_wp_error($terms)) { wp_send_json_error(['message'=>'Pilihan gagal dimuat.'],500); }
        $more=count($terms)>200; $terms=array_slice($terms,0,200);
        wp_send_json_success(['terms'=>array_map(static fn($t)=>['slug'=>$t->slug,'name'=>$t->name,'count'=>$t->count],$terms),'more'=>$more,'offset'=>$offset+count($terms)]);
    }
    private function listing(): void {
        echo '<section class="fwf-panel"><h2>Listing untuk halaman website</h2><p>Pilih jenis konten, jumlah dan kategori/tag yang ingin ditampilkan. Salin shortcode ke blok Shortcode pada halaman. Hanya konten publish tanpa password yang tampil.</p><div class="fwf-controls" id="fwf-listing-generator" data-url="'.esc_url(admin_url('admin-ajax.php')).'" data-nonce="'.esc_attr(wp_create_nonce('fwf_listing_terms')).'">';
        $choices=[]; foreach (Definitions::types() as $type) { $obj=get_post_type_object($type); if ($obj) { $choices[$type]=$obj->label; } }
        $this->select('listing_type','Jenis konten',$choices,'fwf_project');
        echo '<label class="fwf-control">Taksonomi<select name="listing_taxonomy"><option value="">Tanpa filter taksonomi</option>';
        foreach (get_taxonomies(['public'=>true],'objects') as $key=>$d) { echo '<option value="'.esc_attr($key).'" data-types="'.esc_attr(implode('|',$d->object_type)).'">'.esc_html($d->label).'</option>'; } echo '</select></label>';
        echo '<label class="fwf-control">Jumlah konten (limit)<input type="number" name="listing_limit" value="12" min="1" max="50" step="1" required><span class="description">1–50 konten.</span></label></div>';
        echo '<fieldset id="fwf-listing-terms" data-check-scope hidden><legend>Pilih kategori/tag</legend><div class="fwf-small-actions"><button type="button" class="button button-small" data-check-all>Check all</button> <button type="button" class="button button-small" data-uncheck-all>Uncheck all</button></div><p class="description">Beberapa pilihan memakai aturan salah satu (OR). Check all memilih semua term pada taksonomi ini, termasuk term baru.</p><div id="fwf-term-options"></div><button type="button" class="button button-small" data-more-terms hidden>Muat pilihan berikutnya</button></fieldset><p role="status" id="fwf-term-status"></p><p><code id="fwf-listing-code">[falcon_listing type="fwf_project" limit="12"]</code></p><button class="button" type="button" data-copy-listing>Salin shortcode</button><p role="status" id="fwf-copy-status"></p></section>';
    }
    private function navigation(): void {
        global $menu;
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input name="action" type="hidden" value="fwf_builder"><input name="section" type="hidden" value="navigation">'; wp_nonce_field('fwf_builder');
        echo '<p>Urutan berlaku untuk situs. Menu yang tidak tersedia bagi suatu role tetap tersembunyi. Menu baru ditambahkan setelah urutan tersimpan.</p><div id="fwf-menu-list">';
        $items=[]; foreach ($menu as $m) { if (current_user_can($m[1])) { $items[$m[2]]=wp_strip_all_tags($m[0])?:'Pemisah'; } }
        $saved=(array)get_option('fwf_menu_order',[]); $keys=array_merge(array_intersect($saved,array_keys($items)),array_diff(array_keys($items),$saved));
        foreach ($keys as $key) { echo '<div class="fwf-menu-row"><span class="fwf-drag-handle" draggable="true" title="Geser untuk mengatur urutan" aria-label="Geser menu">⠿</span><input name="order[]" type="hidden" value="'.esc_attr($key).'"><span class="fwf-menu-label">'.esc_html($items[$key]).'</span><span class="fwf-reorder-actions"><button type="button" class="button" data-up>↑</button> <button type="button" class="button" data-down>↓</button></span></div>'; }
        echo '</div>'; submit_button('Simpan urutan'); echo '<button class="button" name="reset" value="1">Reset urutan bawaan</button></form>';
    }
    public function save(): void {
        if (!current_user_can('fwf_manage_modules') || in_array('fwf_agent',(array)wp_get_current_user()->roles,true)) { wp_die('Tidak diizinkan.','',['response'=>403]); }
        check_admin_referer('fwf_builder'); $p=wp_unslash($_POST); $section=sanitize_key($p['section']??'');
        if ($section==='navigation') {
            $order=$p['order']??[];
            if (!is_array($order) || count($order)>200 || count(array_filter($order,'is_string'))!==count($order)) { $result=new \WP_Error('FWF_VALIDATION','Urutan menu tidak valid.'); }
            else { update_option('fwf_menu_order',!empty($p['reset'])?[]:array_values(array_unique(array_map('sanitize_text_field',$order))),false); $result=true; }
        } else { $result=Definitions::save($section,(string)($p['key']??''),$p,(string)($p['revision']??'')); }
        $audit=\FalconWF\Audit\Logger::write('builder_'.$section,is_wp_error($result)?$result->get_error_code():'succeeded');
        if (is_wp_error($audit) && !is_wp_error($result)) { $result=new \WP_Error('FWF_AUDIT','Pengaturan mungkin tersimpan, tetapi audit gagal. Periksa sebelum mencoba ulang.'); }
        set_transient('fwf_notice_'.get_current_user_id(),['ok'=>!is_wp_error($result),'text'=>is_wp_error($result)?$result->get_error_message():'Pengaturan tersimpan.'],120);
        wp_safe_redirect($this->url($section,is_wp_error($result)?'':sanitize_key($p['key']??''))); exit;
    }
}
