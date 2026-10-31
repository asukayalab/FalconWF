<?php
namespace FalconWF\Packages;
final class Verifier {
    public static function bundle(string $root): array|\WP_Error {
        $manifest = json_decode((string)@file_get_contents($root . '/installer-manifest.json'), true);
        if (!is_array($manifest) || ($manifest['id'] ?? '') !== 'falcon-wf' || ($manifest['theme']['id'] ?? '') !== 'falcon-theme' || ($manifest['theme']['artifact'] ?? '') !== 'bundles/falcon-theme.zip' || !preg_match('/^[a-f0-9]{64}$/', $manifest['theme']['sha256'] ?? '')) {
            return new \WP_Error('FWF_PACKAGE', 'Manifest installer tidak valid. Gunakan ZIP hasil build.');
        }
        $zip = $root . '/bundles/falcon-theme.zip';
        if (!is_file($zip) || !hash_equals($manifest['theme']['sha256'], hash_file('sha256', $zip))) { return new \WP_Error('FWF_PACKAGE', 'Checksum bundle FT tidak cocok.'); }
        if (!class_exists('ZipArchive')) { return new \WP_Error('FWF_ENV', 'Extension PHP ZIP diperlukan untuk validasi paket.'); }
        $archive = new \ZipArchive();
        if ($archive->open($zip) !== true) { return new \WP_Error('FWF_PACKAGE', 'Bundle ZIP rusak.'); }
        $size = 0; $seen = []; $valid = true;
        for ($i=0; $i<$archive->numFiles; $i++) {
            $stat = $archive->statIndex($i); $name = $stat['name'];
            $size += $stat['size'];
            $opsys = 0; $attr = 0; $archive->getExternalAttributesIndex($i, $opsys, $attr);
            if (!str_starts_with($name, 'falcon-theme/') || str_contains($name, '..') || str_contains($name, '\\') || str_contains($name, "\0") || isset($seen[$name]) || (($attr >> 16) & 0170000) === 0120000) { $valid = false; }
            $seen[$name] = true;
        }
        $style = $archive->getFromName('falcon-theme/style.css');
        $archive->close();
        if (!$valid || $size > 20*1024*1024 || !isset($seen['falcon-theme/index.php']) || !is_string($style) || !preg_match('/^Version:[ \t]*(.+)$/m', $style, $v) || trim($v[1]) !== $manifest['theme']['version']) { return new \WP_Error('FWF_PACKAGE', 'Isi/path/versi bundle FT tidak valid.'); }
        return $manifest['theme']+['expanded_bytes'=>$size];
    }
    public static function remote(string $path,array $package): true|\WP_Error {
        $project=$package['type']==='theme' && isset($package['parent']);
        $contract=$project?json_decode((string)@file_get_contents(dirname(__DIR__,2).'/resources/project-runtime.json'),true):null;
        if($project && (!is_array($contract) || ($contract['schema']??null)!==1)){return new \WP_Error('FWF_PACKAGE','Kontrak runtime proyek tidak tersedia. Gunakan installer Falcon lengkap.');}
        $archiveLimit=$project?$contract['max_archive_bytes']:20*1024*1024;
        $expandedLimit=$project?$contract['max_expanded_bytes']:40*1024*1024;
        if(!is_file($path)){return new \WP_Error('FWF_PACKAGE','File ZIP tidak tersedia. Ulangi pemeriksaan dan pengunduhan paket.');}
        if(filesize($path)>$archiveLimit){return new \WP_Error('FWF_PACKAGE','Ukuran ZIP melebihi batas paket yang didukung. Build ulang dengan aset yang lebih kecil.');}
        if(!hash_equals($package['sha256'],hash_file('sha256',$path))){return new \WP_Error('FWF_PACKAGE','Checksum ZIP tidak cocok dengan manifest. Paket belum dipasang; periksa kembali rilis sumber.');}
        if(!class_exists('ZipArchive')){return new \WP_Error('FWF_ENV','Extension PHP ZIP diperlukan untuk memeriksa paket.');}
        $zip=new \ZipArchive();if($zip->open($path)!==true){return new \WP_Error('FWF_PACKAGE','ZIP tidak valid.');}
        try {
            $seen=[];$total=0;$root=$package['id'].'/';
            for($i=0;$i<$zip->numFiles;$i++){
                $stat=$zip->statIndex($i);$name=$stat['name'];$total+=$stat['size'];$os=0;$attr=0;$zip->getExternalAttributesIndex($i,$os,$attr);
                if(!str_starts_with($name,$root) || str_contains($name,'..') || str_contains($name,'\\') || str_contains($name,"\0") || array_intersect(explode('/',rtrim($name,'/')),['','.']) || isset($seen[$name]) || (($attr>>16)&0170000)===0120000 || $total>$expandedLimit){return new \WP_Error('FWF_PACKAGE','ZIP path/ukuran tidak aman.');}$seen[$name]=true;
                if($project && !str_ends_with($name,'/')){
                    $relative=substr($name,strlen($root));$extension=pathinfo($relative,PATHINFO_EXTENSION);
                    if(!in_array($extension,array_merge($contract['text_extensions'],$contract['binary_extensions']),true)){return new \WP_Error('FWF_PACKAGE','Jenis aset atau ukuran runtime proyek tidak didukung.');}
                }
            }
            $header=$zip->getFromName($root.($package['type']==='plugin'?'falcon-wf.php':'style.css'));
            if(!is_string($header) || !preg_match('/^[ *]*Version:[ \t]*(.+)$/m',$header,$match) || trim($match[1])!==$package['version']){return new \WP_Error('FWF_PACKAGE','Versi header tidak cocok.');}
            if($project && (!preg_match('/^[ *]*Theme Name:[ \t]*(.+)$/m',$header,$name) || trim($name[1])==='')){return new \WP_Error('FWF_PACKAGE','Theme Name pada header proyek wajib tersedia.');}
            if($package['type']==='theme'){
                foreach($project?$contract['required_files']:['index.php'] as $required){if(!isset($seen[$root.$required])){return new \WP_Error('FWF_PACKAGE','Paket theme tidak lengkap: '.$required.' wajib tersedia. Build ulang paket proyek.');}}
            }
            if (isset($package['parent']) && (!preg_match('/^[ *]*Template:[ \t]*(.+)$/m',$header,$parent) || trim($parent[1])!==$package['parent'])) { return new \WP_Error('FWF_PACKAGE','Parent header tidak cocok dengan manifest.'); }
            return true;
        } finally {$zip->close();}
    }
}
