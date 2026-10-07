<?php
namespace FalconWF\AI;
final class InboundAuth {
    private static ?string $uuid = null;
    public static function register(): void {
        add_action('wp_authenticate_application_password_errors', static function ($errors, $user) {
            if (in_array('fwf_agent', (array)$user->roles, true) && (!defined('REST_REQUEST') || !REST_REQUEST)) {
                $errors->add('FWF_PERMISSION', 'Application password agent hanya untuk REST MCP.');
            }
        }, 10, 2);
        add_filter('rest_pre_dispatch', static function ($result, $server, $request) {
            if (in_array('fwf_agent', (array) wp_get_current_user()->roles, true) && $request->get_route() !== '/falcon-wf/v1/mcp') { return new \WP_Error('FWF_PERMISSION', 'Agent hanya dapat memakai tools FWF.', ['status'=>403]); }
            return $result;
        }, 10, 3);
        add_action('admin_init', static function () {
            if (in_array('fwf_agent', (array) wp_get_current_user()->roles, true)) { wp_die('User agent hanya untuk connector, bukan dashboard.', '', ['response'=>403]); }
        });
        add_action('application_password_did_authenticate',static function ($user,$item) { self::$uuid=$item['uuid']; },10,2);
    }
    public static function uuid(): ?string { return self::$uuid; }
    public static function allowed(): bool {
        $grant=get_option('fwf_agent_' . get_current_user_id(),[]);
        return isset($grant['uuid'],$grant['scope']['expires']) && self::$uuid !== null && hash_equals($grant['uuid'],self::$uuid) && $grant['scope']['expires']>=time() && is_user_logged_in() && self::$uuid !== null && in_array('fwf_agent',(array)wp_get_current_user()->roles,true)
            && (is_ssl() || wp_get_environment_type()==='local');
    }
}
