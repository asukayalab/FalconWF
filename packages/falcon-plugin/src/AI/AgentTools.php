<?php
namespace FalconWF\AI;
use FalconWF\Content\Repository;
use FalconWF\Content\Schema;
final class AgentTools {
    public function __construct(private Repository $content) {}
    public function call(string $tool, array $args): array|\WP_Error {
        if (!InboundAuth::allowed()) { return new \WP_Error('FWF_AUTH','Application password agent dan HTTPS diperlukan.'); }
        if (array_is_list($args) && $args) { return new \WP_Error('FWF_VALIDATION','Arguments harus object.'); }
        foreach (['id','expected_revision','type','idempotency_key'] as $key) {
            if (isset($args[$key]) && ($key==='id' ? !is_int($args[$key]) || $args[$key]<1 : !is_string($args[$key]))) { return new \WP_Error('FWF_VALIDATION','Tipe argument tidak valid.'); }
        }
        $rate=RequestGuard::limit(get_current_user_id()); if (is_wp_error($rate)) { return $rate; }
        if ($tool==='describe_schema') {
            $type=$args['type']??'';
            if (!in_array($type,$this->content->types(),true)) { return new \WP_Error('FWF_VALIDATION','Jenis tidak tersedia.'); }
            $grant=get_option('fwf_agent_'.get_current_user_id(),[]); $scope=$grant['scope']??[];
            $fields=array_values(array_intersect(Schema::keys($type),$scope['fields']??[]));
            $action=($scope['actions']??[])[0]??'';
            $policy=Policy::check($action,$type,0,$fields); if (is_wp_error($policy) || !$fields) { return new \WP_Error('FWF_PERMISSION','Schema di luar scope.'); }
            $definitions=[]; foreach ($fields as $key) { $definitions[$key]=Schema::custom($type)[$key]??['label'=>$key,'kind'=>'text']; }
            return ['type'=>$type,'fields'=>$definitions,'definition_revision'=>\FalconWF\Content\Definitions::revision()];
        }
        if ($tool==='read_content') {
            $object=$this->content->get(absint($args['id']??0)); if (is_wp_error($object)) { return $object; }
            $fields=$args['fields']??Schema::keys($object['type']);
            if (!is_array($fields) || !$fields || count(array_filter($fields,'is_string'))!==count($fields)) { return new \WP_Error('FWF_VALIDATION','Pilih field.'); }
            $policy=Policy::check($object['status']==='publish'?'read_published':'read_draft',$object['type'],$object['id'],$fields);
            if (is_wp_error($policy)) { return $policy; }
            if ($object['status']==='draft' && !current_user_can('edit_post',$object['id'])) { return new \WP_Error('FWF_PERMISSION','Draft bukan milik actor atau izin editor ditolak.'); }
            $object['fields']=array_intersect_key($object['fields'],array_flip($fields)); return $object;
        }
        if (!in_array($tool,['create_draft','edit_draft'],true)) { return new \WP_Error('FWF_PERMISSION','Tool tidak tersedia.'); }
        $fields=$args['fields']??[]; if (!is_array($fields)) { return new \WP_Error('FWF_VALIDATION','Fields harus object.'); }
        $id=$tool==='edit_draft'?absint($args['id']??0):0;
        $type=$args['type']??'post';
        if ($tool==='edit_draft') { $current=$this->content->get($id); if (is_wp_error($current)) { return $current; } $type=$current['type']; }
        $policy=Policy::check($tool,$type,$id,array_keys($fields)); if (is_wp_error($policy)) { return $policy; }
        return RequestGuard::run($tool,(string)($args['idempotency_key']??''),$args,function () use ($tool,$id,$type,$fields,$args) {
            // Recheck grant immediately before mutation, including after outbound work/locks.
            $policy=Policy::check($tool,$type,$id,array_keys($fields)); if (is_wp_error($policy)) { return $policy; }
            $result=$tool==='create_draft'?$this->content->create($type,$fields):$this->content->edit($id,(string)($args['expected_revision']??''),$fields);
            if (!is_wp_error($result) && $tool==='create_draft') {
                $key='fwf_agent_' . get_current_user_id(); $grant=get_option($key,[]);
                $grant['scope']['objects'][]=$result['id']; update_option($key,$grant,false);
            }
            if (!is_wp_error($result)) { $result['fields']=array_intersect_key($result['fields'],array_flip(array_keys($fields))); }
            return $result;
        });
    }
}
