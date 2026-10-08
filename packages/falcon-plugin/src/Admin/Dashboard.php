<?php
namespace FalconWF\Admin;
use FalconWF\Bootstrap;
use FalconWF\Settings;
use FalconWF\Health;
use FalconWF\Audit\Logger;
use FalconWF\Installer\ThemeInstaller;
use FalconWF\AI\Policy;
use FalconWF\Updates\ProjectManager;
use FalconWF\AI\ProviderClient;
final class Dashboard {
    private const PAGES = [
        'overview'=>['Ringkasan','fwf_manage_system'], 'content'=>['Content Builder','fwf_manage_modules'],
        'backup'=>['Backup & Restore','fwf_manage_system'],
        'design'=>['Desain Global','fwf_manage_system'],
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
            if (str_contains($hook,'falcon-wf-backup')) { wp_enqueue_media();wp_enqueue_script('fwf-backup',plugins_url('assets/backup.js',$this->file),['media-views'],(string)filemtime(dirname($this->file).'/assets/backup.js'),true); }
            if (isset($manifest['admin.css']) && is_file(dirname($this->file).'/assets/'.$manifest['admin.css'])) {
                wp_enqueue_style('falcon-wf-admin',plugins_url('assets/'.$manifest['admin.css'],$this->file),[],null);
            }
        });
        add_action('wp_ajax_fwf_backup_media',static function() {
            if (!current_user_can('fwf_manage_system')) { wp_send_json_error(['message'=>'Tidak diizinkan.'],403); }check_ajax_referer('fwf_backup_media');
            try { $ids=\FalconWF\Backup\MediaSelection::ids(wp_unslash($_POST['ids']??''));$snapshot=\FalconWF\Backup\MediaSelection::snapshot($ids,false);wp_send_json_success(['bytes'=>$snapshot['bytes'],'files'=>$snapshot['count'],'attachments'=>count($snapshot['records'])]); }
            catch (\Throwable $e) { wp_send_json_error(['message'=>$e->getMessage()],400); }
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
    private function start(string $op, string $page, bool $upload=false): void {
        echo '<form method="post"'.($upload?' enctype="multipart/form-data"':'').' action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="fwf_action"><input type="hidden" name="operation" value="'.esc_attr($op).'"><input type="hidden" name="screen" value="'.esc_attr($page).'">';
        wp_nonce_field('fwf_'.$op);
    }
    private function end(string $label): void { submit_button($label); echo '</form>'; }
    private function text(string $name,string $label,string $value='',string $type='text',bool $required=true): void {
        echo '<p><label for="fwf-'.esc_attr($name).'">'.esc_html($label).'</label><br><input class="regular-text" id="fwf-'.esc_attr($name).'" name="'.esc_attr($name).'" type="'.esc_attr($type).'" value="'.esc_attr($value).'"'.($required?' required':'').'></p>';
    }
    public function render(string $page): void {
        if (!current_user_can(self::PAGES[$page][1])) { wp_die('Tidak diizinkan.', '', ['response'=>403]); }
        echo '<div class="wrap fwf"><p class="fwf-label">ASUKAYALAB / FALCON WF</p><h1>'.esc_html(self::PAGES[$page][0]).'</h1>';
        $notice=get_transient('fwf_notice_'.get_current_user_id());
        if ($notice) { delete_transient('fwf_notice_'.get_current_user_id()); echo '<div role="status" class="notice notice-'.($notice['ok']?'success':'error').'"><p>'.esc_html($notice['text']).'</p></div>'; }
        switch ($page) {
            case 'overview':
                echo '<p>Build pengembangan @@PRODUCT_VERSION@@. Kelulusan rilis 0.1 belum lengkap.</p><dl>';
                foreach (Health::report() as $key=>$value) { echo '<dt>'.esc_html($key).'</dt><dd>'.esc_html(is_bool($value)?($value?'Ya':'Tidak'):(string)$value).'</dd>'; }
                echo '</dl>'; $this->setup(); break;
            case 'identity':
                $this->start('save_identity',$page); $identity=Settings::identity();
                echo '<p>Judul dan tagline di frontend mengikuti <a href="'.esc_url(admin_url('options-general.php')).'">Settings → General WordPress</a>. Nama berikut disimpan sebagai identitas proyek Falcon.</p>';
                $this->text('name','Nama identitas proyek',$identity['name']);
                echo '<p><label for="fwf-contact">Kontak publik</label><br><textarea id="fwf-contact" name="contact" rows="4" maxlength="500">'.esc_textarea($identity['contact']).'</textarea></p>';
                $this->end('Simpan identitas'); $this->seo(); break;
            case 'design': $this->design(); break;
            case 'backup': $this->backup(); break;
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
                $this->start('save_repo',$page); $this->text('repo','Alamat repo FWF (URL GitHub atau owner/name)',(string)get_option('fwf_repo',''));
                $this->text('release_tag','Tag prerelease (local/staging saja; kosong = stable)',(string)get_option('fwf_update_tag',''),'text',false);
                echo '<p>Commit/push belum membuat update: GitHub Release harus berisi release-manifest.json dan ZIP komponen dari build commit bersih. Prerelease dipilih lewat tag tertentu, bukan otomatis.</p>';
                echo '<p>Repo publik tidak memerlukan token atau pengaturan server. Setelah menyimpan, buka Pembaruan untuk memeriksa versi. Koneksi desain klien terpisah di bawah.</p>'; $this->end('Simpan repo release'); $this->projectConnection(); break;
            case 'updates':
                $this->start('check_updates',$page); $this->end(get_option('fwf_update_tag','')===''?'Periksa pembaruan':'Periksa pembaruan prerelease');
                echo '<p>Environment: '.esc_html(wp_get_environment_type()).' · Jalur: '.esc_html(get_option('fwf_update_tag','')?:'stable terbaru').'.</p>';
                $candidate=get_option('fwf_release_candidate',[]);
                foreach (['falcon-wf'=>'Falcon Plugin','falcon-theme'=>'Falcon Theme'] as $id=>$label) {
                    $installed=\FalconWF\Updates\UpdateManager::installedVersion($id);
                    echo '<p>'.esc_html($label.' terpasang: '.($installed?:'belum terpasang')).'</p>';
                }
                if (!empty($candidate['notes'])) { echo '<h2>Catatan perubahan</h2><pre style="white-space:pre-wrap">'.esc_html($candidate['notes']).'</pre>'; }
                foreach ($candidate['manifest']['packages']??[] as $package) {
                    $current=\FalconWF\Updates\UpdateManager::installedVersion($package['id']);
                    $newer=$current!=='' && version_compare($package['version'],$current,'>');
                    echo '<p>'.esc_html($package['id'].' · Terpasang: '.($current?:'belum terpasang').' · Tersedia: '.$package['version'].' · '.($newer?'Pembaruan tersedia':($current===''?'Pasang melalui installer terlebih dahulu':'Tidak ada versi lebih baru'))).'</p>';
                    if (!$newer) { continue; }
                    $this->start('apply_update',$page); echo '<input type="hidden" name="package_id" value="'.esc_attr($package['id']).'"><label><input type="checkbox" name="backup" value="yes" required> Backup tersedia dan paket telah diuji pada staging; saya memilih update komponen ini.</label>'; $this->end('Update komponen');
                }
                echo '<p>Periksa → review versi dan catatan perubahan → update satu komponen. Paket diperiksa ulang dan hash ZIP diverifikasi sebelum pemasangan. Theme aktif tetap dipertahankan. Pemulihan database memakai backup terpisah.</p>'; $this->projectUpdates(); break;
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
    private function projectConnection(): void {
        $connection=ProjectManager::connection();echo '<section class="fwf-panel"><h2>Paket desain proyek</h2><p>Satu child theme per koneksi situs. Repo menyimpan desain/template; konten dan pengaturan desain tetap di situs. Pemasangan tidak mengaktifkan theme.</p>';
        $this->start('save_project','connections');
        $this->text('project_repo','Repo desain klien (URL GitHub atau owner/name)',$connection['repo']??'');
        $this->text('project_id','Project ID di project-manifest.json',$connection['project_id']??'');
        $this->text('theme_id','Slug folder child theme',$connection['theme_id']??'');
        $this->text('project_tag','Tag prerelease (local/staging; kosong = stable)',$connection['tag']??'','text',false);
        echo '<p>Repo desain publik tidak membutuhkan token. Repo private memerlukan credential read-only FWF_PROJECT_GITHUB_TOKEN pada server. Release proyek berisi project-manifest.json dan ZIP child theme dari commit bersih.</p>';
        $this->end('Simpan koneksi proyek');
        if ($connection) { $this->start('disconnect_project','connections');echo '<p>Putus koneksi mempertahankan file theme, konten dan pengaturan desain.</p>';$this->end('Putus koneksi proyek'); }
        echo '</section>';
    }
    private function projectUpdates(): void {
        $connection=ProjectManager::connection();echo '<section class="fwf-panel"><h2>Paket desain proyek</h2>';
        if (!$connection) { echo '<p>Isi repo dan identitas paket di <a href="'.esc_url(admin_url('admin.php?page=falcon-wf-connections')).'">Proyek &amp; Koneksi</a> terlebih dahulu.</p></section>';return; }
        $theme=wp_get_theme($connection['theme_id']??'');
        echo '<p>Repo: '.esc_html($connection['repo']??'').' · Theme: '.esc_html($connection['theme_id']??'').' · Terpasang: '.esc_html($theme->exists()?$theme->get('Version'):'belum').'.</p>';
        $this->start('check_project','updates');$this->end('Periksa paket proyek');
        $candidate=get_option('fwf_project_candidate',[]);$p=$candidate['manifest']['packages'][0]??null;
        if (is_array($p) && ($candidate['connection']??null)===$connection) {
            echo '<p>Target: '.esc_html($p['id'].' → '.$p['version']).'. Checksum, identitas dan kompatibilitas FP/FT telah diperiksa; diperiksa ulang saat apply.</p>';
            $this->start('apply_project','updates');echo '<p><label><input type="checkbox" name="backup" value="yes" required> Backup tersedia, paket telah diuji pada staging, dan saya memilih memasang/memperbarui file desain ini.</label></p>';$this->end($theme->exists()?'Perbarui desain proyek':'Pasang desain proyek');
        }
        if ($theme->exists()) { echo '<p><a href="'.esc_url(admin_url('themes.php?theme='.rawurlencode($connection['theme_id']))).'">Lihat theme / Live Preview</a>. Pilih aktivasi melalui Appearance → Themes sebagai aksi manusia terpisah.</p>'; }
        echo '<p>Edit kode langsung pada child theme akan terganti saat update. Konten, Reading settings dan pilihan theme aktif dipertahankan. Recovery mengikuti WordPress/backup.</p></section>';
    }
    private function recoveryPanel(): void {
        echo '<section class="fwf-panel"><h2>Pemulihan restore terputus</h2>';
        $pending=\FalconWF\Backup\Recovery::pending();if (!$pending) { echo '<p>Tidak ada restore terputus yang perlu dipulihkan.</p>'; }
        else { echo '<p>Restore terputus ditemukan. Hentikan perubahan pada situs, lalu periksa hasilnya. Pemulihan tidak dijalankan otomatis.</p>';foreach ($pending as $id) { $this->start('review_recovery','backup');echo '<input type="hidden" name="journal_id" value="'.esc_attr($id).'"><p><code>'.esc_html($id).'</code></p>';$this->end('Periksa pemulihan'); } }
        $completed=\FalconWF\Backup\Recovery::completed();if ($completed) { echo '<h3>Sisa pembersihan restore yang sudah ditutup</h3><p>Konten sudah dipertahankan atau dikembalikan. Periksa untuk membersihkan sisa journal privat.</p>';foreach ($completed as $id) { $this->start('review_recovery','backup');echo '<input type="hidden" name="journal_id" value="'.esc_attr($id).'"><p><code>'.esc_html($id).'</code></p>';$this->end('Periksa sisa pembersihan'); } }
        $review=get_transient('fwf_backup_recovery_review_'.get_current_user_id());
        if (is_array($review)) { echo '<h3>Review pemulihan terputus</h3><p>'.esc_html(!empty($review['cleanup_only'])?'Restore sudah ditutup. Hanya sisa journal privat dibersihkan; konten tidak diubah.':($review['decision']==='committed'?'Database sudah berhasil dipulihkan. File hasil restore dipertahankan; hanya sisa proses dan lock miliknya dibersihkan.':'Database belum commit. File dikembalikan ke keadaan sebelum restore; file baru dari restore dibatalkan.')).'</p><p>'.(int)$review['files'].' file; backup keselamatan: <code>'.esc_html($review['safety']).'</code>.</p>';$this->start('apply_recovery','backup');echo '<input type="hidden" name="journal_id" value="'.esc_attr($review['id']).'"><p><label><input type="checkbox" name="confirm" value="yes" required> Saya telah menghentikan perubahan situs dan menyetujui tindakan pemulihan yang direview.</label></p>';$this->end('Jalankan pemulihan yang direview'); }
        echo '<p>Jika dashboard tidak dapat dibuka, operator server dapat memakai alat rescue FWF dari paket yang terverifikasi. Koneksi database dan folder backup privat tetap diperlukan.</p></section>';
    }
    private function mediaPicker(string $prefix,?array $ids=null): void {
        echo '<fieldset data-fwf-media-picker data-prefix="'.esc_attr($prefix).'" data-url="'.esc_url(admin_url('admin-ajax.php')).'" data-nonce="'.esc_attr(wp_create_nonce('fwf_backup_media')).'"><legend>Isi komponen Media / uploads</legend><p><label><input type="radio" name="'.esc_attr($prefix).'_media_mode" value="all" '.checked($ids===null,true,false).'> Semua uploads</label> <label><input type="radio" name="'.esc_attr($prefix).'_media_mode" value="selected" '.checked($ids!==null,true,false).'> Media/report terpilih</label></p><input type="hidden" name="'.esc_attr($prefix).'_media_ids" value="'.esc_attr($ids===null?'':implode(',',$ids)).'"><p><button type="button" class="button" data-fwf-media-choose>Pilih dari Media Library</button></p><p data-fwf-media-summary aria-live="polite">'.($ids===null?'Semua uploads.':esc_html(count($ids).' media utama dipilih: '.implode(', ',$ids))).'</p><p>Media terpilih mencakup file asli, thumbnail/ukuran edit, cover terkait dan metadata WordPress. Database seluruh situs harus tidak dipilih. Metadata plugin pihak ketiga tidak ikut. Untuk restore terpilih, backup keselamatan mencakup database dan uploads seluruh situs.</p><noscript><p>Pemilihan media memerlukan JavaScript. Pilihan semua uploads tetap dapat digunakan.</p></noscript></fieldset>';
    }
    private function backup(): void {
        $manager=\FalconWF\Backup\Manager::class;$dir=$manager::directory();
        echo '<p>Backup lokal tersimpan di folder privat pada server situs ini; Download ZIP menyimpan salinannya ke komputer Anda.</p>';
        echo '<p>Fondasi local/staging: ZIP tanpa password, pemulihan pada instalasi dan versi yang sama. Akun, credential server, konfigurasi server, cache dan pilihan theme/plugin aktif dipertahankan. Ukuran backup mengikuti kapasitas disk dan sumber daya server; tidak memakai batas tetap 64 MiB.</p>';
        if (is_wp_error($dir)) { echo '<p>'.esc_html($dir->get_error_message()).'</p>'; return; }
        $this->recoveryPanel();$scheduler=\FalconWF\Backup\Scheduler::class;$schedule=$scheduler::policy();$state=get_option('fwf_backup_schedule_state',[]);
        echo '<section class="fwf-panel"><h2>Backup berkala</h2><p>Pilih jadwal dan isi backup. Zona waktu mengikuti pengaturan WordPress saat jadwal disimpan; saat ini <strong>'.esc_html(wp_timezone_string()).'</strong>. Simpan ulang jadwal setelah mengubah zona waktu situs.</p>';
        $this->start('save_backup_schedule','backup');echo '<input type="hidden" name="revision" value="'.esc_attr($scheduler::revision()).'"><p><label><input type="checkbox" name="enabled" value="yes" '.checked($schedule['enabled'],true,false).'> Aktifkan backup berkala</label></p><p><label for="fwf-backup-frequency">Frekuensi</label><br><select id="fwf-backup-frequency" name="frequency">';
        foreach (['daily'=>'Harian','weekly'=>'Mingguan'] as $value=>$label) { echo '<option value="'.esc_attr($value).'" '.selected($schedule['frequency'],$value,false).'>'.esc_html($label).'</option>'; }echo '</select></p><p><label for="fwf-backup-weekday">Hari (untuk jadwal mingguan)</label><br><select id="fwf-backup-weekday" name="weekday">';
        foreach (['1'=>'Senin','2'=>'Selasa','3'=>'Rabu','4'=>'Kamis','5'=>'Jumat','6'=>'Sabtu','7'=>'Minggu'] as $value=>$label) { echo '<option value="'.esc_attr($value).'" '.selected($schedule['weekday'],(string)$value,false).'>'.esc_html($label).'</option>'; }echo '</select></p><p><label for="fwf-backup-time">Jam backup</label><br><input id="fwf-backup-time" type="time" name="time" value="'.esc_attr($schedule['time']).'" required></p><fieldset><legend>Komponen backup berkala</legend>';
        foreach ($manager::components() as $value=>$label) { echo '<p><label><input type="checkbox" name="scheduled_components[]" value="'.esc_attr($value).'" '.checked(in_array($value,$schedule['components'],true),true,false).'> '.esc_html($label).'</label></p>'; }echo '</fieldset>';$this->mediaPicker('scheduled',$schedule['media_selection']??null);$this->end('Simpan jadwal');
        $next=$schedule['enabled'] && !get_option('fwf_backup_schedule_paused',false) && empty($state['blocked'])?wp_next_scheduled($scheduler::HOOK,[$schedule['generation'],$state['next']??0]):false;
        echo '<p>Jadwal berikutnya: <strong>'.($next?esc_html(wp_date('d M Y · H:i',$next,new \DateTimeZone($schedule['timezone'])).' ('.$schedule['timezone'].')'):'Belum aktif / belum terjadwal').'</strong>.</p><p>Jika terlambat, cukup satu backup susulan. Tugas aktif tidak ditumpuk; alasannya dicatat. Backup berkala memakai retensi dan perlindungan arsip yang sama. WP-Cron bergantung kunjungan; untuk situs sepi, gunakan cron server.</p>';
        $scheduleError=$state['blocked']??get_option('fwf_backup_schedule_error','');if ($scheduleError==='') { $scheduleError=get_option('fwf_backup_schedule_error',''); }if ($scheduleError!=='') { echo '<p>'.esc_html($scheduleError).'</p>'; }
        $runs=$scheduler::runs();if ($runs) { echo '<h3>Riwayat jadwal terbaru</h3><ul>';foreach (array_slice($runs,0,5) as $run) { $label=['starting'=>'Mulai — hasil belum diketahui','queued'=>'Dalam antrean','done'=>'Berhasil','failed'=>'Gagal','skipped'=>'Dilewati','cancelled'=>'Dibatalkan'][$run['status']]??$run['status'];echo '<li>'.esc_html(wp_date('d M Y · H:i',$run['at']).' — '.$label.($run['error']!==''?' · '.$run['error']:''));if ($run['job']!=='') { echo '<br><code>'.esc_html($run['job']).'</code>'; }echo '</li>'; }echo '</ul>'; }echo '</section>';
        $policy=$manager::retention();$archives=$manager::listing();$protected=array_filter($archives,static fn($item)=>$item['protected']);
        echo '<h2>Retensi backup di server</h2><p>'.count($archives).' arsip · '.count($protected).' terlindungi · total '.esc_html(size_format(array_sum(array_column($archives,'size')),2)).'. Arsip terlindungi tidak masuk batas jumlah, tetapi tetap memakai ruang disk.</p>';
        $this->start('save_backup_retention','backup');echo '<input type="hidden" name="revision" value="'.esc_attr($manager::retentionRevision()).'"><p><label><input type="checkbox" name="enabled" value="yes" '.checked($policy['enabled'],true,false).'> Aktifkan penghapusan otomatis arsip lama</label></p><p><label for="fwf-backup-limit">Simpan maksimal backup biasa (tidak terlindungi)</label><br><input id="fwf-backup-limit" name="limit" type="number" min="1" max="1000" value="'.(int)$policy['limit'].'" required></p><p>Setelah backup baru berhasil dan lolos pemeriksaan, arsip biasa paling lama dihapus permanen sampai batas terpenuhi. Backup gagal tidak menghapus arsip lama. Menyimpan pengaturan ini belum menghapus file. Backup keselamatan sebelum restore tidak memicu penghapusan.</p>';$this->end('Simpan retensi');
        $last=get_option('fwf_backup_retention_result',false);if (is_array($last)) { echo '<p>Retensi terakhir: '.(int)$last['deleted'].' arsip dihapus'.($last['warning']!==''?' · '.esc_html($last['warning']):'').'.</p>'; }
        $estimates=$manager::estimates();
        if (is_wp_error($estimates)) { echo '<p>'.esc_html($estimates->get_error_message()).'</p>'; return; }
        echo '<p>Estimasi ukuran sebelum kompresi. Ukuran ZIP akhir bisa berbeda; angka database berupa perkiraan encoding, termasuk overhead. File media/plugin/theme dihitung dari berkas yang masuk backup.</p>';
        $this->start('create_backup','backup');
        foreach ($manager::components() as $key=>$label) { echo '<p><label><input type="checkbox" name="components[]" value="'.esc_attr($key).'" data-bytes="'.esc_attr($estimates[$key]['bytes']).'" checked> '.esc_html($label).' — <strong data-fwf-component-size>'.esc_html(size_format($estimates[$key]['bytes'],2)).'</strong><span data-fwf-component-files>'.($estimates[$key]['files']?' ('.esc_html($estimates[$key]['files']).' file)':'').'</span>'.'</label></p>'; }
        $this->mediaPicker('manual');
        echo '<p><strong>Total estimasi pilihan: <output aria-live="polite" data-fwf-backup-total>'.esc_html(size_format($manager::selectedSize($estimates,array_keys($manager::components())),2)).'</output></strong></p><p data-fwf-settings-included>Pengaturan sudah termasuk database; tidak dihitung dua kali.</p><noscript><p>Tanpa JavaScript, total di atas adalah semua komponen bawaan; ubah pilihan lalu jalankan backup untuk pemeriksaan disk aktual.</p></noscript>';
        echo '<p><label for="fwf-backup-note">Catatan backup (opsional)</label><br><textarea id="fwf-backup-note" name="backup_note" maxlength="1000" rows="2" placeholder="Contoh: sebelum update desain beranda"></textarea></p>';
        $this->end('Mulai backup latar belakang');
        echo '<h2>Tugas backup</h2><p>Progres tersimpan. WP-Cron menjalankan antrean saat situs menerima kunjungan; untuk situs sepi, gunakan cron server. Snapshot database diambil dalam satu proses; bila terputus, tahap snapshot diulang. Setelah snapshot selesai, ZIP dilanjutkan per bagian. Restore tetap proses konfirmasi terpisah.</p>';
        $jobs=$manager::jobs();
        if (is_wp_error($jobs)) { echo '<p>'.esc_html($jobs->get_error_message()).'</p>'; }
        else {
            echo '<div data-fwf-backup-jobs data-labels="'.esc_attr(wp_json_encode($manager::phases())).'" data-url="'.esc_url(admin_url('admin-ajax.php')).'" data-nonce="'.esc_attr(wp_create_nonce('fwf_backup_jobs')).'">';
            foreach ($jobs as $job) {
                echo '<section data-job-id="'.esc_attr($job['id']).'"><p><code>'.esc_html($job['id']).'</code> — <span data-job-status data-phase="'.esc_attr($job['phase']).'">'.esc_html($manager::phases()[$job['phase']]??$job['phase']).($job['total']?' · '.(int)$job['cursor'].' / '.(int)$job['total'].' berkas':'').'</span></p><p data-job-error>'.esc_html($job['error']).'</p>';
                if (!in_array($job['phase'],['done','cancelled'],true)) { foreach (['resume_backup'=>'Lanjutkan / coba lagi','cancel_backup'=>'Batalkan tugas'] as $op=>$label) { $this->start($op,'backup');echo '<input type="hidden" name="job_id" value="'.esc_attr($job['id']).'">';$this->end($label); } }
                echo '</section>';
            }echo '</div>';
        }
        echo '<p>Batas upload ZIP melalui browser saat ini: <strong>'.esc_html(size_format(wp_max_upload_size(),2)).'</strong> (konfigurasi server WordPress/PHP). Backup lokal tidak memakai batas upload ini. Untuk ZIP yang lebih besar, batas upload server perlu disesuaikan. Proses tetap mengikuti waktu eksekusi dan ruang disk server.</p>';
        $this->start('import_backup','backup',true);echo '<p><label for="fwf-backup-file">Import ZIP backup dari komputer (instalasi ini)</label><br><input id="fwf-backup-file" type="file" name="backup_file" accept=".zip" required></p>';$this->end('Import dan periksa ZIP');
        $review=get_transient('fwf_backup_review_'.get_current_user_id());
        if ($review) {
            echo '<section><h2>Review pemulihan</h2><p>Dibuat '.esc_html($review['manifest']['created']).'; komponen: '.esc_html(implode(', ',$review['manifest']['components'])).'; '.count($review['manifest']['entries']).' entries. File yang sudah ada ditimpa; file tambahan tidak dihapus. Database/settings mengganti data yang didukung.</p>';
            $this->start('restore_backup','backup');echo '<input type="hidden" name="backup_id" value="'.esc_attr($review['id']).'"><p><label><input type="checkbox" name="confirm" value="yes" required> Saya menyetujui pemulihan data yang direview. FWF membuat backup keselamatan sebelum menulis.</label></p>';$this->end('Pulihkan backup yang direview');echo '</section>';
        }
        $deletion=get_transient('fwf_backup_delete_review_'.get_current_user_id());
        if ($deletion) {
            echo '<section><h2>Konfirmasi Delete</h2><p>File backup ini akan dihapus permanen dari server. Konten situs tidak dihapus.</p><p>'.esc_html(wp_date('d M Y · H:i',$deletion['time'])).'<br><code>'.esc_html($deletion['id']).'.zip</code> ('.esc_html(size_format($deletion['size'],2)).')</p>';
            $this->start('delete_backup','backup');echo '<input type="hidden" name="backup_id" value="'.esc_attr($deletion['id']).'"><p><label><input type="checkbox" name="confirm" value="yes" required> Hapus permanen file backup ini.</label></p>';$this->end('Delete permanen');
            $this->start('cancel_delete_backup','backup');$this->end('Batal hapus');echo '</section>';
        }
        echo '<h2>Backup tersimpan</h2>';
        foreach ($manager::listing() as $id=>$item) {
            echo '<section class="fwf-backup-card" data-backup-id="'.esc_attr($id).'"><p class="fwf-backup-summary"><strong>'.esc_html(wp_date('d M Y · H:i',$item['time'])).'</strong>'.($item['protected']?' <span aria-label="Backup terlindungi" title="Backup terlindungi">🔒 Terlindungi</span>':'').'<br><code>'.esc_html($id).'.zip</code> ('.esc_html(size_format($item['size'],2)).')</p>';
            echo '<div class="fwf-backup-actions">';
            foreach (['download_backup'=>'Download ZIP','review_backup'=>'Restore','review_delete_backup'=>'Delete'] as $op=>$label) { $this->start($op,'backup');echo '<input type="hidden" name="backup_id" value="'.esc_attr($id).'">';if ($op==='review_delete_backup' && $item['protected']) { submit_button($label,'secondary','submit',true,['disabled'=>true,'title'=>'Lepas perlindungan terlebih dahulu']);echo '</form>'; }else { $this->end($label); } }
            $op=$item['protected']?'unprotect_backup':'protect_backup';$this->start($op,'backup');echo '<input type="hidden" name="backup_id" value="'.esc_attr($id).'">';$this->end($item['protected']?'Lepas Perlindungan':'Lindungi backup ini');
            echo '</div><details><summary>Catatan admin / klien'.($item['note']!==''?' — '.esc_html(wp_trim_words((string)$item['note'],12,'…')):' — tambah catatan').'</summary>';
            $this->start('save_backup_note','backup');echo '<input type="hidden" name="backup_id" value="'.esc_attr($id).'"><p><label for="fwf-note-'.esc_attr($id).'">Catatan backup (maks. 1000 karakter)</label><br><textarea id="fwf-note-'.esc_attr($id).'" name="backup_note" maxlength="1000" rows="3">'.esc_textarea((string)$item['note']).'</textarea></p>';$this->end('Simpan catatan');echo '</details></section>';
        }
        echo '<p>Storage cloud masih tahap berikutnya. Tidak mengirim backup ke Google Drive pada tahap ini.</p>';
    }
    private function seo(): void {
        echo '<section><h2>SEO / GEO dasar</h2>';
        if (get_template()!=='falcon-theme' || !class_exists('FalconTheme\\Seo')) { echo '<p>Pengaturan tersedia saat Falcon Theme atau child theme aktif.</p></section>'; return; }
        $this->start('save_seo','identity');
        echo '<input type="hidden" name="revision" value="'.esc_attr(\FalconTheme\Seo::revision()).'">';
        echo '<p><label for="fwf-seo-mode">Pemilik metadata tambahan</label><br><select id="fwf-seo-mode" name="seo_mode">';
        foreach (['external'=>'WordPress / plugin SEO lain (bawaan)','falcon'=>'Falcon: description, Open Graph dan schema dasar'] as $mode=>$label) { echo '<option value="'.esc_attr($mode).'" '.selected(\FalconTheme\Seo::config()['mode'],$mode,false).'>'.esc_html($label).'</option>'; }
        echo '</select></p><p>Judul, robots dan sitemap mengikuti WordPress. Deskripsi memakai Ringkasan (Excerpt) publik, tanpa menyalin custom fields. Coming-soon, situs private, draft dan halaman berpassword tidak menerima metadata Falcon.</p>';
        if (\FalconTheme\Seo::delegated()) { echo '<p>Plugin SEO lain terdeteksi: keluaran Falcon ditangguhkan untuk menghindari metadata ganda.</p>'; }
        echo '<p>Plugin SEO lain yang belum terdeteksi: gunakan mode bawaan. Tidak menjamin ranking atau kemunculan jawaban AI.</p>';
        $this->end('Simpan SEO'); echo '</section>';
    }
    private function design(): void {
        if (get_template()!=='falcon-theme' || !class_exists('FalconTheme\\Design')) { echo '<p>Desain Global memerlukan Falcon Theme yang kompatibel atau child theme-nya aktif. Pilih melalui <a href="'.esc_url(admin_url('themes.php')).'">Appearance → Themes</a>.</p>'; return; }
        echo '<p>Pengaturan untuk '.esc_html(wp_get_theme()->get('Name')).'. Berlaku pada seluruh halaman yang memakai token desain Falcon. Kosongkan nilai untuk mengikuti desain bawaan theme. Font memakai font lokal perangkat.</p>';
        $this->start('save_design','design');
        echo '<input type="hidden" name="theme" value="'.esc_attr(get_stylesheet()).'"><input type="hidden" name="revision" value="'.esc_attr(\FalconTheme\Design::revision()).'">';
        $values=\FalconTheme\Design::values();
        echo '<div class="fwf-design-fields">';
        foreach (\FalconTheme\Design::fields() as $key=>$field) {
            $id='fwf-design-'.$key; echo '<p><label for="'.esc_attr($id).'">'.esc_html($field[0]).'</label><br>';
            if ($field[1]==='font') {
                echo '<select id="'.esc_attr($id).'" name="design['.esc_attr($key).']"><option value="">Ikuti desain bawaan</option>';
                foreach (\FalconTheme\Design::fonts() as $font=>[$label]) { echo '<option value="'.esc_attr($font).'" '.selected($values[$key]??'',$font,false).'>'.esc_html($label).'</option>'; } echo '</select>';
            } else {
                echo '<input id="'.esc_attr($id).'" name="design['.esc_attr($key).']" value="'.esc_attr($values[$key]??'').'" type="'.($field[1]==='number'?'number':'text').'"'.($field[1]==='number'?' min="'.esc_attr($field[2]).'" max="'.esc_attr($field[3]).'" step="0.01"':' placeholder="#RRGGBB" pattern="#[a-fA-F0-9]{6}" maxlength="7"').'>'.($field[1]==='number'?' <span>Rentang '.esc_html($field[2].'–'.$field[3]).'</span>':'');
            }
            echo '</p>';
        }
        echo '</div>';
        echo '<p>Ukuran judul menyesuaikan layar kecil hingga batas maksimum yang dipilih. H1–H6 tetap ditentukan oleh struktur konten, bukan ukuran huruf. Mengosongkan semua field lalu menyimpan mengembalikan desain bawaan.</p>';
        submit_button('Simpan desain');
        echo '<p><button type="submit" class="button button-secondary" name="reset_design" value="yes" formnovalidate>Kembalikan desain bawaan</button></p></form>';
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
        $caps=['review_recovery'=>'fwf_manage_system','apply_recovery'=>'fwf_manage_system','save_backup_schedule'=>'fwf_manage_system','save_backup_retention'=>'fwf_manage_system','protect_backup'=>'fwf_manage_system','unprotect_backup'=>'fwf_manage_system','cancel_delete_backup'=>'fwf_manage_system','save_backup_note'=>'fwf_manage_system','review_delete_backup'=>'fwf_manage_system','delete_backup'=>'fwf_manage_system','resume_backup'=>'fwf_manage_system','cancel_backup'=>'fwf_manage_system','create_backup'=>'fwf_manage_system','review_backup'=>'fwf_manage_system','restore_backup'=>'fwf_manage_system','import_backup'=>'fwf_manage_system','download_backup'=>'fwf_manage_system','save_seo'=>'fwf_manage_system','save_repo'=>'fwf_manage_connections','check_updates'=>'fwf_manage_updates','apply_update'=>'fwf_manage_updates','install_theme'=>'fwf_manage_system','activate_theme'=>'fwf_manage_system','skip_setup'=>'fwf_manage_system','save_project'=>'fwf_manage_connections','disconnect_project'=>'fwf_manage_connections','check_project'=>'fwf_manage_updates','apply_project'=>'fwf_manage_updates','save_design'=>'fwf_manage_system','save_identity'=>'fwf_manage_system','save_modules'=>'fwf_manage_modules','save_maintenance'=>'fwf_manage_system','save_outbound'=>'fwf_manage_ai','disconnect_outbound'=>'fwf_manage_ai','suggest'=>'fwf_manage_ai','apply_proposal'=>'fwf_manage_ai','grant_agent'=>'fwf_manage_ai','revoke_agent'=>'fwf_manage_ai'];
        if (!isset($caps[$op]) || !current_user_can($caps[$op])) { Logger::write('admin_denied','FWF_PERMISSION'); wp_die('Tidak diizinkan.', '', ['response'=>403]); }
        check_admin_referer('fwf_'.$op);
        $p=wp_unslash($_POST); $result=true; $proposal='';
        $audit=Logger::write($op,'started'); if (is_wp_error($audit)) { $result=$audit; }
        else {
            switch ($op) {
                case 'review_recovery':
                    $result=\FalconWF\Backup\Manager::recovery($p['journal_id']??null);delete_transient('fwf_backup_recovery_review_'.get_current_user_id());if (!is_wp_error($result)) { set_transient('fwf_backup_recovery_review_'.get_current_user_id(),$result,10*MINUTE_IN_SECONDS); }break;
                case 'apply_recovery':
                    $recovery=get_transient('fwf_backup_recovery_review_'.get_current_user_id());delete_transient('fwf_backup_recovery_review_'.get_current_user_id());$result=is_array($recovery) && ($recovery['id']??'')===($p['journal_id']??null)?\FalconWF\Backup\Manager::recovery($recovery['id'],$recovery['review'],($p['confirm']??'')==='yes'):new \WP_Error('FWF_BACKUP_RECOVERY','Review pemulihan sudah kedaluwarsa.');break;
                case 'create_backup': $result=\FalconWF\Backup\Manager::queue($p['components']??null,true,$p['backup_note']??'',($p['manual_media_mode']??'all')==='selected'?($p['manual_media_ids']??''):null);break;
                case 'resume_backup': $result=\FalconWF\Backup\Manager::control($p['job_id']??null);break;
                case 'cancel_backup': $result=\FalconWF\Backup\Manager::control($p['job_id']??null,true);break;
                case 'save_backup_schedule': $result=\FalconWF\Backup\Scheduler::save(['enabled'=>($p['enabled']??'')==='yes','frequency'=>$p['frequency']??null,'weekday'=>$p['weekday']??null,'time'=>$p['time']??null,'components'=>$p['scheduled_components']??null,'media_selection'=>($p['scheduled_media_mode']??'all')==='selected'?($p['scheduled_media_ids']??''):null],$p['revision']??null);break;
                case 'save_backup_retention': $result=\FalconWF\Backup\Manager::saveRetention(($p['enabled']??'')==='yes',$p['limit']??null,$p['revision']??null);break;
                case 'protect_backup': $result=\FalconWF\Backup\Manager::protect($p['backup_id']??null,true);break;
                case 'unprotect_backup': $result=\FalconWF\Backup\Manager::protect($p['backup_id']??null,false);break;
                case 'cancel_delete_backup': delete_transient('fwf_backup_delete_review_'.get_current_user_id());break;
                case 'save_backup_note': $result=\FalconWF\Backup\Manager::saveNote($p['backup_id']??null,$p['backup_note']??null);break;
                case 'review_delete_backup':
                    $result=\FalconWF\Backup\Manager::reviewRemoval($p['backup_id']??null);delete_transient('fwf_backup_delete_review_'.get_current_user_id());
                    if (!is_wp_error($result)) { set_transient('fwf_backup_delete_review_'.get_current_user_id(),$result,10*MINUTE_IN_SECONDS); }break;
                case 'delete_backup':
                    $deletion=get_transient('fwf_backup_delete_review_'.get_current_user_id());delete_transient('fwf_backup_delete_review_'.get_current_user_id());
                    $result=is_array($deletion) && ($deletion['id']??'')===($p['backup_id']??null)?\FalconWF\Backup\Manager::remove($deletion['id'],$deletion['sha256'],($p['confirm']??'')==='yes'):new \WP_Error('FWF_BACKUP','Review penghapusan sudah kedaluwarsa.');break;
                case 'review_backup':
                    $result=\FalconWF\Backup\Manager::inspect($p['backup_id']??null);
                    delete_transient('fwf_backup_review_'.get_current_user_id());
                    if (!is_wp_error($result)) { set_transient('fwf_backup_review_'.get_current_user_id(),$result,10*MINUTE_IN_SECONDS); }break;
                case 'import_backup':
                    $file=$_FILES['backup_file']??null;
                    $result=is_array($file) && ($file['error']??-1)===UPLOAD_ERR_OK && is_string($file['tmp_name']??null) && is_uploaded_file($file['tmp_name'])?\FalconWF\Backup\Manager::import($file['tmp_name']):new \WP_Error('FWF_BACKUP','Upload ZIP tidak valid.');break;
                case 'restore_backup':
                    $review=get_transient('fwf_backup_review_'.get_current_user_id());delete_transient('fwf_backup_review_'.get_current_user_id());
                    $result=is_array($review) && ($review['id']??'')===($p['backup_id']??null)?\FalconWF\Backup\Manager::restore($review['id'],$review['sha256'],$review['revision'],($p['confirm']??'')==='yes'):new \WP_Error('FWF_BACKUP','Review pemulihan sudah kedaluwarsa.');break;
                case 'download_backup':
                    $result=\FalconWF\Backup\Manager::path($p['backup_id']??null);
                    if (!is_wp_error($result)) { nocache_headers();header('Content-Type: application/zip');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="falcon-backup-'.basename($result).'"');header('Content-Length: '.filesize($result));readfile($result);exit; }break;
                case 'save_project': $result=ProjectManager::save(['repo'=>$p['project_repo']??null,'project_id'=>$p['project_id']??null,'theme_id'=>$p['theme_id']??null,'tag'=>$p['project_tag']??null]); break;
                case 'disconnect_project': delete_option('fwf_project_connection');delete_option('fwf_project_candidate'); break;
                case 'check_project': $result=(new ProjectManager())->check(); break;
                case 'apply_project': $result=(new ProjectManager())->apply(($p['backup']??'')==='yes'); break;
                case 'save_repo':
                    $repo=\FalconWF\Updates\GitHubClient::normalizeRepo($p['repo']??null);
                    $tag=is_string($p['release_tag']??'')?trim($p['release_tag']??''):null;
                    $selection=$tag===null?new \WP_Error('FWF_VALIDATION','Tag harus berupa teks.'):\FalconWF\Updates\UpdateManager::validateSelection($tag);
                    if (!\FalconWF\Updates\GitHubClient::validRepo($repo)) { $result=new \WP_Error('FWF_VALIDATION','Gunakan URL https://github.com/owner/repo atau owner/repo tanpa token.'); } elseif(is_wp_error($selection)) { $result=$selection; } else { update_option('fwf_update_tag',$tag,false); update_option('fwf_repo',$repo,false); delete_option('fwf_release_candidate'); } break;
                case 'check_updates': $result=(new \FalconWF\Updates\UpdateManager())->check(); break;
                case 'apply_update': $result=(new \FalconWF\Updates\UpdateManager())->update(sanitize_key($p['package_id']??''),($p['backup']??'')==='yes'); break;
                case 'install_theme': $result=(new ThemeInstaller(dirname($this->file)))->install(); break;
                case 'activate_theme': $result=($p['confirm']??'')==='yes'?(new ThemeInstaller(dirname($this->file)))->activate():new \WP_Error('FWF_PERMISSION','Konfirmasi pergantian theme diperlukan.'); break;
                case 'skip_setup': update_option('fwf_setup','skipped',false); break;
                case 'save_design': $result=Settings::saveDesign(($p['reset_design']??'')==='yes'?[]:($p['design']??null),$p['theme']??null,$p['revision']??null); break;
                case 'save_seo': $result=Settings::saveSeo($p['seo_mode']??null,$p['revision']??null); break;
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
