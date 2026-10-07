<?php
namespace FalconWF\Migrations;
use FalconWF\Packages\Lock;
use FalconWF\Audit\Logger;
final class Runner {
    public static function run(): true|\WP_Error {
        $version=(int)get_option('fwf_schema_version',0);
        if($version>1){return new \WP_Error('FWF_MIGRATION','Schema lebih baru dari kode ini. Jangan downgrade.');}
        if($version===1){return true;}
        $owner=Lock::acquire('migration');if(is_wp_error($owner)){return $owner;}
        try {
            $result=Logger::install();if(is_wp_error($result)){return $result;}
            add_option('fwf_identity',['name'=>'','contact'=>''],'',false);
            add_option('fwf_modules',[],'',false);add_option('fwf_setup','not_started','',false);
            if(!update_option('fwf_schema_version',1,false) && (int)get_option('fwf_schema_version')!==1){return new \WP_Error('FWF_MIGRATION','Schema version gagal disimpan.');}
            return true;
        } finally {Lock::release('migration',$owner);}
    }
}
