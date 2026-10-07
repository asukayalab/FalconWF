<?php
namespace FalconWF\Admin;
use FalconWF\Bootstrap;
use FalconWF\Settings;
use FalconWF\Health;
use FalconWF\Audit\Logger;
use FalconWF\Installer\ThemeInstaller;
use FalconWF\AI\Policy;
use FalconWF\AI\ProviderClient;
final class Dashboard {
    private const PAGES = [
        'overview'=>['Ringkasan','fwf_manage_system'], 'content'=>['Content Builder','fwf_manage_modules'],
        'identity'=>['Identitas & Kontak','fwf_manage_system'], 'modules'=>['Modul','fwf_manage_modules'],
        'connections'=>['Proyek & Koneksi','fwf_manage_connections'], 'ai'=>['AI','fwf_manage_ai'],
        'updates'=>['Pembaruan','fwf_manage_updates'], 'maintenance'=>['Pemeliharaan','fwf_manage_system'],
        'audit'=>['Log Aktivitas','fwf_view_audit'],
    ];
    public function __construct(private string $file, private Bootstrap $app) {}
    public function register(): void {
        add_action('admin_menu',[$this,'menu']);
        add_action('admin_post_fwf_action',[$this,'action']);
        add_action('admin_enqueue_scripts',function ($hook) {
            if (!str_contains($hook,'falcon-wf')) { return; }
            $manifest=json_decode((string)@file_get_contents(dirname($this->file).'/assets/manifest.json'),true);
            if (isset($manifest['admin.css']) && is_file(dirname($this->file).'/assets/'.$manifest['admin.css'])) {
                wp_enqueue_style('falcon-wf-admin',plugins_url('assets/'.$manifest['admin.css'],$this->file),[],null);
            }
        });
        add_filter('plugin_action_links_' . plugin_basename($this->file),static function ($links) {
            array_unshift($links,'<a href="'.esc_url(admin_url('admin.php?page=falcon-wf')).'">Falcon WF</a>'); return $links;
        });
    }
    public function menu(): void {
        $overview=fn()=> $this->render(current_user_can('fwf_manage_system')?'overview':'content');
        add_menu_page('Falcon WF','Falcon WF','edit_posts','falcon-wf',$overview,'dashicons-admin-generic',58);
        foreach (self::PAGES as $id=>[$label,$cap]) {
            add_submenu_page('falcon-wf',$label,$label,$cap,$id==='overview'?'falcon-wf':'falcon-wf-'.$id,$id==='overview'?$overview:fn()=> $this->render($id));
        }
    }
    private function start(string $op, string $page): void {
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="fwf_action"><input type="hidden" name="operation" value="'.esc_attr($op).'"><input type="hidden" name="screen" value="'.esc_attr($page).'">';
        wp_nonce_field('fwf_'.$op);
    }
    private function end(string $label): void { submit_button($label); echo '</form>'; }
    private function text(string $name,string $label,string $value='',string $type='text'): void {
        echo '<p><label for="fwf-'.esc_attr($name).'">'.esc_html($label).'</label><br><input class="regular-text" id="fwf-'.esc_attr($name).'" name="'.esc_attr($name).'" type="'.esc_attr($type).'" value="'.esc_attr($value).'" required></p>';
    }
    public function render(string $page): void {
        if (!current_user_can(self::PAGES[$page][1])) { wp_die('Tidak diizinkan.', '', ['response'=>403]); }
        echo '<div class="wrap fwf"><p class="fwf-label">ASUKAYALAB / FALCON WF</p><h1>'.esc_html(self::PAGES[$page][0]).'</h1>';
        $notice=get_transient('fwf_notice_'.get_current_user_id());
        if ($notice) { delete_transient('fwf_notice_'.get_current_user_id()); echo '<div role="status" class="notice notice-'.($notice['ok']?'success':'error').'"><p>'.esc_html($notice['text']).'</p></div>'; }
        switch ($page) {
            case 'overview':
                echo '<p>Build pengembangan 0.1.0-alpha.2. Kelulusan rilis 0.1 belum lengkap.</p><dl>';
                foreach (Health::report() as $key=>$value) { echo '<dt>'.esc_html($key).'</dt><dd>'.esc_html(is_bool($value)?($value?'Ya':'Tidak'):(string)$value).'</dd>'; }
                echo '</dl>'; $this->setup(); break;
            case 'identity':
                $this->start('save_identity',$page); $identity=Settings::identity();
                echo '<p>Judul dan tagline di frontend mengikuti <a href="'.esc_url(admin_url('options-general.php')).'">Settings → General WordPress</a>. Nama berikut disimpan sebagai identitas proyek Falcon.</p>';
                $this->text('name','Nama identitas proyek',$identity['name']);
                echo '<p><label for="fwf-contact">Kontak publik</label><br><textarea id="fwf-contact" name="contact" rows="4" maxlength="500">'.esc_textarea($identity['contact']).'</textarea></p>';
                $this->end('Simpan identitas'); break;
            case 'modules':
                echo '<p>Jenis konten bawaan dan buatan pengguna dikelola melalui <a href="'.esc_url(admin_url('admin.php?page=falcon-wf-content')).'">Content Builder</a>. Layar ini untuk modul ekstensi yang didaftarkan melalui kode.</p>';
                $this->start('save_modules',$page);
                foreach ($this->app->modules->all() as $id=>$d) { if (isset($d['definition']) || in_array($id,['publications','learning'],true)) { continue; } echo '<p><label><input type="checkbox" name="modules[]" value="'.esc_attr($id).'" '.checked(in_array($id,$this->app->modules->active(),true),true,false).'> '.esc_html($d['label']).'</label></p>'; }
                echo '<p>Menonaktifkan modul menyembunyikan UI, bukan menghapus konten.</p>'; $this->end('Simpan modul'); break;
            case 'content':
                (new Builder($this->file))->render();
                break;
            case 'ai': $this->ai(); break;
            case 'connections':
                $this->start('save_repo',$page); $this->text('repo','Repo FWF private: owner/name',(string)get_option('fwf_repo',''));
                echo '<p>Credential read-only berasal dari FWF_GITHUB_TOKEN server-side. Repo hanya sumber release, bukan runtime frontend. Paket klien belum didukung build ini.</p>'; $this->end('Simpan repo release'); break;
            case 'updates':
                $this->start('check_updates',$page); $this->end('Periksa private stable release');
                $candidate=get_option('fwf_release_candidate',[]);
                foreach ($candidate['manifest']['packages']??[] as $package) {
                    echo '<p>'.esc_html($package['id'].' → '.$package['version']).'</p>';
                    $this->start('apply_update',$page); echo '<input type="hidden" name="package_id" value="'.esc_attr($package['id']).'"><label><input type="checkbox" name="backup" value="yes" required> Backup tersedia dan paket telah diuji pada staging; saya memilih update komponen ini.</label>'; $this->end('Update komponen');
                }
                echo '<p>Manual, satu komponen per aksi. Recovery kode mengikuti WordPress upgrader; database tidak dipulihkan otomatis. Distribusi private nyata dan restore rehearsal masih perlu diuji.</p>'; break;
            case 'maintenance':
                $this->start('save_maintenance',$page);
                echo '<p><label><input type="checkbox" name="enabled" value="yes" '.checked(get_option('fwf_maintenance',false),true,false).'> Aktifkan maintenance (HTTP 503). Admin berwenang tetap bisa preview.</label></p><p>Terpisah dari theme default Dalam Pembangunan.</p>';
                $this->end('Simpan mode'); break;
            case 'audit':
                echo '<table class="widefat"><thead><tr><th>Waktu UTC</th><th>Actor</th><th>Aksi</th><th>Objek</th><th>Hasil</th><th>Request ID</th></tr></thead><tbody>';
                foreach (Logger::recent() as $row) { echo '<tr>'; foreach (['created_at','actor','action','object_id','result','request_id'] as $key) { echo '<td>'.esc_html((string)$row[$key]).'</td>'; } echo '</tr>'; }
                echo '</tbody></table>'; break;
            default:
                echo '<p>Belum tersedia pada build ini. Private release/update memerlukan repo target, autentikasi terbatas dan pengujian recovery. Tidak ada koneksi atau update production yang dijalankan.</p>'; break;
        }
        echo '</div>';
    }
    private function setup(): void {
        $theme=wp_get_theme('falcon-theme'); $active=get_stylesheet()==='falcon-theme';
        $state=(string)get_option('fwf_setup','not_started'); $attempt=get_option('fwf_setup_attempt',[]);
        echo '<section class="fwf-panel"><h2>Setup Falcon Theme</h2><p>Pilih langkah berikut. Memasang theme tidak mengganti tampilan aktif.</p><ol class="fwf-steps">';
        echo '<li><strong>1 · Periksa dan pasang</strong><p>'.($theme->exists()?'Falcon Theme terpasang: '.esc_html($theme->get('Version')):'Falcon Theme belum terpasang.').'</p>';
        if (!$theme->exists()) {
            $ready=(new ThemeInstaller(dirname($this->file)))->readiness();
            if (is_wp_error($ready)) { echo '<div role="status" class="notice notice-warning inline"><p>'.esc_html($ready->get_error_message()).'</p></div><p><a href="'.esc_url(admin_url('theme-install.php')).'">Buka installer theme WordPress</a> · <a href="'.esc_url(admin_url('site-health.php')).'">Buka Site Health</a></p>'; }
            else { echo '<p>Paket tervalidasi dan pemeriksaan filesystem lulus.</p>'; $this->start('install_theme','overview'); $this->end('Pasang FT bundled'); }
        } else { echo '<p>Installer mempertahankan file yang sudah ada. Versi lama diperbarui lewat menu Pembaruan setelah backup.</p>'; }
        if (in_array($attempt['state']??'',['failed','installing'],true)) {
            echo '<p class="fwf-warning">Percobaan sebelumnya gagal atau belum tercatat selesai. Periksa Site Health dan theme yang terpasang sebelum retry. Konten dan theme aktif dipertahankan.</p>';
        }
        echo '</li><li><strong>2 · Pilih theme aktif</strong><p>Theme saat ini: '.esc_html(wp_get_theme()->get('Name')).'.</p>';
        if ($active) { echo '<p class="fwf-success">Falcon Theme sedang aktif.</p>'; }
        elseif ($theme->exists() && !$theme->errors()) { $this->start('activate_theme','overview'); echo '<label><input type="checkbox" name="confirm" value="yes" required> Saya memilih mengganti theme aktif ke Falcon Theme.</label>'; $this->end('Aktifkan Falcon Theme'); }
        else { echo '<p>Pasang theme terlebih dahulu untuk membuka langkah aktivasi.</p>'; }
        $this->start('skip_setup','overview'); $this->end($state==='skipped'?'Setup dilewati · tetap pertahankan theme':'Lewati setup / pertahankan theme');
        echo '</li><li><strong>3 · Identitas dan konten</strong><p><a href="'.esc_url(admin_url('options-general.php')).'">Atur Judul Situs dan Tagline</a>, lalu <a href="'.esc_url(admin_url('admin.php?page=falcon-wf-content')).'">atur struktur konten</a>. Setup tidak menerbitkan konten otomatis.</p></li></ol></section>';
    }
    private function ai(): void {
        $config=get_option('fwf_outbound',[]);
        echo '<section class="fwf-panel"><h2>Pilih cara memakai AI</h2><p><strong>Agent / MCP:</strong> agent yang terhubung membaca schema dinamis dan membuat atau mengedit draft sesuai scope. Koneksi lokal saat ini menggunakan application password + HTTP Basic; pilih client yang mendukung transport ini.</p><p><strong>OpenAI API:</strong> produksi konten dari dashboard menggunakan key server-side dan model yang dipilih di bawah.</p><p><strong>Paket ChatGPT:</strong> integrasi langsung belum tersedia di build ini. Sign in with ChatGPT memerlukan client terdaftar dan kelayakan penggunaan paket; dukungan Go belum terverifikasi. <a href="https://developers.openai.com/siwc/quickstart" target="_blank" rel="noopener noreferrer">Dokumentasi resmi</a></p></section>';
        echo '<h2>WordPress → provider</h2><p>Credential berasal dari konstanta server-side FWF_OPENAI_API_KEY. Pilih model yang tersedia pada akun provider. Data dikirim hanya setelah aksi eksplisit; proposal tidak otomatis diterapkan/publish.</p>';
        echo '<p>Credential: '.(defined('FWF_OPENAI_API_KEY') && FWF_OPENAI_API_KEY?'tersedia di server':'belum dikonfigurasi').'. Koneksi nyata belum dinyatakan berhasil hanya dari keberadaan credential.</p>';
        $this->start('save_outbound','ai');
        echo '<label><input type="checkbox" name="enabled" value="yes" '.checked(!empty($config['enabled']),true,false).'> Izinkan pengiriman field draft yang dipilih ke OpenAI</label>';
        $this->text('model','Model API',$config['model']??'');
        foreach (['title','body','summary'] as $field) { echo '<p><label><input type="checkbox" name="fields[]" value="'.esc_attr($field).'" '.checked(in_array($field,$config['fields']??[],true),true,false).'> '.esc_html($field).'</label></p>'; }
        echo '<p>Batas awal 20 request/hari, output maksimum 1.200 token/request. Audit menyimpan metadata; proposal disimpan 24 jam. Kebijakan provider perlu ditinjau sebelum koneksi live.</p>';
        $this->end('Simpan izin outbound');
        $this->start('suggest','ai'); $this->text('object_id','ID draft','','number');
        echo '<p><label for="fwf-field">Field konteks</label><br><select id="fwf-field" name="field"><option>title</option><option>body</option><option>summary</option></select></p>';
        echo '<p><label for="fwf-task">Instruksi editorial</label><br><textarea id="fwf-task" name="task" rows="3" maxlength="2000" required></textarea></p>';
        $this->end('Kirim konteks terpilih dan buat proposal');
        $key=sanitize_text_field(wp_unslash($_GET['proposal']??'')); $proposal=get_option('fwf_proposal_'.$key,false);
        if ($proposal && $proposal['actor']===get_current_user_id() && $proposal['at']>time()-DAY_IN_SECONDS) {
            echo '<h3>Review proposal</h3><p>Draft #'.esc_html((string)$proposal['id']).', field '.esc_html($proposal['field']).'</p><pre>'.esc_html($proposal['text']).'</pre>';
            $this->start('apply_proposal','ai'); echo '<input type="hidden" name="proposal" value="'.esc_attr($key).'">'; $this->end('Setujui dan terapkan ke draft');
        }
        $this->start('disconnect_outbound','ai'); $this->end('Putuskan outbound (credential provider tidak dihapus)');
        echo '<h2>Agent → WordPress</h2><p>Transport MCP stateless, application password milik user khusus Falcon Agent. Client harus mendukung HTTP Basic. Integrasi ChatGPT langsung/OAuth belum diverifikasi.</p><p>Endpoint: <code>'.esc_html(rest_url('falcon-wf/v1/mcp')).'</code></p><p>Buat user Falcon Agent dan application password melalui Users. Scope berlaku 30 hari; default semua ditolak. Membaca draft perlu grant tersendiri. Field/objek di luar scope ditolak.</p>';
        $this->start('grant_agent','ai'); $this->text('actor','ID user agent','','number'); $this->text('uuid','UUID application password');
        $this->text('objects','ID objek diizinkan, pisahkan koma','0');
        foreach (['read_published','read_draft','create_draft','edit_draft'] as $action) { echo '<p><label><input type="checkbox" name="actions[]" value="'.esc_attr($action).'"> '.esc_html($action).'</label></p>'; }
        foreach (\FalconWF\Content\Schema::keys() as $field) { echo '<p><label><input type="checkbox" name="fields[]" value="'.esc_attr($field).'"> Field '.esc_html($field).'</label></p>'; }
        foreach ($this->app->content->types() as $type) { echo '<p><label><input type="checkbox" name="types[]" value="'.esc_attr($type).'"> Type '.esc_html($type).'</label></p>'; }
        $this->end('Berikan scope explicit');
        $this->start('revoke_agent','ai'); $this->text('actor','ID user agent','','number'); $this->end('Cabut scope inbound');
    }
    public function action(): void {
        $op=sanitize_key(wp_unslash($_POST['operation']??''));
        $caps=['save_repo'=>'fwf_manage_connections','check_updates'=>'fwf_manage_updates','apply_update'=>'fwf_manage_updates','install_theme'=>'fwf_manage_system','activate_theme'=>'fwf_manage_system','skip_setup'=>'fwf_manage_system','save_identity'=>'fwf_manage_system','save_modules'=>'fwf_manage_modules','save_maintenance'=>'fwf_manage_system','save_outbound'=>'fwf_manage_ai','disconnect_outbound'=>'fwf_manage_ai','suggest'=>'fwf_manage_ai','apply_proposal'=>'fwf_manage_ai','grant_agent'=>'fwf_manage_ai','revoke_agent'=>'fwf_manage_ai'];
        if (!isset($caps[$op]) || !current_user_can($caps[$op])) { Logger::write('admin_denied','FWF_PERMISSION'); wp_die('Tidak diizinkan.', '', ['response'=>403]); }
        check_admin_referer('fwf_'.$op);
        $p=wp_unslash($_POST); $result=true; $proposal='';
        $audit=Logger::write($op,'started'); if (is_wp_error($audit)) { $result=$audit; }
        else {
            switch ($op) {
                case 'save_repo':
                    $repo=sanitize_text_field($p['repo']??'');
                    if (!\FalconWF\Updates\GitHubClient::validRepo($repo)) { $result=new \WP_Error('FWF_VALIDATION','Gunakan owner/name tanpa credential URL.'); } else { update_option('fwf_repo',$repo,false); delete_option('fwf_release_candidate'); } break;
                case 'check_updates': $result=(new \FalconWF\Updates\UpdateManager())->check(); break;
                case 'apply_update': $result=(new \FalconWF\Updates\UpdateManager())->update(sanitize_key($p['package_id']??''),($p['backup']??'')==='yes'); break;
                case 'install_theme': $result=(new ThemeInstaller(dirname($this->file)))->install(); break;
                case 'activate_theme': $result=($p['confirm']??'')==='yes'?(new ThemeInstaller(dirname($this->file)))->activate():new \WP_Error('FWF_PERMISSION','Konfirmasi pergantian theme diperlukan.'); break;
                case 'skip_setup': update_option('fwf_setup','skipped',false); break;
                case 'save_identity': $result=Settings::saveIdentity(['name'=>$p['name']??'','contact'=>$p['contact']??'']); break;
                case 'save_modules': $ids=(array)($p['modules']??[]); if (!empty(\FalconWF\Content\Definitions::all()['types']['fwf_project']['active'])) { $ids[]='projects'; } $result=$this->app->modules->setActive(array_values(array_unique($ids))); break;
                case 'save_maintenance': update_option('fwf_maintenance',($p['enabled']??'')==='yes',false); break;
                case 'save_outbound':
                    $model=sanitize_text_field($p['model']??'');
                    if (!preg_match('/^[a-zA-Z0-9._-]{1,100}$/',$model)) { $result=new \WP_Error('FWF_VALIDATION','Model tidak valid.'); break; }
                    update_option('fwf_outbound',['enabled'=>($p['enabled']??'')==='yes','model'=>$model,'fields'=>array_values(array_intersect((array)($p['fields']??[]),['title','body','summary']))],false); break;
                case 'disconnect_outbound': $config=get_option('fwf_outbound',[]); $config['enabled']=false; update_option('fwf_outbound',$config,false); break;
                case 'suggest': $result=(new ProviderClient($this->app->content))->suggest(absint($p['object_id']??0),(string)($p['field']??''),(string)($p['task']??'')); if (!is_wp_error($result)) { $proposal=$result['proposal']; } break;
                case 'apply_proposal': $result=(new ProviderClient($this->app->content))->apply(sanitize_text_field($p['proposal']??'')); break;
                case 'grant_agent': $result=Policy::grant(absint($p['actor']??0),sanitize_text_field($p['uuid']??''),['actions'=>(array)($p['actions']??[]),'fields'=>(array)($p['fields']??[]),'types'=>(array)($p['types']??[]),'objects'=>explode(',',(string)($p['objects']??''))]); break;
                case 'revoke_agent': Policy::revoke(absint($p['actor']??0)); break;
            }
        }
        Logger::write($op,is_wp_error($result)?$result->get_error_code():'succeeded');
        set_transient('fwf_notice_'.get_current_user_id(),['ok'=>!is_wp_error($result),'text'=>is_wp_error($result)?$result->get_error_message():'Tindakan selesai.'],120);
        $screen=sanitize_key($p['screen']??'overview'); if (!isset(self::PAGES[$screen])) { $screen='overview'; }
        $url=admin_url('admin.php?page='.($screen==='overview'?'falcon-wf':'falcon-wf-'.$screen));
        if ($proposal) { $url=add_query_arg('proposal',$proposal,$url); }
        wp_safe_redirect($url); exit;
    }
}
