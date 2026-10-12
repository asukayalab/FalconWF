<?php
namespace FalconWF\Backup;
use FalconWF\Packages\Lock;
/** One owner for private storage selection, including SHORTINIT rescue. */
final class Storage {
    private const OPTION='fwf_backup_directory';
    private static function error(string $message): \WP_Error { return new \WP_Error('FWF_BACKUP_STORAGE',$message); }
    private static function publicRoots(): array {
        $roots=[ABSPATH,WP_CONTENT_DIR];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) { $roots[]=$_SERVER['DOCUMENT_ROOT']; }
        if ((!defined('SHORTINIT') || !SHORTINIT) && function_exists('wp_get_upload_dir')) { $roots[]=wp_get_upload_dir()['basedir'];$roots[]=WP_PLUGIN_DIR;$roots[]=get_theme_root(); }
        return array_values(array_unique(array_filter(array_map('realpath',$roots))));
    }
    private static function validate(string $directory,array $roots): string|\WP_Error {
        if (!$directory || str_contains($directory,"\0") || !str_starts_with($directory,'/')) { return self::error('Gunakan folder privat dengan alamat lengkap pada server.'); }
        for ($part=rtrim($directory,'/');$part!=='' && $part!=='/';$part=dirname($part)) {
            if (is_link($part)) { return self::error('Folder backup atau folder induknya tidak boleh berupa symlink.'); }
        }
        $path=realpath($directory);
        if (!$path || !is_dir($path) || !is_writable($path) || (fileperms($path)&0077)!==0) { return self::error('Folder backup harus tersedia, dapat ditulis, dan privat (izin 0700).'); }
        foreach ($roots as $root) {
            if ($path===$root || str_starts_with($path,rtrim($root,'/').'/')) { return self::error('Folder backup harus di luar seluruh folder publik situs, termasuk document root server.'); }
        }
        return $path;
    }
    public static function directory(): string|\WP_Error {
        $record=get_option(self::OPTION,[]);$roots=self::publicRoots();
        if (is_array($record)) {
            $stored=$record['public_roots']??[];if (!is_array($stored) || count(array_filter($stored,'is_string'))!==count($stored)) { return self::error('Konfigurasi batas folder publik tidak valid; pengelola perlu memeriksa.'); }
            $roots=array_merge($roots,$stored);
        }
        if (defined('FWF_BACKUP_DIR')) {
            return is_string(FWF_BACKUP_DIR)?self::validate(FWF_BACKUP_DIR,$roots):self::error('Konfigurasi FWF_BACKUP_DIR harus berupa alamat folder privat.');
        }
        if (!is_array($record) || !is_string($record['path']??null)) { return self::error('Penyimpanan backup belum disiapkan. Klik Siapkan penyimpanan backup di bawah.'); }
        return self::validate($record['path'],$roots);
    }
    /** Explicit human setup only; never move archives or replace a working location. */
    public static function setup(string $directory=''): string|\WP_Error {
        if (!current_user_can('fwf_manage_system') || is_multisite()) { return self::error('Tidak diizinkan atau multisite tidak didukung.'); }
        $ready=self::directory();if (!is_wp_error($ready)) { return $ready; }
        if (defined('FWF_BACKUP_DIR') || get_option(self::OPTION,false)!==false) { return self::error('Lokasi backup sudah ditetapkan tetapi tidak dapat dipakai. Pengelola hosting perlu memperbaiki akses lokasi yang sama; arsip tidak dipindahkan.'); }
        $lease=Lock::acquire('backup_storage_setup');if (is_wp_error($lease)) { return $lease; }
        try {
            if (get_option(self::OPTION,false)!==false) { return self::directory(); }
            $roots=self::publicRoots();
            if ($directory==='') {
                $web=realpath($_SERVER['DOCUMENT_ROOT']??'');
                if (!$web || $web==='/' || !is_dir($web)) { return self::error('Folder publik server belum dapat dipastikan. Pengelola hosting perlu menyediakan folder privat melalui Pengaturan pengelola.'); }
                $directory=dirname($web).'/.falcon-wf-backups-'.substr(hash('sha256',ABSPATH),0,20);
                $marker=$directory.'/.falcon-site';$identity=hash('sha256',ABSPATH);
                foreach ($roots as $root) { if ($directory===$root || str_starts_with($directory,rtrim($root,'/').'/')) { return self::error('Folder otomatis tidak berada di luar seluruh folder publik. Pengelola hosting perlu menyediakan folder privat.'); } }
                if (!file_exists($directory)) {
                    if (!@mkdir($directory,0700)) { return self::error('Server tidak mengizinkan pembuatan folder privat otomatis. Minta pengelola hosting membuat folder di luar document root dengan izin 0700, lalu masukkan alamatnya melalui Pengaturan pengelola.'); }
                    if (!chmod($directory,0700) || file_put_contents($marker,$identity,LOCK_EX)!==strlen($identity) || !chmod($marker,0600)) { return self::error('Pembuatan penyimpanan privat belum selesai. Pengelola hosting perlu memeriksa folder; data tidak dihapus.'); }
                } elseif (is_link($directory) || is_link($marker) || !is_file($marker) || file_get_contents($marker)!==$identity) {
                    return self::error('Folder otomatis sudah ada tetapi kepemilikannya tidak cocok. Pengelola hosting perlu memeriksa; tidak ada file yang diubah.');
                }
            }
            $path=self::validate($directory,$roots);if (is_wp_error($path)) { return $path; }
            $record=['path'=>$path,'public_roots'=>$roots];
            if (!add_option(self::OPTION,$record,'',false) || get_option(self::OPTION)!==$record) { return self::error('Lokasi penyimpanan gagal disimpan. Coba lagi; folder dan isinya tetap dipertahankan.'); }
            return $path;
        } finally { Lock::release('backup_storage_setup',$lease); }
    }
}
