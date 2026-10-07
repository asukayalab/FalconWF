<?php
namespace FalconWF\Installer;
use FalconWF\Audit\Logger;
use FalconWF\Packages\Lock;
use FalconWF\Packages\Verifier;
final class ThemeInstaller {
    public function __construct(private string $root) {}
    public function readiness(): array|\WP_Error {
        $bundle=Verifier::bundle($this->root); if (is_wp_error($bundle)) { return $bundle; }
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) { return new \WP_Error('FWF_FILESYSTEM','Perubahan file dinonaktifkan. Gunakan pipeline deployment.'); }
        require_once ABSPATH.'wp-admin/includes/file.php';
        $themeRoot=get_theme_root();
        if (get_filesystem_method([], $themeRoot)!=='direct') { return new \WP_Error('FWF_FILESYSTEM','Hosting memerlukan credential filesystem. Gunakan Appearance → Themes → Add New untuk alur credential WordPress.'); }
        $temp=get_temp_dir();
        if (!is_writable($themeRoot) || !is_writable($temp)) { return new \WP_Error('FWF_FILESYSTEM','Folder theme atau folder sementara tidak dapat ditulis. Perbaiki permission sebelum mencoba lagi.'); }
        $required=2*$bundle['expanded_bytes']+filesize($this->root.'/bundles/falcon-theme.zip')+1024*1024;
        foreach (array_unique([$themeRoot,$temp]) as $path) {
            $free=@disk_free_space($path);
            if ($free!==false && $free<$required) { return new \WP_Error('FWF_DISK','Ruang disk tidak cukup untuk ekstraksi paket. Kosongkan ruang lalu periksa ulang.'); }
        }
        return $bundle;
    }
    public function install(): true|\WP_Error {
        if (!current_user_can('fwf_manage_system') || !current_user_can('install_themes')) { return new \WP_Error('FWF_PERMISSION', 'Tidak diizinkan memasang theme.'); }
        if (defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS) { return new \WP_Error('FWF_FILESYSTEM', 'Code immutable. Pasang FT melalui pipeline deployment.'); }
        $bundle = Verifier::bundle($this->root);
        if (is_wp_error($bundle)) { return $bundle; }
        $owner = Lock::acquire('theme'); if (is_wp_error($owner)) { return $owner; }
        try {
            $theme = wp_get_theme('falcon-theme');
            if ($theme->exists()) {
                if ($theme->errors()) { return new \WP_Error('FWF_PACKAGE','FT terdeteksi tetapi tidak valid. Periksa folder/theme melalui Site Health; installer tidak menghapus atau menimpa file.'); }
                if (version_compare($theme->get('Version'), $bundle['version'], '>=')) { return true; }
                return new \WP_Error('FWF_COMPATIBILITY', 'FT lama sudah terpasang. Gunakan alur pembaruan setelah backup; installer tidak menimpa theme.');
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            $ready=$this->readiness(); if (is_wp_error($ready)) { return $ready; }
            $audit = Logger::write('theme_install', 'started'); if (is_wp_error($audit)) { return $audit; }
            $upgrader = new \Theme_Upgrader(new \WP_Ajax_Upgrader_Skin());
            update_option('fwf_setup_attempt',['state'=>'installing','at'=>time()],false);
            $result = $upgrader->install($this->root . '/bundles/falcon-theme.zip', ['overwrite_package'=>false]);
            if ($result !== true) {
                update_option('fwf_setup_attempt',['state'=>'failed','at'=>time()],false);
                Logger::write('theme_install', 'failed');
                return new \WP_Error('FWF_FILESYSTEM', 'FT gagal dipasang. Periksa permission, ruang disk dan Site Health. Theme aktif dipertahankan.');
            }
            wp_clean_themes_cache();
            if (!wp_get_theme('falcon-theme')->exists() || wp_get_theme('falcon-theme')->errors()) { update_option('fwf_setup_attempt',['state'=>'failed','at'=>time()],false); return new \WP_Error('FWF_PACKAGE','Hasil instalasi theme tidak valid. Periksa Site Health sebelum aktivasi.'); }
            update_option('fwf_setup_attempt',['state'=>'installed','at'=>time()],false);
            update_option('fwf_setup', 'installed', false);
            $logged = Logger::write('theme_install', 'succeeded');
            return is_wp_error($logged) ? new \WP_Error('FWF_AUDIT', 'FT terpasang, tetapi audit final gagal. Periksa sebelum retry.') : true;
        } finally { Lock::release('theme', $owner); }
    }
    public function activate(): true|\WP_Error {
        if (!current_user_can('fwf_manage_system') || !current_user_can('switch_themes')) { return new \WP_Error('FWF_PERMISSION', 'Tidak diizinkan mengganti theme.'); }
        $theme = wp_get_theme('falcon-theme');
        if (!$theme->exists() || $theme->errors()) { return new \WP_Error('FWF_PACKAGE', 'FT belum terpasang atau tidak valid.'); }
        $audit = Logger::write('theme_switch', 'started'); if (is_wp_error($audit)) { return $audit; }
        switch_theme('falcon-theme');
        update_option('fwf_setup', 'activated', false);
        Logger::write('theme_switch', 'succeeded');
        return true;
    }
}
