<?php
namespace FalconWF\Backup;
use FalconWF\Packages\Lock;
/** Calendar scheduling only; all snapshots and archive writes belong to Manager. */
final class Scheduler {
    public const HOOK='fwf_backup_scheduled';
    public static function policy(): array {
        $value=get_option('fwf_backup_schedule',[]);$default=['enabled'=>false,'frequency'=>'daily','weekday'=>'1','time'=>'02:00','timezone'=>wp_timezone_string(),'components'=>['database','media','settings'],'owner'=>0,'generation'=>'','media_selection'=>null];
        return is_array($value)?array_replace($default,$value):$default;
    }
    public static function revision(): string { return hash('sha256',wp_json_encode(self::policy())); }
    private static function put(string $key,array $value): void {
        update_option($key,$value,false);if (get_option($key)!==$value) { throw new \RuntimeException('Penyimpanan jadwal gagal.'); }
    }
    /** Calculate in calendar days so DST does not shift the chosen wall-clock time. */
    public static function next(array $policy,int $after): int {
        $zone=new \DateTimeZone($policy['timezone']);$now=(new \DateTimeImmutable('@'.$after))->setTimezone($zone);[$hour,$minute]=array_map('intval',explode(':',$policy['time']));
        for ($day=0;$day<9;$day++) {
            $candidate=$now->modify('+'.$day.' days')->setTime($hour,$minute);
            if ($candidate->getTimestamp()>$after && ($policy['frequency']==='daily' || $candidate->format('N')===$policy['weekday'])) { return $candidate->getTimestamp(); }
        }
        throw new \RuntimeException('Waktu jadwal tidak valid.');
    }
    private static function preservePending(array $state): void {
        $pending=$state['pending']??null;$runs=get_option('fwf_backup_schedule_runs',[]);
        if (is_array($pending) && is_string($pending['key']??null) && (!is_array($runs) || !isset($runs[$pending['key']]))) { self::record($pending); }
    }
    private static function arm(array $policy,array $state): void {
        if (!$policy['enabled'] || get_option('fwf_backup_schedule_paused',false) || !empty($state['blocked'])) { return; }
        $args=[$policy['generation'],$state['next']];
        if (!wp_next_scheduled(self::HOOK,$args)) {
            $result=wp_schedule_single_event($state['next'],self::HOOK,$args,true);
            if (is_wp_error($result) || !$result) { throw new \RuntimeException('WP-Cron gagal menjadwalkan backup. Periksa cron lalu simpan ulang jadwal.'); }
        }
    }
    public static function save(array $input,mixed $revision): true|\WP_Error {
        $lease=Lock::acquire('backup_schedule');if (is_wp_error($lease)) { return $lease; }
        $old=get_option('fwf_backup_schedule',false);$oldState=get_option('fwf_backup_schedule_state',false);$oldPause=get_option('fwf_backup_schedule_paused',false);$changed=false;
        try {
            if (!current_user_can('fwf_manage_system') || is_multisite()) { throw new \RuntimeException('Tidak diizinkan.'); }
            if (!is_string($revision) || !hash_equals(self::revision(),$revision)) { throw new \RuntimeException('Jadwal berubah. Muat ulang halaman.'); }
            if (array_diff(array_keys($input),['enabled','frequency','weekday','time','components','media_selection']) || !is_bool($input['enabled']??null) || !in_array($input['frequency']??null,['daily','weekly'],true) || !is_string($input['weekday']??null) || !preg_match('/^[1-7]$/D',$input['weekday']) || !is_string($input['time']??null) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$input['time'])) { throw new \RuntimeException('Frekuensi, hari atau jam tidak valid.'); }
            $parts=$input['components']??null;if (!is_array($parts) || !$parts || count(array_filter($parts,'is_string'))!==count($parts) || count(array_unique($parts,SORT_REGULAR))!==count($parts) || array_diff($parts,array_keys(Manager::components()))) { throw new \RuntimeException('Pilih komponen backup berkala yang valid.'); }
            $media=MediaSelection::ids($input['media_selection']??null);if ($media!==null) { if (!in_array('media',$parts,true) || in_array('database',$parts,true)) { throw new \RuntimeException('Media terpilih memerlukan media tanpa database seluruh situs.'); }MediaSelection::snapshot($media,false); }$input['media_selection']=$media;
            $policy=$input+['timezone'=>wp_timezone_string(),'owner'=>get_current_user_id(),'generation'=>wp_generate_uuid4()];$state=['generation'=>$policy['generation'],'next'=>$policy['enabled']?self::next($policy,time()):0,'claimed'=>0,'blocked'=>''];
            if (is_array($oldState)) { self::preservePending($oldState); }
            $changed=true;self::put('fwf_backup_schedule',$policy);self::put('fwf_backup_schedule_state',$state);delete_option('fwf_backup_schedule_paused');
            $cleared=wp_unschedule_hook(self::HOOK,true);if (is_wp_error($cleared) || $cleared===false) { throw new \RuntimeException('Jadwal lama gagal dibersihkan.'); }self::arm($policy,$state);delete_option('fwf_backup_schedule_error');return true;
        } catch (\Throwable $e) {
            if ($changed) {
                foreach (['fwf_backup_schedule'=>$old,'fwf_backup_schedule_state'=>$oldState,'fwf_backup_schedule_paused'=>$oldPause] as $key=>$value) { if ($value===false) { delete_option($key); }else { update_option($key,$value,false); } }
                wp_unschedule_hook(self::HOOK);try { if (is_array($old) && is_array($oldState)) { self::arm($old,$oldState); } }catch (\Throwable $ignored) {}
            }
            return new \WP_Error('FWF_BACKUP_SCHEDULE',$e->getMessage());
        } finally { Lock::release('backup_schedule',$lease); }
    }
    public static function register(): void {
        add_action(self::HOOK,[self::class,'tick'],10,2);add_action('fwf_backup_job_terminal',[self::class,'terminal']);self::reconcile();
    }
    public static function reconcile(): void {
        $policy=self::policy();if (!$policy['enabled'] || get_option('fwf_backup_schedule_paused',false)) { return; }
        $lease=Lock::acquire('backup_schedule');if (is_wp_error($lease)) { return; }
        try {
            $state=get_option('fwf_backup_schedule_state',[]);
            if (($state['generation']??null)!==$policy['generation'] || !is_int($state['next']??null) || $state['next']<1 || !empty($state['blocked'])) { return; }
            self::preservePending($state);self::arm($policy,$state);delete_option('fwf_backup_schedule_error');
        } catch (\Throwable $e) { update_option('fwf_backup_schedule_error',$e->getMessage(),false); }
        finally { Lock::release('backup_schedule',$lease); }
    }
    private static function record(array $run): void {
        $runs=get_option('fwf_backup_schedule_runs',[]);if (!is_array($runs)) { $runs=[]; }$runs[$run['key']]=$run;
        // Never discard ambiguous or pending execution records.
        $terminal=array_keys(array_filter($runs,static fn($r)=>!in_array($r['status'],['starting','queued'],true)));
        while (count($terminal)>20) { unset($runs[array_shift($terminal)]); }self::put('fwf_backup_schedule_runs',$runs);
    }
    public static function tick(mixed $generation,mixed $due): void {
        $lease=Lock::acquire('backup_schedule');if (is_wp_error($lease)) { return; }$actor=get_current_user_id();$run=null;
        try {
            $policy=self::policy();$state=get_option('fwf_backup_schedule_state',[]);
            if (!$policy['enabled'] || get_option('fwf_backup_schedule_paused',false) || !empty($state['blocked']) || $generation!==$policy['generation'] || !is_int($due) || $due>time() || ($state['next']??null)!==$due || ($state['claimed']??0)>=$due) { return; }
            self::preservePending($state);
            $run=['key'=>$generation.':'.$due,'due'=>$due,'at'=>time(),'job'=>'','status'=>'starting','error'=>'Proses mulai; hasil belum diketahui.'];
            // Claim before queueing. A crash in this gap is reported as unknown, never blindly replayed.
            $state['claimed']=$due;$state['next']=self::next($policy,max(time(),$due));$state['pending']=$run;self::put('fwf_backup_schedule_state',$state);self::record($run);self::arm($policy,$state);
            wp_set_current_user($policy['owner']);
            if (!current_user_can('fwf_manage_system') || is_multisite()) {
                $state['blocked']='Izin pemilik jadwal dicabut. Admin perlu menyimpan ulang jadwal.';self::put('fwf_backup_schedule_state',$state);wp_unschedule_hook(self::HOOK);throw new \RuntimeException($state['blocked']);
            }
            $jobs=Manager::jobs();if (is_wp_error($jobs)) { throw new \RuntimeException($jobs->get_error_message()); }
            foreach ($jobs as $job) { if (!in_array($job['phase'],['done','failed','cancelled'],true)) { $run['status']='skipped';$run['error']='Dilewati: tugas backup lain masih aktif.';self::record($run);return; } }
            $id=Manager::queue($policy['components'],true,'Backup berkala — '.$policy['frequency'].' — '.wp_date('d M Y H:i',$due,new \DateTimeZone($policy['timezone'])),$policy['media_selection']??null);
            if (is_wp_error($id)) { $run['status']=$id->get_error_code()==='FWF_LOCK'?'skipped':'failed';$run['error']=$id->get_error_message(); }
            else { $run['job']=$id;$run['status']='queued';$run['error']=''; }self::record($run);
        } catch (\Throwable $e) { if ($run) { $run['status']='failed';$run['error']=$e->getMessage();try { self::record($run); }catch (\Throwable $ignored) {} }update_option('fwf_backup_schedule_error',$e->getMessage(),false); }
        finally { wp_set_current_user($actor);Lock::release('backup_schedule',$lease); }
    }
    public static function terminal(array $job): void {
        $runs=get_option('fwf_backup_schedule_runs',[]);if (!is_array($runs) || !array_filter($runs,static fn($run)=>($run['job']??null)===$job['id'])) { return; }
        $lease=Lock::acquire('backup_schedule');if (is_wp_error($lease)) { return; }
        try { foreach (get_option('fwf_backup_schedule_runs',[]) as $run) { if ($run['job']===$job['id']) { $run['status']=$job['phase'];$run['error']=$job['error'];$run['finished']=time();self::record($run);break; } } }
        catch (\Throwable $e) { update_option('fwf_backup_schedule_error',$e->getMessage(),false); }
        finally { Lock::release('backup_schedule',$lease); }
    }
    public static function runs(): array {
        $runs=get_option('fwf_backup_schedule_runs',[]);if (!is_array($runs)) { $runs=[]; }$pending=get_option('fwf_backup_schedule_state',[])['pending']??null;if (is_array($pending) && !isset($runs[$pending['key']])) { $runs[$pending['key']]=$pending; }
        // Read-only reconciliation also shows a terminal job if notification was lease-blocked.
        $jobs=Manager::jobs();if (!is_wp_error($jobs)) { foreach ($runs as &$run) { if (isset($jobs[$run['job']]) && in_array($jobs[$run['job']]['phase'],['done','failed','cancelled'],true)) { $run['status']=$jobs[$run['job']]['phase'];$run['error']=$jobs[$run['job']]['error']; } }unset($run); }
        return array_reverse(array_values($runs));
    }
    public static function deactivate(): void { update_option('fwf_backup_schedule_paused',true,false);wp_unschedule_hook(self::HOOK); }
    public static function activate(): void { delete_option('fwf_backup_schedule_paused');self::reconcile(); }
}
