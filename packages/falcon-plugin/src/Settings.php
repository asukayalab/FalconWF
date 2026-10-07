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
}
