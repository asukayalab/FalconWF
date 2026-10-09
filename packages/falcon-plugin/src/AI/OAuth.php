<?php
namespace FalconWF\AI;
use FalconWF\Packages\Lock;
/** OAuth transport only; content permissions remain owned by Policy/AgentTools. */
final class OAuth {
    private const SCOPE='falcon:content';
    private const PREFIX='fwf_oauth_';
    private static function secret(): string { return rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'='); }
    public static function resource(): string { return rest_url('falcon-wf/v1/mcp'); }
    public static function issuer(): string { return untrailingslashit(home_url('/fwf-oauth')); }
    public static function secure(): bool { return (is_ssl() || wp_get_environment_type()==='local') && !is_multisite(); }
    private static function error(string $code='invalid_grant'): \WP_Error { return new \WP_Error($code,'Koneksi OAuth ditolak.',['status'=>400]); }
    public static function register(): void {
        add_action('rest_api_init',static function () {
            foreach (['resource'=>[self::class,'resourceMetadata'],'server'=>[self::class,'serverMetadata']] as $path=>$callback) {
                register_rest_route('falcon-wf/v1','/oauth/'.$path,['methods'=>'GET','permission_callback'=>'__return_true','callback'=>$callback]);
            }
            register_rest_route('falcon-wf/v1','/oauth/token',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>static fn($r)=>self::tokenResponse($r)]);
            register_rest_route('falcon-wf/v1','/oauth/revoke',['methods'=>'POST','permission_callback'=>'__return_true','callback'=>static fn($r)=>self::revokeResponse($r)]);
        });
        add_action('template_redirect',[self::class,'discovery'], -100);
        add_action('admin_post_fwf_oauth_authorize',[self::class,'authorize']);
        add_action('admin_post_nopriv_fwf_oauth_authorize',static function () { auth_redirect(); });
        add_action('fwf_daily_cleanup',[self::class,'cleanup']);
        add_filter('rest_post_dispatch',static function ($response,$server,$request) {
            if (str_starts_with($request->get_route(),'/falcon-wf/v1/oauth/')) { $response->header('Cache-Control','no-store');$response->header('Pragma','no-cache'); }
            if ($request->get_route()==='/falcon-wf/v1/mcp' && $response->get_status()===401) {
                $response->header('WWW-Authenticate','Bearer resource_metadata="'.rest_url('falcon-wf/v1/oauth/resource').'", scope="'.self::SCOPE.'"');
            }
            return $response;
        },10,3);
    }
    public static function resourceMetadata(): array { return ['resource'=>self::resource(),'authorization_servers'=>[self::issuer()],'bearer_methods_supported'=>['header'],'scopes_supported'=>[self::SCOPE]]; }
    public static function serverMetadata(): array {
        return ['issuer'=>self::issuer(),'authorization_endpoint'=>admin_url('admin-post.php?action=fwf_oauth_authorize'),'token_endpoint'=>rest_url('falcon-wf/v1/oauth/token'),'revocation_endpoint'=>rest_url('falcon-wf/v1/oauth/revoke'),'authorization_response_iss_parameter_supported'=>true,'response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],'scopes_supported'=>[self::SCOPE]];
    }
    public static function discovery(): void {
        $issuer=wp_parse_url(self::issuer());$resource=wp_parse_url(self::resource());
        $path=wp_parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
        $serverPath='/.well-known/oauth-authorization-server'.($issuer['path']??'');
        $resourcePath='/.well-known/oauth-protected-resource'.($resource['path']??'');
        if ($path!==$serverPath && $path!==$resourcePath) { return; }
        if (!self::secure()) { status_header(403);exit; }
        status_header(200);nocache_headers();header('Content-Type: application/json');header('X-Content-Type-Options: nosniff');
        echo wp_json_encode($path===$serverPath?self::serverMetadata():self::resourceMetadata());exit;
    }
    public static function clients(): array { $clients=get_option(self::PREFIX.'clients',[]);return is_array($clients)?$clients:[]; }
    public static function addClient(string $name,string $redirect): array|\WP_Error {
        if (!current_user_can('fwf_manage_ai') || !self::secure()) { return self::error('access_denied'); }
        $url=wp_parse_url($redirect);$name=sanitize_text_field($name);
        $local=wp_get_environment_type()==='local' && in_array($url['host']??'',['localhost','127.0.0.1'],true);
        if (!$name || strlen($name)>100 || strlen($redirect)>1024 || !$url || !isset($url['host']) || isset($url['fragment']) || isset($url['user']) || isset($url['pass']) || preg_match('/[\x00-\x20\x7f]/',$redirect) || (($url['scheme']??'')!=='https' && !($local && ($url['scheme']??'')==='http'))) { return self::error('invalid_request'); }
        $lock=Lock::acquire('oauth_clients');if(is_wp_error($lock)){return $lock;}
        try {
            $clients=self::clients();if(count($clients)>=20){return self::error('invalid_request');}
            $id='fwf_'.self::secret();$client=['name'=>$name,'redirect'=>$redirect,'owner'=>get_current_user_id(),'revision'=>wp_generate_uuid4()];$clients[$id]=$client;
            update_option(self::PREFIX.'clients',$clients,false);if(get_option(self::PREFIX.'clients')!==$clients){return self::error('server_error');}
            return ['client_id'=>$id]+$client;
        }finally{Lock::release('oauth_clients',$lock);}
    }
    public static function removeClient(string $id): true|\WP_Error {
        if(!current_user_can('fwf_manage_ai')){return self::error('access_denied');}
        $lock=Lock::acquire('oauth_clients');if(is_wp_error($lock)){return $lock;}
        try{$clients=self::clients();unset($clients[$id]);update_option(self::PREFIX.'clients',$clients,false);return get_option(self::PREFIX.'clients',[])===$clients?true:self::error('server_error');}finally{Lock::release('oauth_clients',$lock);}
    }
    private static function client(string $id): array|\WP_Error {
        $client=self::clients()[$id]??null;
        if(!is_array($client) || !is_string($client['revision']??null) || !is_string($client['redirect']??null) || !is_string($client['name']??null) || !is_int($client['owner']??null)){return self::error('invalid_client');}
        return is_array($client) && user_can((int)($client['owner']??0),'fwf_manage_ai')?$client:self::error('invalid_client');
    }
    public static function authorization(array $p): array|\WP_Error {
        $p+=['scope'=>self::SCOPE];
        foreach(['client_id','redirect_uri','resource','response_type','code_challenge','code_challenge_method','state','scope'] as $key){if(!isset($p[$key]) || !is_string($p[$key])){return self::error('invalid_request');}}
        $client=self::client($p['client_id']);if(is_wp_error($client)){return $client;}
        if(!self::secure() || $p['response_type']!=='code' || $p['redirect_uri']!==$client['redirect'] || $p['resource']!==self::resource() || $p['scope']!==self::SCOPE || $p['code_challenge_method']!=='S256' || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$p['code_challenge']) || !$p['state'] || strlen($p['state'])>1024 || preg_match('/[\x00-\x1f\x7f]/',$p['state'])){return self::error('invalid_request');}
        return array_intersect_key($p,array_flip(['client_id','redirect_uri','resource','response_type','code_challenge','code_challenge_method','state','scope']));
    }
    private static function grant(int $actor): array|\WP_Error {
        $user=get_userdata($actor);$grant=get_option('fwf_agent_'.$actor,[]);
        if(!is_array($grant) || !is_string($grant['uuid']??null) || !is_string($grant['revision']??null) || !is_int($grant['scope']['expires']??null)){return self::error();}
        if(!$user || !in_array('fwf_agent',(array)$user->roles,true) || !is_array($grant) || !isset($grant['uuid'],$grant['revision'],$grant['scope']['expires']) || $grant['scope']['expires']<=time() || empty($grant['scope']['actions']) || empty($grant['scope']['types']) || empty($grant['scope']['fields']) || !\WP_Application_Passwords::get_user_application_password($actor,$grant['uuid'])){return self::error();}
        return $grant;
    }
    private static function valid(array $record): bool {
        if(($record['expires']??0)<=time() || ($record['resource']??'')!==self::resource() || !user_can((int)($record['owner']??0),'fwf_manage_ai')){return false;}
        $client=self::client($record['client_id']??'');$grant=self::grant((int)($record['actor']??0));
        return !is_wp_error($client) && !is_wp_error($grant) && hash_equals($client['revision'],$record['client_revision']??'') && hash_equals($grant['revision'],$record['grant_revision']??'') && hash_equals($grant['uuid'],$record['uuid']??'');
    }
    /** Requires the approval owner at the human boundary; returns code only to approved redirect. */
    public static function code(array $parameters,int $actor): string|\WP_Error {
        if(!current_user_can('fwf_manage_ai')){return self::error('access_denied');}
        $p=self::authorization($parameters);if(is_wp_error($p)){return $p;}$grant=self::grant($actor);if(is_wp_error($grant)){return $grant;}$client=self::client($p['client_id']);
        $audit=\FalconWF\Audit\Logger::write('oauth_consent','approved');if(is_wp_error($audit)){return self::error('server_error');}
        $code=self::secret();$record=$p+['actor'=>$actor,'owner'=>get_current_user_id(),'uuid'=>$grant['uuid'],'grant_revision'=>$grant['revision'],'client_revision'=>$client['revision'],'expires'=>time()+300,'session'=>wp_generate_uuid4()];
        return add_option(self::PREFIX.'code_'.hash('sha256',$code),$record,'',false)?$code:self::error('server_error');
    }
    public static function authorize(): void {
        if(!current_user_can('fwf_manage_ai') || !self::secure()){wp_die('Tidak diizinkan.','',['response'=>403]);}
        $p=self::authorization(wp_unslash($_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET));if(is_wp_error($p)){wp_die('Permintaan OAuth tidak valid.','',['response'=>400]);}
        $action='fwf_oauth_consent_'.hash('sha256',wp_json_encode($p));
        nocache_headers();header('Referrer-Policy: no-referrer');header('X-Frame-Options: DENY');header('Content-Security-Policy: frame-ancestors \'none\'');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            check_admin_referer($action);
            if(($_POST['decision']??'')==='deny'){$redirect=add_query_arg(['error'=>'access_denied','state'=>$p['state'],'iss'=>self::issuer()],$p['redirect_uri']);}
            elseif(($_POST['decision']??'')==='approve'){$code=self::code($p,absint($_POST['actor']??0));if(is_wp_error($code)){wp_die('Grant agent tidak valid. Simpan scope dan periksa application password.','',['response'=>400]);}$redirect=add_query_arg(['code'=>$code,'state'=>$p['state'],'iss'=>self::issuer()],$p['redirect_uri']);}
            else{wp_die('Pilih setuju atau tolak.','',['response'=>400]);}
            wp_redirect($redirect,303,'Falcon WF');exit;
        }
        $client=self::client($p['client_id']);
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hubungkan Falcon WF</title></head><body><main><h1>Hubungkan '.esc_html($client['name']).' ke Falcon WF</h1><p>Tujuan callback: '.esc_html($client['redirect']).'</p><p>Client hanya memakai scope agent yang dipilih. Tidak mendapat izin publish, delete, pengaturan atau update.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        echo '<input type="hidden" name="action" value="fwf_oauth_authorize">';foreach($p as $key=>$value){echo '<input type="hidden" name="'.esc_attr($key).'" value="'.esc_attr($value).'">';}wp_nonce_field($action);
        echo '<label>Actor dan scope yang disetujui <select name="actor" required><option value="">Pilih agent</option>';
        foreach(get_users(['role'=>'fwf_agent','number'=>100]) as $user){$g=self::grant($user->ID);if(is_wp_error($g)){continue;}$scope=$g['scope'];$label=$user->user_login.' · '.implode(', ',$scope['actions']).' · type: '.implode(', ',$scope['types']).' · field: '.implode(', ',$scope['fields']).' · ID: '.implode(', ',$scope['objects']);echo '<option value="'.esc_attr((string)$user->ID).'">'.esc_html($label).'</option>';}
        echo '</select></label><p>Akses mengikuti masa berlaku scope (maksimal30hari); token akses1jam. Client dapat memperbarui token selama grant masih berlaku. Cabut scope atau client untuk memutus akses.</p><button name="decision" value="approve">Setujui koneksi dengan scope ini</button> <button name="decision" value="deny">Tolak</button></form></main></body></html>';exit;
    }
    private static function issue(array $record): array|\WP_Error {
        $access=self::secret();$refresh=self::secret();$accessHash=hash('sha256',$access);$refreshHash=hash('sha256',$refresh);$grant=self::grant($record['actor']);
        if(is_wp_error($grant)){return $grant;}
        $common=array_intersect_key($record,array_flip(['client_id','resource','actor','owner','uuid','grant_revision','client_revision','session']));
        $accessRecord=$common+['expires'=>min(time()+HOUR_IN_SECONDS,$grant['scope']['expires']),'refresh_hash'=>$refreshHash];
        $refreshRecord=$common+['expires'=>$grant['scope']['expires'],'access_hash'=>$accessHash];
        if(!add_option(self::PREFIX.'access_'.$accessHash,$accessRecord,'',false)){return self::error('server_error');}
        if(!add_option(self::PREFIX.'refresh_'.$refreshHash,$refreshRecord,'',false)){delete_option(self::PREFIX.'access_'.$accessHash);return self::error('server_error');}
        return ['access_token'=>$access,'token_type'=>'Bearer','expires_in'=>$accessRecord['expires']-time(),'refresh_token'=>$refresh,'scope'=>self::SCOPE];
    }
    public static function exchange(array $p): array|\WP_Error {
        if(!self::secure()){return self::error('access_denied');}
        foreach(['grant_type','client_id','resource'] as $key){if(!isset($p[$key]) || !is_string($p[$key])){return self::error('invalid_request');}}
        if(isset($p['scope']) && $p['scope']!==self::SCOPE){return self::error('invalid_scope');}
        if(isset($p['client_secret'])){return self::error('invalid_client');}
        $refresh=$p['grant_type']==='refresh_token';if(!$refresh && $p['grant_type']!=='authorization_code'){return self::error('unsupported_grant_type');}
        $secret=$p[$refresh?'refresh_token':'code']??'';if(!is_string($secret) || !preg_match('/^[A-Za-z0-9_-]{43}$/D',$secret)){return self::error();}
        $hash=hash('sha256',$secret);$key=self::PREFIX.($refresh?'refresh_':'code_').$hash;$lock=Lock::acquire('oauth_'.$hash);if(is_wp_error($lock)){return self::error('temporarily_unavailable');}
        try{
            $r=get_option($key,false);if(!is_array($r) || !isset($r['session'])){return self::error();}
            $session=$r['session'];$sessionLock=Lock::acquire('oauth_session_'.$session);if(is_wp_error($sessionLock)){return self::error('temporarily_unavailable');}
            try {
            $r=get_option($key,false);if(!is_array($r) || !self::valid($r) || $p['client_id']!==$r['client_id'] || $p['resource']!==$r['resource']){return self::error();}
            if(!$refresh){$verifier=$p['code_verifier']??'';if(!is_string($verifier) || !preg_match('/^[A-Za-z0-9._~-]{43,128}$/D',$verifier) || ($p['redirect_uri']??null)!==$r['redirect_uri'] || !hash_equals($r['code_challenge'],rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'='))){return self::error();}}
            // Durable consume before token issuance; a crash cannot replay the credential.
            if(!delete_option($key)){return self::error('server_error');}
            if($refresh){$oldAccess=self::PREFIX.'access_'.$r['access_hash'];delete_option($oldAccess);if(get_option($oldAccess,false)!==false){return self::error('server_error');}}
            return self::issue($r);
            } finally {Lock::release('oauth_session_'.$session, $sessionLock);}
        }finally{Lock::release('oauth_'.$hash,$lock);}
    }
    public static function tokenResponse(\WP_REST_Request $request): \WP_REST_Response {
        if(strlen($request->get_body())>8192 || !str_starts_with(strtolower($request->get_header('content-type')),'application/x-www-form-urlencoded') || $request->get_header('authorization')){return new \WP_REST_Response(['error'=>'invalid_request'],400);}
        $result=self::exchange($request->get_body_params());return new \WP_REST_Response(is_wp_error($result)?['error'=>$result->get_error_code()]:$result,is_wp_error($result)?400:200);
    }
    public static function identity(string $hash): array|\WP_Error {
        $record=get_option(self::PREFIX.'access_'.$hash,false);
        $refresh=is_array($record)?get_option(self::PREFIX.'refresh_'.($record['refresh_hash']??''),false):false;
        return self::secure() && is_array($record) && is_array($refresh) && ($refresh['access_hash']??'')===$hash && self::valid($record)?$record:self::error('invalid_token');
    }
    public static function revokeResponse(\WP_REST_Request $request): \WP_REST_Response {
        if(!self::secure() || strlen($request->get_body())>8192 || !str_starts_with(strtolower($request->get_header('content-type')),'application/x-www-form-urlencoded') || $request->get_header('authorization')){return new \WP_REST_Response(['error'=>'invalid_request'],400);}
        $p=$request->get_body_params();$token=$p['token']??'';$client=$p['client_id']??'';
        if(is_string($token) && preg_match('/^[A-Za-z0-9_-]{43}$/D',$token) && is_string($client)){
            $hash=hash('sha256',$token);$lock=Lock::acquire('oauth_'.$hash);
            if(is_wp_error($lock)){return new \WP_REST_Response(['error'=>'temporarily_unavailable'],503);}
            try{foreach(['access','refresh'] as $type){$key=self::PREFIX.$type.'_'.$hash;$record=get_option($key,false);if(is_array($record) && ($record['client_id']??'')===$client && isset($record['session'])){
                $session=$record['session'];$sessionLock=Lock::acquire('oauth_session_'.$session);if(is_wp_error($sessionLock)){return new \WP_REST_Response(['error'=>'temporarily_unavailable'],503);}
                try{$record=get_option($key,false);if(!is_array($record)){continue;}
                    $refreshKey=$type==='refresh'?$key:self::PREFIX.'refresh_'.$record['refresh_hash'];
                    $accessKey=$type==='access'?$key:self::PREFIX.'access_'.$record['access_hash'];
                    delete_option($refreshKey);delete_option($accessKey);
                    if(get_option($refreshKey,false)!==false || get_option($accessKey,false)!==false){return new \WP_REST_Response(['error'=>'server_error'],503);}
                }finally{Lock::release('oauth_session_'.$session,$sessionLock);}
            }}}finally{Lock::release('oauth_'.$hash,$lock);}
        }
        return new \WP_REST_Response(null,200);
    }
    public static function cleanup(): void {
        global $wpdb;$rows=$wpdb->get_results($wpdb->prepare('SELECT option_name,option_value FROM '.$wpdb->options.' WHERE option_name LIKE %s',$wpdb->esc_like(self::PREFIX).'%'),ARRAY_A);
        foreach($rows as $row){$v=maybe_unserialize($row['option_value']);if(is_array($v) && isset($v['expires']) && $v['expires']<=time()){delete_option($row['option_name']);}}
    }
}
