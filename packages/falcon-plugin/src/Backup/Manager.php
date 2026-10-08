<?php
namespace FalconWF\Backup;
use FalconWF\Packages\Lock;
/** Bounded, same-installation recovery. Packages live outside the web root. */
final class Manager {
    public static function components(): array { return ['database'=>'Database konten situs (akun tetap)','media'=>'Media / uploads','plugins'=>'Berkas plugin','themes'=>'Berkas theme','settings'=>'Pengaturan FWF dan Reading']; }
    public static function directory(): string|\WP_Error {
        if (!defined('FWF_BACKUP_DIR') || !is_string(FWF_BACKUP_DIR)) { return new \WP_Error('FWF_BACKUP_STORAGE','Konfigurasikan FWF_BACKUP_DIR di luar folder publik WordPress.'); }
        $path=realpath(FWF_BACKUP_DIR);$web=realpath(ABSPATH);
        if (!$path || !$web || is_link(FWF_BACKUP_DIR) || $path===$web || str_starts_with($path,$web.DIRECTORY_SEPARATOR) || !is_writable($path) || (fileperms($path)&0077)!==0) { return new \WP_Error('FWF_BACKUP_STORAGE','Folder backup harus private (0700), writable, dan di luar WordPress.'); }
        return $path;
    }
    private static function fail(string $message): never { throw new \RuntimeException($message); }
    private static function allowed(): void { if (!current_user_can('fwf_manage_system') || is_multisite()) { self::fail('Tidak diizinkan atau multisite tidak didukung.'); } }
    private static function site(): string { return hash_hmac('sha256',home_url('/'),wp_salt('auth')); }
    private static function roots(): array { return ['media'=>wp_get_upload_dir()['basedir'],'plugins'=>WP_PLUGIN_DIR,'themes'=>get_theme_root()]; }
    private static function setting(string $key): bool {
        return in_array($key,['blogname','blogdescription','blog_public','show_on_front','page_on_front','page_for_posts','fwf_identity','fwf_builder','fwf_modules','fwf_seo'],true) || (bool)preg_match('/^fwf_design_[a-z0-9_-]+$/D',$key);
    }
    private static function volatile(string $key): bool {
        return str_starts_with($key,'_transient_') || str_starts_with($key,'_site_transient_') || str_starts_with($key,'fwf_lock_') || str_starts_with($key,'fwf_backup_') || str_starts_with($key,'fwf_agent_') || str_starts_with($key,'fwf_operation_') || str_starts_with($key,'fwf_request_') || str_starts_with($key,'fwf_test_') || str_ends_with($key,'user_roles') || str_starts_with($key,'fwf_proposal_') || str_starts_with($key,'fwf_rate_') || in_array($key,['active_plugins','stylesheet','template','current_theme','theme_switched','home','siteurl','cron','rewrite_rules','fwf_outbound','fwf_ai_budget','fwf_repo','fwf_update_tag','fwf_update_channel','fwf_setup','fwf_agent_scopes','fwf_project_connection','fwf_project_candidate','fwf_release_candidate'],true);
    }
    private static function tables(): array {
        global $wpdb;
        $tables=$wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($wpdb->prefix).'%'));
        // Accounts, authentication and operational audit are deliberately not rewindable through this UI.
        if (!$tables || $wpdb->last_error || !in_array($wpdb->options,$tables,true)) { self::fail('Pembacaan tabel situs gagal.'); }
        $tables=array_values(array_diff($tables,[$wpdb->users,$wpdb->usermeta,$wpdb->prefix.'fwf_audit']));sort($tables);
        foreach ($tables as $table) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/D',$table)) { self::fail('Nama tabel tidak didukung.'); }
            $engine=$wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
            $foreign=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=%s AND CONSTRAINT_TYPE='FOREIGN KEY'",$table));
            if ($engine!=='InnoDB' || (int)$foreign>0) { self::fail('Backup database memerlukan InnoDB tanpa foreign key.'); }
        }
        return $tables;
    }
    private static function rows(string $table): \Generator {
        global $wpdb;
        for ($offset=0;;$offset+=100) {
            $rows=$wpdb->get_results("SELECT * FROM `$table` LIMIT 100 OFFSET $offset",ARRAY_A);
            if ($wpdb->last_error) { self::fail('Pembacaan database gagal.'); }
            foreach ($rows as $row) { if ($table!==$wpdb->options || !self::volatile($row['option_name'])) { yield $row; } }
            if (count($rows)<100) { break; }
        }
    }
    private static function data(array $components, ?string $directory=null, ?array $media=null): array {
        global $wpdb;$data=[];
        foreach (in_array('database',$components,true)?self::tables():[] as $table) {
            $columns=$wpdb->get_results("SHOW FULL COLUMNS FROM `$table`",ARRAY_A);
            foreach (array_column($columns,'Field') as $field) { if (!preg_match('/^[a-zA-Z0-9_]+$/D',$field)) { self::fail('Kolom tabel tidak didukung.'); } }
            $entry='rows/'.$table.'.jsonl';$stream=$directory?fopen($directory.'/'.$table.'.jsonl','xb'):null;
            if ($directory && !$stream) { self::fail('Staging database gagal.'); }
            $hash=hash_init('sha256');$count=0;$size=0;
            try {
                foreach (self::rows($table) as $row) {
                    $line=wp_json_encode(array_map(static fn($v)=>$v===null?null:base64_encode($v),$row),JSON_THROW_ON_ERROR)."\n";
                    hash_update($hash,$line);$count++;$size+=strlen($line);
                    if ($stream && fwrite($stream,$line)!==strlen($line)) { self::fail('Penulisan staging database gagal.'); }
                }
            } finally { if ($stream) { fclose($stream); } }
            $data[$table]=['columns'=>$columns,'rows_file'=>$entry,'sha256'=>hash_final($hash),'count'=>$count,'size'=>$size];
        }
        if (!in_array('database',$components,true) && in_array('settings',$components,true)) {
            $data['settings']=[];foreach (self::rows($wpdb->options) as $row) { if (self::setting($row['option_name'])) { $data['settings'][]=array_intersect_key($row,array_flip(['option_name','option_value','autoload'])); } }
        }
        if ($media!==null) { $data['attachments']=MediaSelection::snapshot($media,false)['records']; }
        return $data;
    }
    private static function space(string $directory, float $needed): void {
        $free=disk_free_space($directory);
        if ($free===false || $free<$needed+32*1024*1024) { self::fail('Ruang disk tidak cukup untuk backup/staging/recovery yang dipilih.'); }
    }
    public static function estimates(?array $media=null): array|\WP_Error {
        try {
            self::allowed();global $wpdb;$result=[];
            foreach (self::components() as $key=>$label) { $result[$key]=['bytes'=>0,'files'=>0]; }
            foreach (self::files(['media','plugins','themes'],false) as $name=>$file) { $key=explode('/',$name,2)[0];$result[$key]['bytes']+=$file['size'];$result[$key]['files']++; }
            foreach (self::tables() as $table) {
                $columns=$wpdb->get_col("SHOW COLUMNS FROM `$table`",0);
                foreach ($columns as $column) { if (!preg_match('/^[a-zA-Z0-9_]+$/D',$column)) { self::fail('Kolom tabel tidak didukung.'); } }
                $expression=implode('+',array_map(static fn($field)=>"COALESCE(OCTET_LENGTH(`$field`),0)",$columns));
                $row=$wpdb->get_row("SELECT COALESCE(SUM($expression),0) AS bytes,COUNT(*) AS rows_count FROM `$table`",ARRAY_A);
                if ($wpdb->last_error || !$row) { self::fail('Estimasi database gagal.'); }
                $result['database']['bytes']+=(int)ceil((int)$row['bytes']*4/3)+(int)$row['rows_count']*(count($columns)*24+4);
            }
            $result['settings']['bytes']=strlen(wp_json_encode(self::data(['settings'])));
            if ($media!==null) { $selected=MediaSelection::snapshot($media,false);$result['media']=['bytes'=>$selected['bytes'],'files'=>$selected['count']]; }
            return $result;
        } catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
    }
    public static function selectedSize(array $estimates, array $components): int {
        $total=0;foreach ($components as $key) { if ($key==='settings' && in_array('database',$components,true)) { continue; }$total+=(int)($estimates[$key]['bytes']??0); }return $total;
    }
    private static function files(array $components, bool $hash=true, ?array $media=null): array {
        $files=[];
        foreach (self::roots() as $component=>$root) {
            if (!in_array($component,$components,true) || !is_dir($root) || ($component==='media' && $media!==null)) { continue; }
            if (is_link($root)) { self::fail('Symlink root ditolak.'); }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isLink()) { self::fail('Symlink ditolak.'); }if (!$file->isFile()) { continue; }
                $relative=str_replace(DIRECTORY_SEPARATOR,'/',substr($file->getPathname(),strlen($root)+1));
                if (preg_match('~(^|/)(\.env(?:\.[^/]*)?|wp-config\.php|debug\.log|\.git)(/|$)~',$relative)) { continue; }
                if (!self::safe($relative)) { self::fail('Path backup tidak valid.'); }
                $files[$component.'/'.$relative]=['path'=>$file->getPathname(),'sha256'=>$hash?hash_file('sha256',$file->getPathname()):null,'size'=>$file->getSize(),'mode'=>fileperms($file->getPathname())&0777];
            }
        }
        if ($media!==null) { $files=array_filter($files,static fn($key)=>!str_starts_with($key,'media/'),ARRAY_FILTER_USE_KEY)+MediaSelection::snapshot($media,$hash)['files']; }
        ksort($files);return $files;
    }
    private static function safe(string $path): bool { return $path!=='' && !str_contains($path,'\\') && !str_contains($path,"\0") && !preg_match('~(^/|^[A-Za-z]:|(^|/)(\.|\.\.)(/|$)|//)~',$path); }
    public static function listing(): array {
        $dir=self::directory();if (is_wp_error($dir)) { return []; }$result=[];
        foreach (glob($dir.'/'.'*.zip')?:[] as $path) { $id=basename($path,'.zip');if (preg_match('/^[a-f0-9-]{36}$/D',$id) && !is_link($path)) { $result[$id]=['size'=>filesize($path),'time'=>filemtime($path),'note'=>get_option('fwf_backup_note_'.$id,''),'protected'=>(bool)get_option('fwf_backup_protected_'.$id,false)]; } }
        uasort($result,static fn($a,$b)=>$b['time']<=>$a['time']);return $result;
    }
    public static function path(mixed $id): string|\WP_Error {
        $dir=self::directory();if (is_wp_error($dir)) { return $dir; }
        if (!is_string($id) || !preg_match('/^[a-f0-9-]{36}$/D',$id) || !is_file($dir.'/'.$id.'.zip') || is_link($dir.'/'.$id.'.zip')) { return new \WP_Error('FWF_BACKUP_NOT_FOUND','Backup tidak ditemukan.'); }return $dir.'/'.$id.'.zip';
    }
    private static function validNote(mixed $note): string {
        if (!is_string($note) || strlen($note)>4000 || preg_match_all('/./us',$note)>1000 || wp_check_invalid_utf8($note)!==$note) { self::fail('Catatan maksimum 1000 karakter teks.'); }
        return sanitize_textarea_field($note);
    }
    public static function saveNote(mixed $id,mixed $note): true|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }
        try { self::allowed();$path=self::path($id);if (is_wp_error($path)) { return $path; }return self::storeNote($id,self::validNote($note)); }
        catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
        finally { Lock::release('backup',$lock); }
    }
    private static function storeNote(string $id,string $note): true {
        $key='fwf_backup_note_'.$id;update_option($key,$note,false);
        if (get_option($key)!==$note) { self::fail('Penyimpanan catatan gagal.'); }return true;
    }
    public static function reviewRemoval(mixed $id): array|\WP_Error {
        try { self::allowed();$path=self::path($id);if (is_wp_error($path)) { return $path; }return ['id'=>$id,'sha256'=>hash_file('sha256',$path),'time'=>filemtime($path),'size'=>filesize($path)]; }
        catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
    }
    public static function remove(mixed $id,mixed $hash,bool $confirm): true|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }
        try { return self::removeLocked($id,$hash,$confirm); }
        finally { Lock::release('backup',$lock); }
    }
    private static function removeLocked(mixed $id,mixed $hash,bool $confirm): true|\WP_Error {
        try {
            self::allowed();if (!$confirm || !is_string($hash)) { self::fail('Konfirmasi penghapusan file backup diperlukan.'); }
            $path=self::path($id);if (is_wp_error($path)) { return $path; }
            if (get_option('fwf_backup_protected_'.$id,false)) { self::fail('Backup terlindungi. Lepas perlindungan terlebih dahulu.'); }
            if (!hash_equals(hash_file('sha256',$path),$hash)) { self::fail('File backup berubah; periksa ulang sebelum menghapus.'); }
            $dir=self::directory();if (glob($dir.'/restore-*')) { self::fail('Journal recovery tertunda; backup dipertahankan untuk pemeriksaan.'); }
            if (is_dir($dir.'/job-'.$id)) {
                $state=self::job($id,false);if (!in_array($state['phase'],['done','failed','cancelled'],true)) { self::fail('Tugas backup ini masih aktif.'); }
                $jobDir=self::jobDirectory($id);self::cleanJob($jobDir);if (!unlink($jobDir.'/state.json') || !rmdir($jobDir)) { self::fail('Pembersihan catatan tugas gagal; ZIP belum dihapus.'); }
                wp_clear_scheduled_hook('fwf_backup_step',[$id]);
            }
            if (!unlink($path)) { self::fail('File backup gagal dihapus.'); }delete_option('fwf_backup_note_'.$id);delete_option('fwf_backup_protected_'.$id);return true;
        } catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
    }
    public static function retention(): array {
        $value=get_option('fwf_backup_retention',[]);if (!is_array($value)) { $value=[]; }
        return ['enabled'=>is_array($value) && ($value['enabled']??false)===true,'limit'=>max(1,min(1000,(int)($value['limit']??5)))];
    }
    public static function retentionRevision(): string { return hash('sha256',wp_json_encode(self::retention())); }
    public static function saveRetention(bool $enabled,mixed $limit,mixed $revision): true|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }
        try {
            self::allowed();if (!is_string($revision) || !hash_equals(self::retentionRevision(),$revision)) { self::fail('Pengaturan retensi berubah. Muat ulang halaman.'); }
            if ((!is_string($limit) && !is_int($limit)) || !preg_match('/^[0-9]+$/D',(string)$limit) || (int)$limit<1 || (int)$limit>1000) { self::fail('Jumlah backup harus 1–1000.'); }
            $value=['enabled'=>$enabled,'limit'=>(int)$limit];update_option('fwf_backup_retention',$value,false);if (get_option('fwf_backup_retention')!==$value) { self::fail('Penyimpanan retensi gagal.'); }return true;
        } catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
        finally { Lock::release('backup',$lock); }
    }
    public static function protect(mixed $id,bool $protected): true|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }
        try {
            self::allowed();$path=self::path($id);if (is_wp_error($path)) { return $path; }
            $key='fwf_backup_protected_'.$id;if ($protected) { update_option($key,true,false); }else { delete_option($key); }
            if ((bool)get_option($key,false)!==$protected) { self::fail('Penyimpanan perlindungan gagal.'); }return true;
        } catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
        finally { Lock::release('backup',$lock); }
    }
    /** Caller holds the backup lease. Only a newly validated background archive triggers pruning. */
    private static function retainLocked(string $newId): string {
        $policy=self::retention();if (!$policy['enabled']) { return ''; }
        $items=array_filter(self::listing(),static fn($item)=>!$item['protected']);
        uasort($items,static fn($a,$b)=>$a['time']<=>$b['time']);$excess=max(0,count($items)-$policy['limit']);$deleted=0;$warning='';
        foreach ($items as $id=>$item) {
            if ($deleted>=$excess) { break; }if ($id===$newId) { continue; }
            $path=self::path($id);if (is_wp_error($path)) { $warning=$path->get_error_message();break; }
            $result=self::removeLocked($id,hash_file('sha256',$path),true);
            if (is_wp_error($result)) { $warning=$result->get_error_message();break; }$deleted++;
        }
        update_option('fwf_backup_retention_result',['at'=>time(),'id'=>$newId,'deleted'=>$deleted,'warning'=>$warning],false);
        return $warning===''?'':'ZIP baru valid; retensi tertunda: '.$warning;
    }
    /** Same engine for synchronous safety backups and persisted admin jobs. */
    public static function create(mixed $components,mixed $media=null): string|\WP_Error {
        $id=self::queue($components,false,'',$media);if (is_wp_error($id)) { return $id; }
        try {
            do { $result=self::work($id);if (is_wp_error($result)) { return $result; }if ($result['phase']==='failed') { return new \WP_Error('FWF_BACKUP',$result['error']); } } while ($result['phase']!=='done');
            return $id;
        } finally { self::forget($id); }
    }
    public static function phases(): array { return ['queued'=>'Menunggu worker','snapshot'=>'Mengambil snapshot','packing'=>'Membuat ZIP','verifying'=>'Memeriksa ZIP','retaining'=>'Merapikan arsip','done'=>'Selesai','failed'=>'Terhenti — perlu diperiksa','cancelled'=>'Dibatalkan']; }
    public static function register(): void {
        add_action('fwf_backup_step',static function($id) { $result=self::work($id);if (is_wp_error($result) && $result->get_error_code()==='FWF_LOCK') { self::schedule($id,10); } });
        add_action('wp_ajax_fwf_backup_jobs',static function() {
            if (!current_user_can('fwf_manage_system')) { wp_send_json_error(['message'=>'Tidak diizinkan.'],403); }
            check_ajax_referer('fwf_backup_jobs');$jobs=self::jobs();if (is_wp_error($jobs)) { wp_send_json_error(['message'=>$jobs->get_error_message()],400); }
            wp_send_json_success(array_values($jobs));
        });
    }
    private static function jobDirectory(mixed $id): string {
        $dir=self::directory();if (is_wp_error($dir)) { self::fail($dir->get_error_message()); }
        if (!is_string($id) || !preg_match('/^[a-f0-9-]{36}$/D',$id)) { self::fail('ID tugas tidak valid.'); }
        $path=$dir.'/job-'.$id;if (!is_dir($path) || is_link($path)) { self::fail('Tugas backup tidak ditemukan.'); }return $path;
    }
    private static function job(mixed $id,bool $compatible=true): array {
        $dir=self::jobDirectory($id);if (is_link($dir.'/state.json')) { self::fail('State tugas tidak valid.'); }
        $state=json_decode(file_get_contents($dir.'/state.json'),true,512,JSON_THROW_ON_ERROR);
        if (($state['id']??null)!==$id || ($compatible && ($state['version']??null)!=='@@PLUGIN_VERSION@@') || ($state['site']??null)!==self::site()) { self::fail('Tugas berbeda instalasi/versi.'); }return $state;
    }
    private static function checkpoint(array $state): void {
        $dir=self::jobDirectory($state['id']);$temp=$dir.'/state-'.wp_generate_uuid4();$state['updated']=time();
        if (file_put_contents($temp,wp_json_encode($state,JSON_THROW_ON_ERROR))===false || !chmod($temp,0600) || !rename($temp,$dir.'/state.json')) { if (is_file($temp)) { unlink($temp); }self::fail('Checkpoint tugas gagal.'); }
        if (in_array($state['phase'],['done','failed','cancelled'],true)) { do_action('fwf_backup_job_terminal',array_intersect_key($state,array_flip(['id','phase','error']))); }
    }
    private static function schedule(string $id,int $delay=1): void {
        if (!wp_next_scheduled('fwf_backup_step',[$id])) { $result=wp_schedule_single_event(time()+$delay,'fwf_backup_step',[$id],true);if (is_wp_error($result) || !$result) { self::fail('Antrean WP-Cron gagal; gunakan Lanjutkan setelah konfigurasi cron diperiksa.'); } }
    }
    private static function cleanJob(string $dir): void {
        foreach (glob($dir.'/*')?:[] as $file) { if (basename($file)==='state.json') { continue; }if (!is_file($file) || is_link($file) || !unlink($file)) { self::fail('Pembersihan staging tugas gagal.'); } }
    }
    public static function jobs(): array|\WP_Error {
        try {
            self::allowed();$dir=self::directory();if (is_wp_error($dir)) { return $dir; }$jobs=[];
            foreach (glob($dir.'/job-*')?:[] as $path) {
                $state=self::job(substr(basename($path),4),false);if ($state['version']!=='@@PLUGIN_VERSION@@' && !in_array($state['phase'],['done','cancelled'],true)) { $state['phase']='failed';$state['error']='Versi kode berubah; batalkan tugas lama dan buat backup baru.'; }$jobs[$state['id']]=array_intersect_key($state,array_flip(['id','phase','components','created','updated','cursor','total','error']));
            }
            uasort($jobs,static fn($a,$b)=>$b['created']<=>$a['created']);return $jobs;
        } catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
    }
    public static function queue(mixed $components,bool $background=true,mixed $note='',mixed $media=null): string|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }$path=null;
        try {
            self::allowed();$note=self::validNote($note);$dir=self::directory();if (is_wp_error($dir)) { return $dir; }
            if (glob($dir.'/restore-*')) { self::fail('Journal recovery tertunda. Periksa sebelum backup baru.'); }
            if (!is_array($components) || !$components || count(array_filter($components,'is_string'))!==count($components) || count(array_unique($components,SORT_REGULAR))!==count($components) || array_diff($components,array_keys(self::components()))) { self::fail('Pilih komponen backup yang valid.'); }
            $jobs=self::jobs();if (is_wp_error($jobs)) { return $jobs; }foreach ($jobs as $job) { if (!in_array($job['phase'],['done','cancelled','failed'],true)) { self::fail('Lanjutkan atau batalkan tugas sebelumnya terlebih dahulu.'); } }
            $media=MediaSelection::ids($media);if ($media!==null && (!in_array('media',$components,true) || in_array('database',$components,true))) { self::fail('Media terpilih memerlukan komponen media tanpa database seluruh situs.'); }
            $estimate=self::estimates($media);if (is_wp_error($estimate)) { return $estimate; }self::space($dir,self::selectedSize($estimate,$components)*3);
            $id=wp_generate_uuid4();$path=$dir.'/job-'.$id;if (!mkdir($path,0700)) { self::fail('Folder tugas gagal.'); }
            $state=['id'=>$id,'version'=>'@@PLUGIN_VERSION@@','site'=>self::site(),'owner'=>get_current_user_id(),'background'=>$background,'phase'=>'queued','created'=>time(),'updated'=>time(),'components'=>array_values($components),'media_selection'=>$media,'cursor'=>0,'total'=>0,'error'=>''];self::checkpoint($state);
            if ($note!=='') { self::storeNote($id,$note); }if ($background) { self::schedule($id); }return $id;
        } catch (\Throwable $e) { if ($path && is_dir($path)) { delete_option('fwf_backup_note_'.$id);self::cleanJob($path);if (is_file($path.'/state.json')) { unlink($path.'/state.json'); }rmdir($path); }return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
        finally { Lock::release('backup',$lock); }
    }
    /** Snapshot is transactional in one worker; archive creation then resumes across workers. */
    private static function capture(array &$state,string $dir): void {
        global $wpdb;self::cleanJob($dir);$transaction=false;
        try {
            if ($wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT')===false) { self::fail('Snapshot database gagal.'); }$transaction=true;
            $index=self::data($state['components'],$dir,$state['media_selection']??null);$entries=[];$payload=[];
            foreach ($index as $table=>$details) { if (in_array($table,['settings','attachments'],true)) { continue; }$entries[$details['rows_file']]=['sha256'=>$details['sha256'],'size'=>$details['size']];$payload[$details['rows_file']]=$table.'.jsonl'; }
            $data=wp_json_encode($index,JSON_THROW_ON_ERROR);if (file_put_contents($dir.'/database.json',$data)!==strlen($data)) { self::fail('Staging metadata gagal.'); }
            $entries['database.json']=['sha256'=>hash('sha256',$data),'size'=>strlen($data)];$payload['database.json']='database.json';
            $files=self::files($state['components'],true,$state['media_selection']??null);
            foreach ($files as $name=>$file) {
                $slot='payload-'.count($payload);if (!copy($file['path'],$dir.'/'.$slot) || !hash_equals($file['sha256'],hash_file('sha256',$dir.'/'.$slot))) { self::fail('File berubah/gagal saat snapshot.'); }
                $entries['files/'.$name]=['sha256'=>$file['sha256'],'size'=>$file['size'],'mode'=>$file['mode']];$payload['files/'.$name]=$slot;
            }
            foreach ($files as $file) { if (!hash_equals($file['sha256'],hash_file('sha256',$file['path']))) { self::fail('File berubah saat snapshot; ulangi pada window pemeliharaan.'); } }
            if ($wpdb->query('COMMIT')===false) { self::fail('Snapshot database gagal.'); }$transaction=false;
            $manifest=['schema'=>3,'site'=>self::site(),'created'=>gmdate('c'),'components'=>$state['components'],'media_selection'=>$state['media_selection']??null,'entries'=>$entries,'wp'=>get_bloginfo('version'),'fp'=>'@@PLUGIN_VERSION@@','exclusions'=>['accounts/authentication','server configuration and server credentials','operational audit/cache/AI grants','active theme/plugin selection']];
            $body=wp_json_encode($manifest,JSON_THROW_ON_ERROR);$signature=hash_hmac('sha256',$body,wp_salt('auth'));
            if (file_put_contents($dir.'/manifest.json',$body)!==strlen($body) || file_put_contents($dir.'/signature.txt',$signature)!==strlen($signature)) { self::fail('Staging manifest gagal.'); }
            $payload['manifest.json']='manifest.json';$payload['signature.txt']='signature.txt';
            foreach (glob($dir.'/*')?:[] as $file) { if (!chmod($file,0600)) { self::fail('Izin staging private gagal.'); } }
            $state['payload']=$payload;$state['entries']=$entries;$state['total']=count($payload);$state['cursor']=0;$state['phase']='packing';self::checkpoint($state);
        } finally { if ($transaction) { $wpdb->query('ROLLBACK'); } }
    }
    public static function work(mixed $id): array|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }$actor=get_current_user_id();$state=null;$zip=null;
        try {
            $state=self::job($id);$dir=self::jobDirectory($id);wp_set_current_user($state['owner']);self::allowed();
            if (in_array($state['phase'],['done','cancelled','failed'],true)) { return $state; }
            if (glob(dirname($dir).'/restore-*')) { self::fail('Journal recovery tertunda.'); }
            $final=dirname($dir).'/'.$id.'.zip';
            if (is_file($final) && $state['phase']!=='retaining') { self::read($final);$state['phase']='retaining';self::checkpoint($state);self::cleanJob($dir); }
            if (in_array($state['phase'],['queued','snapshot'],true)) { $state['phase']='snapshot';self::checkpoint($state);self::capture($state,$dir); }
            elseif ($state['phase']==='packing') {
                $path=$dir.'/archive.zip';if (!is_file($path)) { $state['cursor']=0; }
                $zip=new \ZipArchive();if ($zip->open($path,\ZipArchive::CREATE)!==true) { $zip=null;self::fail('ZIP tugas rusak; gunakan Lanjutkan untuk membangun ulang dari snapshot.'); }
                $names=array_keys($state['payload']);$end=min($state['total'],$state['cursor']+8);$until=microtime(true)+10;
                for ($i=$state['cursor'];$i<$end;$i++) {
                    $name=$names[$i];$source=$dir.'/'.$state['payload'][$name];if (!is_file($source) || is_link($source)) { self::fail('Snapshot staging tidak tersedia.'); }
                    $expected=$state['entries'][$name]['sha256']??($name==='signature.txt'?hash_hmac('sha256',file_get_contents($dir.'/manifest.json'),wp_salt('auth')):null);
                    if ($expected!==null && ($name==='signature.txt'?file_get_contents($source)!==$expected:!hash_equals($expected,hash_file('sha256',$source)))) { self::fail('Snapshot staging berubah.'); }
                    if (!$zip->addFile($source,$name)) { self::fail('Penulisan ZIP tugas gagal.'); }$state['cursor']=$i+1;if (microtime(true)>$until) { break; }
                }
                $closed=$zip->close();$zip=null;if (!$closed) { self::fail('Checkpoint ZIP gagal.'); }if (!chmod($path,0600)) { self::fail('Izin ZIP private gagal.'); }
                if ($state['cursor']===$state['total']) { $state['phase']='verifying'; }self::checkpoint($state);
            } elseif ($state['phase']==='verifying') {
                self::read($dir.'/archive.zip');if (!rename($dir.'/archive.zip',$final)) { self::fail('Finalisasi ZIP tugas gagal.'); }$state['phase']='retaining';self::checkpoint($state);self::cleanJob($dir);
            } elseif ($state['phase']==='retaining') {
                self::read($final);$state['error']=$state['background']?self::retainLocked($id):'';$state['phase']='done';self::checkpoint($state);self::cleanJob($dir);
            } else { self::fail('Tahap tugas tidak valid.'); }
            if ($state['background'] && $state['phase']!=='done') { self::schedule($id); }return $state;
        } catch (\Throwable $e) {
            if ($state) { $state['retry_phase']=$state['phase'];$state['phase']='failed';$state['error']=$e->getMessage();self::checkpoint($state); }
            return new \WP_Error('FWF_BACKUP',$e->getMessage());
        } finally { if ($zip instanceof \ZipArchive) { try { $zip->close(); } catch (\Throwable $ignored) {} }wp_set_current_user($actor);Lock::release('backup',$lock); }
    }
    public static function control(mixed $id,bool $cancel=false): true|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }
        try {
            self::allowed();$state=self::job($id,!$cancel);$dir=self::jobDirectory($id);if (in_array($state['phase'],['done','cancelled'],true)) { return true; }
            wp_clear_scheduled_hook('fwf_backup_step',[$id]);
            if ($cancel) { $state['phase']='cancelled';self::checkpoint($state);self::cleanJob($dir);delete_option('fwf_backup_note_'.$id); }
            else {
                $jobs=self::jobs();if (is_wp_error($jobs)) { return $jobs; }foreach ($jobs as $other) { if ($other['id']!==$id && !in_array($other['phase'],['done','failed','cancelled'],true)) { self::fail('Tugas lain masih aktif.'); } }
                if ($state['phase']==='failed') { $state['phase']=$state['retry_phase']??'queued';if ($state['phase']==='packing' || $state['phase']==='verifying') { if (is_file($dir.'/archive.zip')) { unlink($dir.'/archive.zip'); }$state['phase']='packing';$state['cursor']=0; } }
                $state['error']='';$state['background']=true;self::checkpoint($state);self::schedule($id);
            }return true;
        } catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
        finally { Lock::release('backup',$lock); }
    }
    public static function forget(mixed $id): true|\WP_Error {
        $lock=Lock::acquire('backup');if (is_wp_error($lock)) { return $lock; }
        try { self::allowed();$dir=self::jobDirectory($id);self::job($id,false);wp_clear_scheduled_hook('fwf_backup_step',[$id]);self::cleanJob($dir);if (is_wp_error(self::path($id))) { delete_option('fwf_backup_note_'.$id); }if (!unlink($dir.'/state.json') || !rmdir($dir)) { self::fail('Pembersihan tugas gagal.'); }return true; }
        catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
        finally { Lock::release('backup',$lock); }
    }
    private static function read(string $path): array {
        $zip=new \ZipArchive();if ($zip->open($path)!==true) { self::fail('ZIP tidak dapat dibaca.'); }
        try {
            if (($zip->statName('manifest.json')['size']??PHP_INT_MAX)>self::metadataMemoryBudget() || ($zip->statName('signature.txt')['size']??0)!==64) { self::fail('Manifest tidak valid.'); }
            $body=$zip->getFromName('manifest.json');$signature=$zip->getFromName('signature.txt');
            if (!is_string($body) || strlen($body)>self::metadataMemoryBudget() || !is_string($signature) || !hash_equals(hash_hmac('sha256',$body,wp_salt('auth')),$signature)) { self::fail('Paket bukan backup terpercaya dari instalasi ini.'); }
            $manifest=json_decode($body,true,512,JSON_THROW_ON_ERROR);
            if (($manifest['schema']??null)!==3 || ($manifest['site']??null)!==self::site() || ($manifest['fp']??null)!=='@@PLUGIN_VERSION@@' || ($manifest['wp']??null)!==get_bloginfo('version') || !is_array($manifest['entries']??null) || !is_array($manifest['components']??null) || !$manifest['components'] || array_diff($manifest['components'],array_keys(self::components()))) { self::fail('Identitas atau versi backup tidak cocok.'); }
            $seen=[];$size=0;$payload=[];
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);$name=$stat['name'];$opsys=0;$attr=0;$zip->getExternalAttributesIndex($i,$opsys,$attr);
                if (!self::safe($name) || isset($seen[$name]) || (($attr>>16)&0170000)===0120000 || ($stat['encryption_method']??0)!==0) { self::fail('Path ZIP/duplicate/symlink/enkripsi ditolak.'); }$seen[$name]=true;
                if (in_array($name,['manifest.json','signature.txt'],true)) { continue; }
                $size+=$stat['size'];if (!isset($manifest['entries'][$name]) || $stat['size']!==$manifest['entries'][$name]['size']) { self::fail('Inventory atau ukuran ZIP tidak cocok.'); }
                $stream=$zip->getStream($name);if (!$stream) { self::fail('Pembacaan stream ZIP gagal.'); }
                $hash=hash_init('sha256');$length=hash_update_stream($hash,$stream);fclose($stream);
                if ($length!==$stat['size'] || !hash_equals($manifest['entries'][$name]['sha256'],hash_final($hash))) { self::fail('Checksum backup tidak cocok.'); }
                if ($name!=='database.json' && !preg_match('~^rows/[a-zA-Z0-9_]+\.jsonl$~D',$name)) { $parts=explode('/',$name,3);if (count($parts)!==3 || $parts[0]!=='files' || !isset(self::roots()[$parts[1]]) || !in_array($parts[1],$manifest['components'],true)) { self::fail('File di luar komponen backup.'); } }
                $payload[$name]=null;
                if ($name==='database.json') { if ($stat['size']>self::metadataMemoryBudget()) { self::fail('Metadata backup melebihi memori server tersedia.'); }$payload[$name]=$zip->getFromName($name); }
            }
            if (count($seen)!==count($manifest['entries'])+2 || !isset($payload['database.json'])) { self::fail('Inventory backup tidak lengkap.'); }
            $media=MediaSelection::ids($manifest['media_selection']??null);
            if ($media!==null) {
                if (!in_array('media',$manifest['components'],true) || in_array('database',$manifest['components'],true)) { self::fail('Scope media terpilih tidak valid.'); }
                $data=json_decode($payload['database.json'],true,512,JSON_THROW_ON_ERROR);$records=$data['attachments']??null;if (!is_array($records)) { self::fail('Metadata media tidak lengkap.'); }
                $expected=array_map(static fn($p)=>'files/media/'.$p,MediaSelection::validate($records,$media));$actual=array_values(array_filter(array_keys($manifest['entries']),static fn($p)=>str_starts_with($p,'files/media/')));sort($actual);sort($expected);if ($actual!==$expected) { self::fail('Inventory media tidak cocok dengan metadata.'); }
            }
            return [$manifest,$payload];
        } finally { $zip->close(); }
    }
    private static function metadataMemoryBudget(): int {
        $limit=wp_convert_hr_to_bytes(ini_get('memory_limit'));
        return $limit>0?max(0,(int)(($limit-memory_get_usage(true))/8)):PHP_INT_MAX;
    }
    private static function zipRows(\ZipArchive $zip, array $details): \Generator {
        $stream=$zip->getStream($details['rows_file']);if (!$stream) { self::fail('Stream row database tidak tersedia.'); }$count=0;
        try {
            while (($line=fgets($stream))!==false) { $count++;yield json_decode($line,true,512,JSON_THROW_ON_ERROR); }
            if (!feof($stream) || $count!==$details['count']) { self::fail('Jumlah row database tidak cocok.'); }
        } finally { fclose($stream); }
    }
    public static function inspect(mixed $id): array|\WP_Error {
        try { self::allowed();$path=self::path($id);if (is_wp_error($path)) { return $path; }[$manifest,$payload]=self::read($path);if (($manifest['media_selection']??null)!==null) { $data=json_decode($payload['database.json'],true,512,JSON_THROW_ON_ERROR);MediaSelection::validate($data['attachments'],$manifest['media_selection'],true); }return ['id'=>$id,'sha256'=>hash_file('sha256',$path),'manifest'=>$manifest,'revision'=>self::revision(self::recoveryComponents($manifest))]; }
        catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
    }
    private static function recoveryComponents(array $manifest): array { return ($manifest['media_selection']??null)!==null?array_values(array_unique(array_merge($manifest['components'],['database','media']))):$manifest['components']; }
    private static function revision(array $components): string { return hash('sha256',wp_json_encode(self::data($components)).wp_json_encode(array_map(static fn($file)=>$file['sha256'],self::files($components)))); }
    public static function import(string $source): string|\WP_Error {
        try { self::allowed();$dir=self::directory();if (is_wp_error($dir)) { return $dir; }self::read($source);$id=wp_generate_uuid4();$path=$dir.'/'.$id.'.zip';if (!copy($source,$path)) { self::fail('Import backup gagal.'); }if (!chmod($path,0600)) { unlink($path);self::fail('Izin private ZIP gagal.'); }return $id; }
        catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP',$e->getMessage()); }
    }
    public static function recovery(mixed $id,?string $review=null,bool $confirm=false): array|\WP_Error {
        try { self::allowed();if (!is_string($id)) { self::fail('ID journal tidak valid.'); }return Recovery::operate($id,$review,$confirm); }catch (\Throwable $e) { return new \WP_Error('FWF_BACKUP_RECOVERY',$e->getMessage()); }
    }
    public static function restore(mixed $id,mixed $hash,mixed $revision,bool $confirm): true|\WP_Error {
        global $wpdb;
        if (!$confirm || !is_string($hash) || !is_string($revision)) { return new \WP_Error('FWF_BACKUP','Konfirmasi dan review backup diperlukan.'); }
        $review=self::inspect($id);if (is_wp_error($review)) { return $review; }
        if (!hash_equals($review['sha256'],$hash) || !hash_equals($review['revision'],$revision)) { return new \WP_Error('FWF_CONFLICT','Paket atau situs berubah setelah review.'); }
        $safety=self::create(self::recoveryComponents($review['manifest']));if (is_wp_error($safety)) { return $safety; }
        $owner=Lock::acquire('backup');if (is_wp_error($owner)) { return $owner; }$updates=Lock::acquire('updates');$state=null;$databaseLease=null;$transaction=false;$dir=null;$restoreZip=null;
        try {
            self::allowed();if (is_wp_error($updates)) { return $updates; }if (!$confirm || !is_string($hash) || !is_string($revision)) { self::fail('Konfirmasi dan review backup diperlukan.'); }
            if ((defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) || !in_array(wp_get_environment_type(),['local','staging'],true)) { self::fail('Restore fondasi hanya local/staging writable.'); }
            $path=self::path($id);if (is_wp_error($path)) { return $path; }if (!hash_equals(hash_file('sha256',$path),$hash)) { self::fail('Paket berubah setelah review.'); }
            [$manifest,$payload]=self::read($path);if (!hash_equals(self::revision(self::recoveryComponents($manifest)),$revision)) { self::fail('Situs berubah setelah review. Periksa ulang.'); }
            $data=json_decode($payload['database.json'],true,512,JSON_THROW_ON_ERROR);$tables=in_array('database',$manifest['components'],true)?self::tables():[];
            if (!is_array($data) || ($tables && array_keys($data)!==$tables) || (!$tables && array_diff(array_keys($data),array_merge(in_array('settings',$manifest['components'],true)?['settings']:[],($manifest['media_selection']??null)!==null?['attachments']:[])))) { self::fail('Tabel database tidak cocok.'); }
            foreach ($tables as $table) { if (($data[$table]['rows_file']??null)!=='rows/'.$table.'.jsonl' || !isset($manifest['entries'][$data[$table]['rows_file']]) || $manifest['entries'][$data[$table]['rows_file']]['sha256']!==$data[$table]['sha256']) { self::fail('Row inventory database tidak cocok.'); }if ($data[$table]['columns']!==$wpdb->get_results("SHOW FULL COLUMNS FROM `$table`",ARRAY_A)) { self::fail('Schema tabel berubah.'); } }
            if (($manifest['media_selection']??null)!==null) { MediaSelection::validate($data['attachments'],$manifest['media_selection'],true); }
            $dir=self::directory();self::space($dir,array_sum(array_column($manifest['entries'],'size'))*3);
            $restoreZip=new \ZipArchive();if ($restoreZip->open($path)!==true) { $restoreZip=null;self::fail('ZIP restore gagal.'); }
            $databaseLease=Recovery::databaseLease();$state=Recovery::begin(self::roots(),$updates,$id,$safety);do_action('fwf_backup_restore_checkpoint','prepared',$state['id']);
            foreach ($payload as $name=>$bytes) {
                if (!str_starts_with($name,'files/')) { continue; }[$prefix,$component,$relative]=explode('/',$name,3);$root=realpath(self::roots()[$component]);if (!$root || is_link(self::roots()[$component])) { self::fail('Root restore tidak tersedia.'); }
                $target=$root.'/'.$relative;$parent=dirname($target);$probe=$target;
                while ($probe!==$root) { if (is_link($probe)) { self::fail('Symlink target restore ditolak.'); }$probe=dirname($probe); }
                if (!wp_mkdir_p($parent) || !is_writable($parent) || (file_exists($target) && !is_file($target))) { self::fail('Target restore tidak writable.'); }
                $temp=Recovery::prepare($state,$component,$relative,$manifest['entries'][$name]['sha256']);do_action('fwf_backup_restore_checkpoint','journaled',$state['id']);$input=$restoreZip->getStream($name);$output=fopen($temp,'xb');
                if (!$input || !$output) { if ($input) { fclose($input); }if ($output) { fclose($output); }if (is_file($temp)) { unlink($temp); }self::fail('Stream restore gagal.'); }
                $copied=stream_copy_to_stream($input,$output);fclose($input);fclose($output);
                if ($copied!==$manifest['entries'][$name]['size'] || !hash_equals(hash_file('sha256',$temp),$manifest['entries'][$name]['sha256']) || !rename($temp,$target)) { if (is_file($temp)) { unlink($temp); }self::fail('Penulisan restore gagal.'); }if (!chmod($target,($manifest['entries'][$name]['mode']??0644)&0777)) { self::fail('Pemulihan izin file gagal.'); }if (function_exists('opcache_invalidate')) { opcache_invalidate($target,true); }do_action('fwf_backup_restore_checkpoint','file_written',$state['id']);
            }
            if ($wpdb->query('START TRANSACTION')===false) { self::fail('Transaksi restore gagal.'); }$transaction=true;
            foreach ($tables as $table) {
                if ($table===$wpdb->options) {
                    foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options}") as $key) { if (!self::volatile($key) && $wpdb->delete($table,['option_name'=>$key])===false) { self::fail('Restore option gagal.'); } }
                } elseif ($wpdb->query("DELETE FROM `$table`")===false) { self::fail('Restore tabel gagal.'); }
                $columns=array_column($data[$table]['columns'],'Field');
                foreach (self::zipRows($restoreZip,$data[$table]) as $encoded) {
                    if (!is_array($encoded) || array_keys($encoded)!==$columns) { self::fail('Kolom data tidak cocok.'); }$row=[];
                    foreach ($encoded as $key=>$v) { $decoded=$v===null?null:base64_decode($v,true);if ($decoded===false) { self::fail('Encoding data tidak valid.'); }$row[$key]=$decoded; }
                    if ($table===$wpdb->options && self::volatile($row['option_name'])) { self::fail('Option sistem tidak boleh direstore.'); }
                    if ($table===$wpdb->options) { unset($row['option_id']); }
                    if ($wpdb->insert($table,$row)===false) { self::fail('Insert restore gagal.'); }
                }
            }
            if (isset($data['settings'])) {
                foreach ($wpdb->get_col("SELECT option_name FROM {$wpdb->options}") as $key) { if (self::setting($key) && $wpdb->delete($wpdb->options,['option_name'=>$key])===false) { self::fail('Reset settings gagal.'); } }
                foreach ($data['settings'] as $row) { if (!is_array($row) || array_keys($row)!==['option_name','option_value','autoload'] || !self::setting($row['option_name']) || $wpdb->insert($wpdb->options,$row)===false) { self::fail('Restore settings gagal.'); } }
            }
            if (($manifest['media_selection']??null)!==null) { MediaSelection::restore($data['attachments'],$manifest['media_selection']); }
            do_action('fwf_backup_restore_checkpoint','database_written',$state['id']);Recovery::committing($state);do_action('fwf_backup_restore_checkpoint','before_commit',$state['id']);
            if ($wpdb->query('COMMIT')===false) { self::fail('Commit restore gagal.'); }do_action('fwf_backup_restore_checkpoint','commit_ack',$state['id']);$transaction=false;wp_cache_flush();
            do_action('fwf_backup_restore_checkpoint','after_commit',$state['id']);Recovery::finish($state);
            return true;
        } catch (\Throwable $e) {
            $recovered=true;$resolution=null;
            try { if ($transaction && $wpdb->query('ROLLBACK')===false) { self::fail('Hasil transaksi belum pasti.'); }if ($state!==null) { if (!Recovery::connectionIntact($state)) { Recovery::uncertain($state); }$resolution=Recovery::finish($state); } }catch (\Throwable $recoveryError) { $recovered=false; }
            wp_cache_flush();return new \WP_Error('FWF_BACKUP',$e->getMessage().($resolution==='committed'?' Database sudah commit; file hasil restore dipertahankan.':'').($recovered?'':' Recovery journal memerlukan pemeriksaan operator.'));
        } finally { if ($databaseLease!==null) { Recovery::releaseDatabase($databaseLease); }if ($restoreZip instanceof \ZipArchive) { $restoreZip->close(); }if (!is_wp_error($updates) && ($state===null || !is_dir($dir.'/restore-'.$state['id']))) { Lock::release('updates',$updates); }Lock::release('backup',$owner); }
    }
}
