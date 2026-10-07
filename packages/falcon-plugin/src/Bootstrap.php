<?php
namespace FalconWF;
final class Bootstrap {
    private static ?self $instance = null;
    public readonly Content\Repository $content;
    public readonly Modules\Registry $modules;
    public readonly AI\AgentTools $tools;
    private function __construct(string $file) {
        Content\Definitions::init();
        $this->modules = new Modules\Registry();
        $this->content = new Content\Repository($this->modules);
        $this->tools = new AI\AgentTools($this->content);
        add_action('template_redirect', [Maintenance::class, 'render'], 0);
        add_action('init', [$this->modules, 'register']);
        add_action('rest_api_init', [new Api\Routes($this->content, $this->tools), 'register']);
        (new Admin\ContentEditor($this->content))->register();
        (new Admin\Dashboard($file, $this))->register();
        (new Admin\Builder($file))->register();
        (new Content\Listing())->register();
        add_action('fwf_daily_cleanup', [AI\RequestGuard::class, 'cleanup']);
        if (!wp_next_scheduled('fwf_daily_cleanup')) { wp_schedule_event(time()+DAY_IN_SECONDS, 'daily', 'fwf_daily_cleanup'); }
        do_action('fwf_ready', $this);
    }
    public static function boot(string $file): ?self {
        if (self::$instance) { return self::$instance; }
        $migration=Migrations\Runner::run();
        if (is_wp_error($migration)) {
            add_action('admin_notices',static function () use ($migration) { echo '<div class="notice notice-error"><p>'.esc_html($migration->get_error_message()).'</p></div>'; });
            return null;
        }
        return self::$instance=new self($file);
    }
    public static function instance(): ?self { return self::$instance; }
}
