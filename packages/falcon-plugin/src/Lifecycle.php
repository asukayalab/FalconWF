<?php
namespace FalconWF;
final class Lifecycle {
    public static function activate(): void {
        if (is_multisite()) { wp_die('Falcon WF 0.1 mendukung single-site.'); }
        add_role('fwf_agent', 'Falcon Agent', ['read'=>true, 'edit_posts'=>true]);
        $admin = get_role('administrator');
        if ($admin) {
            foreach (['fwf_manage_system', 'fwf_manage_modules', 'fwf_manage_connections', 'fwf_manage_ai', 'fwf_manage_updates', 'fwf_view_audit'] as $cap) { $admin->add_cap($cap); }
        }
        $result=Migrations\Runner::run();
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        Backup\Scheduler::activate();
    }
    public static function deactivate(): void { Backup\Scheduler::deactivate(); wp_clear_scheduled_hook('fwf_daily_cleanup');wp_unschedule_hook('fwf_backup_step'); /* Preserve data and active theme. */ }
}
