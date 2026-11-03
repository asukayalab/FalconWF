<?php
namespace FalconWF\Updates;
/** Metadata observations only. Human checks alone own install candidates. */
final class Monitor {
    public static function register(): void {
        add_action('fwf_update_check',[self::class,'run']);
        add_action('admin_notices',[self::class,'notice']);
        if(!wp_next_scheduled('fwf_update_check')){wp_schedule_event(time()+HOUR_IN_SECONDS,'twicedaily','fwf_update_check');}
    }
    public static function run(): void {
        $observations=[];
        foreach(['core'=>new UpdateManager(),'project'=>new ProjectManager()] as $kind=>$manager){
            if($kind==='project' && !ProjectManager::connection()){continue;}
            $source=$kind==='core'?['repo'=>UpdateManager::repo(),'channel'=>UpdateManager::channel(),'tag'=>get_option('fwf_update_tag','')]:ProjectManager::connection();
            $release=$manager->discover();
            $observations[$kind]=['source'=>$source,'checked_at'=>time(),'error'=>is_wp_error($release)?$release->get_error_message():'','packages'=>is_wp_error($release)?[]:$release['manifest']['packages']];
        }
        update_option('fwf_update_observations',$observations,false);
    }
    public static function status(): array {
        $value=get_option('fwf_update_observations',[]);$valid=[];
        if(!is_array($value)){return [];}
        foreach($value as $kind=>$row){
            $source=$kind==='core'?['repo'=>UpdateManager::repo(),'channel'=>UpdateManager::channel(),'tag'=>get_option('fwf_update_tag','')]:ProjectManager::connection();
            if(in_array($kind,['core','project'],true) && is_array($row) && ($row['source']??null)===$source){$valid[$kind]=$row;}
        }
        return $valid;
    }
    public static function notice(): void {
        if(!current_user_can('fwf_manage_updates')){return;}
        $labels=[];
        foreach(self::status() as $kind=>$row){
            if(time()-(int)$row['checked_at']>2*DAY_IN_SECONDS){continue;}
            foreach($row['packages'] as $p){
                $current=$kind==='core'?UpdateManager::installedVersion($p['id']):(string)wp_get_theme($p['id'])->get('Version');
                if($current!=='' && version_compare($p['version'],$current,'>')){$label=$kind==='core'?(['falcon-wf'=>'Falcon Plugin','falcon-theme'=>'Falcon Theme'][$p['id']]??$p['id']):(wp_get_theme($p['id'])->get('Name')?:$p['id']);$labels[]=$label.' '.$p['version'];}
            }
        }
        if($labels){echo '<div class="notice notice-info"><p>'.esc_html('Pembaruan Falcon tersedia: '.implode(', ',$labels)).'. <a href="'.esc_url(admin_url('admin.php?page=falcon-wf-updates')).'">Periksa dan review pembaruan</a>. Tidak dipasang otomatis.</p></div>';}
    }
    public static function render(): void {
        echo '<p>Falcon memeriksa metadata core dan proyek dua kali sehari melalui WP-Cron. Eksekusi mengikuti kunjungan situs atau cron server; pemasangan selalu dipilih manusia.</p>';
        foreach(self::status() as $kind=>$row){echo '<p>'.esc_html(($kind==='core'?'Core':'Proyek').' · Cek berkala terakhir: '.wp_date('d M Y, H:i',(int)$row['checked_at']).($row['error']?' · '.$row['error']:' · Metadata berhasil diperiksa.')).'</p>';}
    }
}
