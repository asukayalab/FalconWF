<?php
namespace FalconWF;
final class Settings {
    public static function identity(): array {
        return array_merge(['name' => '', 'contact' => ''], (array) get_option('fwf_identity', []));
    }
    public static function saveIdentity(array $input): true|\WP_Error {
        if (array_diff(array_keys($input), ['name', 'contact'])) { return new \WP_Error('FWF_VALIDATION', 'Field identitas tidak dikenal.'); }
        if (strlen((string)($input['name'] ?? '')) > 150 || strlen((string)($input['contact'] ?? '')) > 500) { return new \WP_Error('FWF_VALIDATION', 'Identitas terlalu panjang.'); }
        update_option('fwf_identity', ['name' => sanitize_text_field($input['name'] ?? ''), 'contact' => sanitize_textarea_field($input['contact'] ?? '')], false);
        return true;
    }
    public static function saveSeo(mixed $mode, mixed $revision): true|\WP_Error {
        if (!current_user_can('fwf_manage_system')) { return new \WP_Error('FWF_PERMISSION','Tidak diizinkan.'); }
        if (get_template()!=='falcon-theme' || !class_exists('FalconTheme\\Seo')) { return new \WP_Error('FWF_SEO_UNAVAILABLE','Falcon Theme aktif diperlukan.'); }
        if (!is_string($mode) || !in_array($mode,['external','falcon'],true) || !is_string($revision)) { return new \WP_Error('FWF_VALIDATION','Mode SEO tidak valid.'); }
        $owner=Packages\Lock::acquire('seo'); if (is_wp_error($owner)) { return $owner; }
        try {
            if (!hash_equals(\FalconTheme\Seo::revision(),$revision)) { return new \WP_Error('FWF_CONFLICT','Pengaturan SEO berubah. Muat ulang.'); }
            $value=['mode'=>$mode]; update_option('fwf_seo',$value,false);
            return get_option('fwf_seo')===$value?true:new \WP_Error('FWF_DB','Pengaturan SEO gagal disimpan.');
        } finally { Packages\Lock::release('seo',$owner); }
    }
    public static function saveDesign(mixed $input, mixed $theme, mixed $revision): true|\WP_Error {
        if (get_template()!=='falcon-theme' || !class_exists('FalconTheme\\Design')) { return new \WP_Error('FWF_DESIGN_UNAVAILABLE','Aktifkan Falcon Theme atau child theme yang kompatibel melalui Appearance → Themes.'); }
        if (!is_string($theme) || !is_string($revision) || $theme!==get_stylesheet()) { return new \WP_Error('FWF_CONFLICT','Theme berubah. Muat ulang pengaturan desain.'); }
        $target='design_'.$theme; $owner=Packages\Lock::acquire($target); if (is_wp_error($owner)) { return $owner; }
        try {
            if (!hash_equals(\FalconTheme\Design::revision(),$revision)) { return new \WP_Error('FWF_CONFLICT','Desain sudah berubah. Muat ulang sebelum menyimpan.'); }
            $values=\FalconTheme\Design::validate($input); if (is_wp_error($values)) { return $values; }
            $key='fwf_design_'.$theme;
            if (!$values) { delete_option($key); } else { update_option($key,$values,false); }
            return true;
        } finally { Packages\Lock::release($target,$owner); }
    }
}
