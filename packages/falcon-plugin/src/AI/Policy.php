<?php
namespace FalconWF\AI;
use FalconWF\Content\Schema;
final class Policy {
    public static function grant(int $actor, string $uuid, array $scope): true|\WP_Error {
        if (!get_user_by('id',$actor) || !in_array('fwf_agent',(array)get_userdata($actor)->roles,true)) { return new \WP_Error('FWF_PERMISSION','Gunakan user khusus dengan role Falcon Agent.'); }
        $actions=$scope['actions']??[]; $fields=$scope['fields']??[];
        if (!$actions || array_diff($actions,['read_published','read_draft','create_draft','edit_draft']) || !$fields || array_diff($fields,Schema::keys()) || !preg_match('/^[a-f0-9-]{36}$/i',$uuid)) { return new \WP_Error('FWF_VALIDATION','Scope atau application password UUID tidak valid.'); }
        $scope=['actions'=>array_values($actions),'fields'=>array_values($fields),
            'objects'=>array_values(array_unique(array_map('absint',$scope['objects']??[]))),
            'types'=>array_values(array_intersect($scope['types']??[],\FalconWF\Bootstrap::instance()->content->types())),
            'expires'=>time()+30*DAY_IN_SECONDS];
        update_option('fwf_agent_' . $actor,['uuid'=>$uuid,'scope'=>$scope],false);
        return true;
    }
    public static function check(string $action, string $type, int $object, array $fields): true|\WP_Error {
        $grant=get_option('fwf_agent_' . get_current_user_id(),[]);
        $scope=$grant['scope']??[];
        if (!InboundAuth::uuid() || !hash_equals($grant['uuid']??'',InboundAuth::uuid()) || ($scope['expires']??0)<time() ||
            !in_array($action,$scope['actions']??[],true) || !in_array($type,$scope['types']??[],true) ||
            array_diff($fields,$scope['fields']??[]) || ($object>0 && !in_array($object,$scope['objects']??[],true))) {
            return new \WP_Error('FWF_PERMISSION','Identity/action/object/field scope ditolak.');
        }
        return true;
    }
    public static function revoke(int $actor): void { delete_option('fwf_agent_' . $actor); }
}
