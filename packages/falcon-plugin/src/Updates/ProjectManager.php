<?php
namespace FalconWF\Updates;
use FalconWF\Audit\Logger;
use FalconWF\Packages\Lock;
use FalconWF\Packages\Verifier;
/** One configured child theme; project manifest owner is separate from core releases. */
final class ProjectManager {
    public static function connection(): array { $value=get_option('fwf_project_connection',[]);return is_array($value)?$value:[]; }
    public static function slug(mixed $value): bool { return is_string($value) && (bool)preg_match('/^[a-z][a-z0-9-]{1,63}$/D',$value) && !in_array($value,['falcon-wf','falcon-theme'],true); }
    public static function save(mixed $input): true|\WP_Error {
        if (!current_user_can('fwf_manage_connections')) { return new \WP_Error('FWF_PERMISSION','Tidak diizinkan.'); }
        if (is_array($input)) { $input['repo']=GitHubClient::normalizeRepo($input['repo']??null); }
        if (!is_array($input) || array_diff(array_keys($input),['repo','project_id','theme_id','tag']) || !GitHubClient::validRepo(is_string($input['repo']??null)?$input['repo']:'') || !self::slug($input['project_id']??null) || !self::slug($input['theme_id']??null) || !is_string($input['tag']??null)) { return new \WP_Error('FWF_VALIDATION','Isi alamat GitHub, project ID dan slug child theme yang valid.'); }
        $selection=UpdateManager::validateSelection($input['tag']);if(is_wp_error($selection)){return $selection;}
        update_option('fwf_project_connection',$input,false);if(get_option('fwf_project_connection')!==$input){return new \WP_Error('FWF_DB','Koneksi proyek gagal disimpan. Periksa database sebelum retry.');}delete_option('fwf_project_candidate');return true;
    }
    private static function version(mixed $value,bool $stable=false): bool { return is_string($value) && (bool)preg_match($stable?'/^\d+\.\d+\.\d+$/D':'/^\d+\.\d+\.\d+(-(alpha|beta|rc)\.[1-9]\d*)?$/D',$value); }
    public static function validate(array $manifest,array $connection): array|\WP_Error {
        $tag=$connection['tag']??null;
        if (!is_string($tag) || !self::slug($connection['project_id']??null) || !self::slug($connection['theme_id']??null)) { return new \WP_Error('FWF_PACKAGE','Koneksi proyek belum valid.'); }
        $selection=UpdateManager::validateSelection($tag);if(is_wp_error($selection)){return $selection;}
        if (($manifest['schema']??null)!==1 || ($manifest['project_id']??null)!==$connection['project_id'] || ($manifest['status']??null)!==($tag===''?'stable':'development') || ($manifest['dirty']??null)!==false || !is_string($manifest['source_commit']??null) || !preg_match('/^[a-f0-9]{40}$/D',$manifest['source_commit']) || !self::version($manifest['version']??null,$tag==='') || ($tag!=='' && $manifest['version']!==preg_replace('/^v/','',$tag)) || !is_array($manifest['packages']??null) || !array_is_list($manifest['packages']) || count($manifest['packages'])!==1) { return new \WP_Error('FWF_PACKAGE','Manifest proyek harus cocok dengan project/tag dan berasal dari commit bersih; satu paket child theme.'); }
        $p=$manifest['packages'][0];
        if (!is_array($p) || ($p['id']??null)!==$connection['theme_id'] || ($p['type']??null)!=='theme' || ($p['parent']??null)!=='falcon-theme' || !self::version($p['version']??null,$tag==='') || ($p['artifact']??null)!==$p['id'].'-'.$p['version'].'.zip' || !is_string($p['sha256']??null) || !preg_match('/^[a-f0-9]{64}$/D',$p['sha256'])) { return new \WP_Error('FWF_PACKAGE','Identitas/type/parent/version/hash paket proyek tidak valid.'); }
        foreach (['min_wp'=>$GLOBALS['wp_version'],'min_php'=>PHP_VERSION] as $key=>$current) { if (!is_string($p[$key]??null) || !preg_match('/^\d+\.\d+(\.\d+)?$/D',$p[$key]) || version_compare($current,$p[$key],'<')) { return new \WP_Error('FWF_COMPATIBILITY','Runtime tidak memenuhi requirement proyek.'); } }
        $ranges=$p['compatibility']??null;
        if (!is_array($ranges) || !isset($ranges['falcon-theme']) || array_diff(array_keys($ranges),['falcon-theme','falcon-wf'])) { return new \WP_Error('FWF_PACKAGE','Compatibility FP/FT diperlukan.'); }
        require_once ABSPATH.'wp-admin/includes/plugin.php';
        foreach ($ranges as $id=>$range) {
            if (!is_array($range) || !self::version($range['min']??null) || !self::version($range['max_exclusive']??null) || version_compare($range['min'],$range['max_exclusive'],'>=') || (isset($range['optional']) && !is_bool($range['optional'])) || ($id==='falcon-theme' && !empty($range['optional']))) { return new \WP_Error('FWF_PACKAGE','Rentang compatibility tidak valid.'); }
            if ($id==='falcon-theme') { $theme=wp_get_theme('falcon-theme');$current=$theme->exists() && !$theme->errors()?$theme->get('Version'):''; }
            else { $current=is_plugin_active('falcon-wf/falcon-wf.php')?get_plugin_data(WP_PLUGIN_DIR.'/falcon-wf/falcon-wf.php')['Version']:''; }
            if (!$current && !empty($range['optional'])) { continue; }
            if (!$current || version_compare($current,$range['min'],'<') || version_compare($current,$range['max_exclusive'],'>=')) { return new \WP_Error('FWF_COMPATIBILITY','Komponen '.$id.' tidak memenuhi rentang proyek. Perbarui core terlebih dahulu.'); }
        }
        $existing=wp_get_theme($p['id']);if ($existing->exists() && ($existing->errors() || !self::version($existing->get('Version')) || $existing->get('Template')!=='falcon-theme')) { return new \WP_Error('FWF_PACKAGE','Slug target sudah dipakai theme lain/tidak valid. File tidak ditimpa.'); }
        return $p;
    }
    private static function client(array $connection): GitHubClient { return new GitHubClient($connection['repo']??'','FWF_PROJECT_GITHUB_TOKEN','project-manifest.json'); }
    public function check(): array|\WP_Error {
        if (!current_user_can('fwf_manage_updates')) { return new \WP_Error('FWF_PERMISSION','Tidak diizinkan memeriksa paket proyek.'); }
        delete_option('fwf_project_candidate');$connection=self::connection();$release=self::client($connection)->release($connection['tag']??'');if(is_wp_error($release)){return $release;}
        $p=self::validate($release['manifest'],$connection);if(is_wp_error($p)){return $p;}
        if (preg_replace('/^v/','',$release['tag'])!==$release['manifest']['version']) { return new \WP_Error('FWF_PACKAGE','Versi manifest proyek tidak cocok dengan tag release.'); }
        if (!isset($release['assets'][$p['artifact']])) { return new \WP_Error('FWF_PACKAGE','ZIP proyek tidak ada pada release.'); }
        $release['connection']=$connection;update_option('fwf_project_candidate',$release,false);if(get_option('fwf_project_candidate')!==$release){delete_option('fwf_project_candidate');return new \WP_Error('FWF_DB','Hasil pemeriksaan gagal disimpan.');}$audit=Logger::write('project_check','succeeded');if(is_wp_error($audit)){delete_option('fwf_project_candidate');return $audit;}return $p;
    }
    public function apply(bool $backupConfirmed): true|\WP_Error {
        $connection=self::connection();$id=$connection['theme_id']??'';
        $exists=self::slug($id) && wp_get_theme($id)->exists();
        if (!self::slug($id) || !current_user_can('fwf_manage_updates') || !current_user_can($exists?'update_themes':'install_themes')) { return new \WP_Error('FWF_PERMISSION','Izin instal/update proyek ditolak.'); }
        if (!$backupConfirmed) { return new \WP_Error('FWF_PERMISSION','Konfirmasikan backup dan pengujian staging sebelum mengganti file desain.'); }
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) { return new \WP_Error('FWF_FILESYSTEM','Code immutable: gunakan pipeline.'); }
        require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        if (get_filesystem_method([],get_theme_root())!=='direct' || !is_writable(get_theme_root()) || !is_writable(get_temp_dir())) { return new \WP_Error('FWF_FILESYSTEM','Theme/temp tidak writable atau memerlukan credential. Gunakan installer WordPress/pipeline.'); }
        // Shared core/project update lock prevents compatibility changing midway through a write.
        $owner=Lock::acquire('updates');if(is_wp_error($owner)){return $owner;}$tmp=null;
        try {
            $release=self::client($connection)->release($connection['tag']??'');if(is_wp_error($release)){return $release;}
            $p=self::validate($release['manifest'],$connection);if(is_wp_error($p)){return $p;}
        if (preg_replace('/^v/','',$release['tag'])!==$release['manifest']['version']) { return new \WP_Error('FWF_PACKAGE','Versi manifest proyek tidak cocok dengan tag release.'); }
            $candidate=get_option('fwf_project_candidate',[]);
            if (($candidate['connection']??null)!==$connection || ($candidate['manifest']??null)!==$release['manifest'] || ($candidate['tag']??null)!==$release['tag'] || !isset($release['assets'][$p['artifact']])) { return new \WP_Error('FWF_CONFLICT','Paket/koneksi berubah atau belum diperiksa. Periksa ulang sebelum menerapkan.'); }
            $theme=wp_get_theme($id);$current=$theme->exists()?$theme->get('Version'):'';
            if ($current && version_compare($current,$p['version'],'>=')) { return new \WP_Error('FWF_COMPATIBILITY','Versi target tidak lebih baru. Tidak downgrade atau overwrite versi sama.'); }
            $tmp=self::client($connection)->asset($release['assets'][$p['artifact']],true);if(is_wp_error($tmp)){return $tmp;}
            $verified=Verifier::remote($tmp,$p);if(is_wp_error($verified)){return $verified;}
            $needed=3*40*1024*1024+filesize($tmp);foreach (array_unique([get_theme_root(),get_temp_dir()]) as $path) { $free=@disk_free_space($path);if($free!==false && $free<$needed){return new \WP_Error('FWF_DISK','Ruang disk tidak cukup untuk instal/update proyek.');} }
            $audit=Logger::write('project_apply','started');if(is_wp_error($audit)){return $audit;}
            $upgrader=new \Theme_Upgrader(new \WP_Ajax_Upgrader_Skin());
            if (!$current) { $result=$upgrader->install($tmp,['overwrite_package'=>false]); }
            else {
                $previous=get_site_transient('update_themes');$transient=is_object($previous)?clone $previous:new \stdClass();$transient->response=$transient->response??[];
                $transient->response[$id]=['new_version'=>$p['version'],'package'=>$tmp,'url'=>'https://github.com/'.$connection['repo'],'requires'=>$p['min_wp'],'requires_php'=>$p['min_php']];set_site_transient('update_themes',$transient);
                try { $result=$upgrader->upgrade($id); } finally { $previous===false?delete_site_transient('update_themes'):set_site_transient('update_themes',$previous); }
            }
            wp_clean_themes_cache();$installed=wp_get_theme($id);
            if ($result!==true || !$installed->exists() || $installed->errors() || $installed->get('Version')!==$p['version'] || $installed->get('Template')!=='falcon-theme') { Logger::write('project_apply','failed');return new \WP_Error('FWF_FILESYSTEM','Instal/update proyek gagal. Periksa recovery WordPress dan backup sebelum retry.'); }
            delete_option('fwf_project_candidate');$audit=Logger::write('project_apply','succeeded');return is_wp_error($audit)?new \WP_Error('FWF_AUDIT','Theme terpasang tetapi audit final gagal. Periksa hasil sebelum retry.') : true;
        } finally { if(is_string($tmp) && is_file($tmp)){wp_delete_file($tmp);}Lock::release('updates',$owner); }
    }
}
