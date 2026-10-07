<?php
namespace FalconWF\Api;
use FalconWF\AI\AgentTools;
use FalconWF\AI\InboundAuth;
use FalconWF\Audit\Logger;
use FalconWF\Content\Repository;
final class Routes {
    public function __construct(private Repository $content, private AgentTools $tools) {}
    public function register(): void {
        register_rest_route('falcon-wf/v1','/health',['methods'=>'GET','permission_callback'=>static fn()=>current_user_can('fwf_manage_system'), 'callback'=>static fn()=>\FalconWF\Health::report()]);
        register_rest_route('falcon-wf/v1','/content/(?P<id>\d+)',['methods'=>'GET','permission_callback'=>function ($r) {
            $o=$this->content->get((int)$r['id']); return !is_wp_error($o) && ($o['status']==='publish' || current_user_can('edit_post',$o['id']));
        }, 'callback'=>function ($r) {
            $o=$this->content->get((int)$r['id']);
            if (!is_wp_error($o) && !current_user_can('edit_post',$o['id'])) {
                foreach (\FalconWF\Content\Schema::custom($o['type']) as $key=>$d) { if (empty($d['public'])) { unset($o['fields'][$key]); } }
            }
            return $o;
        }]);
        register_rest_route('falcon-wf/v1','/mcp',['methods'=>'POST','permission_callback'=>static fn()=>InboundAuth::allowed(), 'callback'=>[$this,'mcp']]);
    }
    public function mcp(\WP_REST_Request $request): \WP_REST_Response {
        $origin=$request->get_header('origin');
        if ($origin && rtrim($origin,'/')!==rtrim((string)preg_replace('#^(https?://[^/]+).*$#','$1',home_url()),'/')) {
            return new \WP_REST_Response(['error'=>'Origin ditolak.'],403);
        }
        if (strlen($request->get_body())>65536) { return new \WP_REST_Response(['error'=>'Request terlalu besar.'],413); }
        $p=$request->get_json_params();
        if (!is_array($p) || ($p['jsonrpc']??'')!=='2.0' || !is_string($p['method']??null) || (isset($p['params']) && !is_array($p['params']))) { return $this->rpcError(null,-32600,'Request JSON-RPC tidak valid.'); }
        $id=$p['id']??null; $method=$p['method'];
        if ($method==='notifications/initialized' && !isset($p['id'])) { return new \WP_REST_Response(null,202); }
        if (!isset($p['id']) || (!is_int($id) && !is_string($id))) { return $this->rpcError(null,-32600,'Request ID diperlukan.'); }
        $result=match ($method) {
            'initialize'=>['protocolVersion'=>'2025-03-26','capabilities'=>['tools'=>['listChanged'=>false]],'serverInfo'=>['name'=>'Falcon WF','version'=>'0.1.0-alpha.2']],
            'ping'=>[],
            'tools/list'=>['tools'=>$this->definitions()],
            'tools/call'=>$this->invoke($p['params']??[]),
            default=>new \WP_Error('FWF_METHOD','Method tidak tersedia.'),
        };
        if (is_wp_error($result)) { return $this->rpcError($id,-32601,$result->get_error_message()); }
        return new \WP_REST_Response(['jsonrpc'=>'2.0','id'=>$id,'result'=>$result],200,['Content-Type'=>'application/json']);
    }
    private function rpcError(mixed $id,int $code,string $message): \WP_REST_Response { return new \WP_REST_Response(['jsonrpc'=>'2.0','id'=>$id,'error'=>['code'=>$code,'message'=>$message]],400); }
    private function invoke(array $params): array {
        if (!is_string($params['name']??null) || !is_array($params['arguments']??[])) { return ['isError'=>true,'content'=>[['type'=>'text','text'=>'FWF_VALIDATION: Tool arguments tidak valid.']]]; }
        $result=$this->tools->call($params['name'],$params['arguments']??[]);
        $audit=Logger::write('agent_' . $params['name'],is_wp_error($result)?$result->get_error_code():'succeeded',is_wp_error($result)?0:($result['id']??0));
        if (is_wp_error($result)) { return ['isError'=>true,'content'=>[['type'=>'text','text'=>$result->get_error_code() . ': ' . $result->get_error_message()]]]; }
        if (is_wp_error($audit)) { return ['isError'=>true,'content'=>[['type'=>'text','text'=>'FWF_AUDIT: Hasil operasi perlu diperiksa; audit final gagal.']]]; }
        return ['content'=>[['type'=>'text','text'=>wp_json_encode($result)]]];
    }
    private function definitions(): array {
        $fields=['type'=>'object','properties'=>\FalconWF\Content\Schema::jsonProperties(), 'additionalProperties'=>false];
        return [
            ['name'=>'describe_schema','description'=>'Describe permitted fields for one scoped content type. Read only.','inputSchema'=>['type'=>'object','properties'=>['type'=>['type'=>'string','enum'=>$this->content->types()]],'required'=>['type'],'additionalProperties'=>false]],
            ['name'=>'read_content','description'=>'Read explicitly scoped published content or opt-in draft fields.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'integer','minimum'=>1],'fields'=>['type'=>'array','items'=>['type'=>'string','enum'=>\FalconWF\Content\Schema::keys()]]],'required'=>['id'],'additionalProperties'=>false]],
            ['name'=>'create_draft','description'=>'Create draft only; never publish. Retry with same idempotency key.','inputSchema'=>['type'=>'object','properties'=>['type'=>['type'=>'string','enum'=>$this->content->types()],'fields'=>$fields,'idempotency_key'=>['type'=>'string']],'required'=>['type','fields','idempotency_key'],'additionalProperties'=>false]],
            ['name'=>'edit_draft','description'=>'Edit explicitly scoped draft with current revision; never publish.','inputSchema'=>['type'=>'object','properties'=>['id'=>['type'=>'integer'],'expected_revision'=>['type'=>'string'],'fields'=>$fields,'idempotency_key'=>['type'=>'string']],'required'=>['id','expected_revision','fields','idempotency_key'],'additionalProperties'=>false]],
        ];
    }
}
