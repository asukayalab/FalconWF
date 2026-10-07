<?php
namespace FalconWF\Updates;
use FalconWF\Audit\Logger;
use FalconWF\Packages\Lock;
final class UpdateManager {
    public static function validateSelection(string $tag): true|\WP_Error {
        if ($tag==='') { return true; }
        if (!in_array(wp_get_environment_type(),['local','staging'],true)) { return new \WP_Error('FWF_PERMISSION','Prerelease hanya di environment local atau staging. Kosongkan tag untuk stable.'); }
        if (!preg_match('/^v?\d+\.\d+\.\d+-(alpha|beta|rc)\.[1-9]\d*$/D',$tag)) { return new \WP_Error('FWF_VALIDATION','Tag prerelease harus seperti v0.1.0-alpha.3.'); }
        return true;
    }
    public static function validate(array $manifest, string $tag=''): array|\WP_Error {
        $selection=self::validateSelection($tag);if(is_wp_error($selection)){return $selection;}
        if (($manifest['schema']??0)!==1 || ($manifest['status']??'')!==($tag===''?'stable':'development') || ($manifest['dirty']??null)!==false || !is_string($manifest['source_commit']??null) || !preg_match('/^[a-f0-9]{40}$/D',$manifest['source_commit']) || !is_array($manifest['packages']??null) || !array_is_list($manifest['packages']) || ($tag!=='' && ($manifest['version']??null)!==preg_replace('/^v/','',$tag))) { return new \WP_Error('FWF_PACKAGE','Manifest harus cocok dengan jalur release dan berasal dari commit bersih.'); }
        $packages=[];
        foreach($manifest['packages'] as $p){
            if(!is_array($p) || !in_array($p['id']??'', ['falcon-wf','falcon-theme'],true) || isset($packages[$p['id']]) ||
              ($p['type']??'')!==($p['id']==='falcon-wf'?'plugin':'theme') ||
              !is_string($p['version']??null) || !preg_match($tag===''?'/^\d+\.\d+\.\d+$/D':'/^\d+\.\d+\.\d+(-(alpha|beta|rc)\.[1-9]\d*)?$/D',$p['version']) ||
              ($p['artifact']??'')!==$p['id'].'-'.$p['version'].'.zip' || !is_string($p['sha256']??null) || !preg_match('/^[a-f0-9]{64}$/D',$p['sha256'])){
              return new \WP_Error('FWF_PACKAGE','Package ID/type/version/hash tidak valid.');
            }
            if(!is_string($p['min_wp']??null) || !is_string($p['min_php']??null) || !preg_match('/^\d+\.\d+(\.\d+)?$/D',$p['min_wp']) || !preg_match('/^\d+\.\d+(\.\d+)?$/D',$p['min_php']) || version_compare($GLOBALS['wp_version'],$p['min_wp'],'<') || version_compare(PHP_VERSION,$p['min_php'],'<')) {return new \WP_Error('FWF_COMPATIBILITY','Runtime tidak memenuhi requirement paket.');}
            $packages[$p['id']]=$p;
        }
        if(!$packages){return new \WP_Error('FWF_PACKAGE','Tidak ada paket.');}return $packages;
    }
    public function check(): array|\WP_Error {
        if(!current_user_can('fwf_manage_updates')){return new \WP_Error('FWF_PERMISSION','Tidak diizinkan cek update.');}
        $repo=(string)get_option('fwf_repo','');$tag=(string)get_option('fwf_update_tag','');delete_option('fwf_release_candidate');$release=(new GitHubClient($repo))->release($tag);
        if(is_wp_error($release)){return $release;}
        $packages=self::validate($release['manifest'],$tag);if(is_wp_error($packages)){return $packages;}
        foreach($packages as $p){if(!isset($release['assets'][$p['artifact']])){return new \WP_Error('FWF_PACKAGE','Artifact release hilang.');}}
        $release['repo']=$repo;$release['selection_tag']=$tag;$release['checked_at']=time(); update_option('fwf_release_candidate',$release,false);
        Logger::write('release_check','succeeded');return $packages;
    }
    public function update(string $id, bool $backupConfirmed): true|\WP_Error {
        $cap=$id==='falcon-wf'?'update_plugins':'update_themes';
        if(!in_array($id,['falcon-wf','falcon-theme'],true) || !current_user_can('fwf_manage_updates') || !current_user_can($cap)){return new \WP_Error('FWF_PERMISSION','Target atau izin update ditolak.');}
        if(!$backupConfirmed){return new \WP_Error('FWF_PERMISSION','Konfirmasikan backup dan staging sebelum update.');}
        if(defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS){return new \WP_Error('FWF_FILESYSTEM','Environment immutable: gunakan pipeline.');}
        require_once ABSPATH.'wp-admin/includes/file.php';require_once ABSPATH.'wp-admin/includes/plugin.php';require_once ABSPATH.'wp-admin/includes/class-wp-upgrader.php';
        if(get_filesystem_method()!=='direct'){return new \WP_Error('FWF_FILESYSTEM','Gunakan alur credential WordPress/pipeline untuk filesystem ini.');}
        $owner=Lock::acquire('updates');if(is_wp_error($owner)){return $owner;}$tmp=null;
        try {
            // Refetch trusted metadata at apply time; do not trust stale client/option input.
            $tag=(string)get_option('fwf_update_tag','');$release=(new GitHubClient((string)get_option('fwf_repo','')))->release($tag);if(is_wp_error($release)){return $release;}
            $packages=self::validate($release['manifest'],$tag);if(is_wp_error($packages)){return $packages;}
            $p=$packages[$id]??null;if(!$p || !isset($release['assets'][$p['artifact']])){return new \WP_Error('FWF_PACKAGE','Target package tidak tersedia.');}
            $candidate=get_option('fwf_release_candidate',[]);
            $reviewed=self::validate(is_array($candidate['manifest']??null)?$candidate['manifest']:[],$tag);
            if(($candidate['repo']??null)!==get_option('fwf_repo','') || ($candidate['selection_tag']??null)!==$tag || is_wp_error($reviewed) || ($reviewed[$id]??null)!==$p || ($candidate['tag']??null)!==$release['tag']){return new \WP_Error('FWF_COMPATIBILITY','Release berubah atau belum diperiksa. Periksa ulang dan review versi sebelum update.');}
            $current=$id==='falcon-wf'?get_plugin_data(WP_PLUGIN_DIR.'/falcon-wf/falcon-wf.php')['Version']:wp_get_theme('falcon-theme')->get('Version');
            if(!$current || version_compare($current,$p['version'],'>=')){return new \WP_Error('FWF_COMPATIBILITY','Target belum terpasang atau tidak lebih baru. Tidak downgrade.');}
            $tmp=(new GitHubClient((string)get_option('fwf_repo','')))->asset($release['assets'][$p['artifact']],true);if(is_wp_error($tmp)){return $tmp;}
            $verified=self::verifyZip($tmp,$p);if(is_wp_error($verified)){return $verified;}
            $audit=Logger::write('package_update','started');if(is_wp_error($audit)){return $audit;}
            $key=$id==='falcon-wf'?'update_plugins':'update_themes';$previous=get_site_transient($key);
            $transient=is_object($previous)?clone $previous:new \stdClass();$transient->response=$transient->response??[];
            $target=$id==='falcon-wf'?'falcon-wf/falcon-wf.php':'falcon-theme';
            $item=['new_version'=>$p['version'],'package'=>$tmp,'url'=>'https://github.com/'.get_option('fwf_repo',''),'requires'=>$p['min_wp'],'requires_php'=>$p['min_php']];
            if($id==='falcon-wf'){$item['slug']='falcon-wf';$item['plugin']=$target;$item=(object)$item;}
            $transient->response[$target]=$item;set_site_transient($key,$transient);
            try {
                $upgrader=$id==='falcon-wf'?new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin()):new \Theme_Upgrader(new \WP_Ajax_Upgrader_Skin());
                if($id==='falcon-wf'){
                    // WordPress' single-plugin upgrade deactivates the plugin; its bulk path preserves activation.
                    // Still one explicitly selected component, never a multi-package update.
                    $results=$upgrader->bulk_upgrade([$target]);
                    $itemResult=is_array($results)?($results[$target]??false):false;
                    $result=$itemResult===true || is_array($itemResult);
                }else{$result=$upgrader->upgrade($target);}
            } finally { if($previous===false){delete_site_transient($key);}else{set_site_transient($key,$previous);} }
            Logger::write('package_update',$result===true?'succeeded':'failed');
            if($result!==true){return new \WP_Error('FWF_FILESYSTEM','Update gagal. Periksa WordPress recovery/backup; database tidak di-rollback otomatis.');}
            return true;
        } finally {if(is_string($tmp) && is_file($tmp)){wp_delete_file($tmp);}Lock::release('updates',$owner);}
    }
    public static function verifyZip(string $path,array $package): true|\WP_Error {
        if(!is_file($path) || filesize($path)>20*1024*1024 || !hash_equals($package['sha256'],hash_file('sha256',$path)) || !class_exists('ZipArchive')){return new \WP_Error('FWF_PACKAGE','Paket corrupt atau ZIP tidak didukung.');}
        $zip=new \ZipArchive();if($zip->open($path)!==true){return new \WP_Error('FWF_PACKAGE','ZIP tidak valid.');}
        try {
            $seen=[];$total=0;$root=$package['id'].'/';
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i);$name=$stat['name'];$total+=$stat['size'];$os=0;$attr=0;$zip->getExternalAttributesIndex($i,$os,$attr);
                if(!str_starts_with($name,$root) || str_contains($name,'..') || str_contains($name,'\\') || isset($seen[$name]) || (($attr>>16)&0170000)===0120000 || $total>40*1024*1024){return new \WP_Error('FWF_PACKAGE','ZIP path/ukuran tidak aman.');}$seen[$name]=true;
            }
            $header=$zip->getFromName($root.($package['id']==='falcon-wf'?'falcon-wf.php':'style.css'));
            if(!is_string($header) || !preg_match('/^[ *]*Version:\s*(.+)$/m',$header,$match) || trim($match[1])!==$package['version']){return new \WP_Error('FWF_PACKAGE','Versi header tidak cocok.');}
            if($package['id']==='falcon-theme' && !isset($seen[$root.'index.php'])){return new \WP_Error('FWF_PACKAGE','Theme tanpa entrypoint.');}
            return true;
        } finally {$zip->close();}
    }
}
