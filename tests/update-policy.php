<?php
// Standalone policy unit check; no WordPress/database or external provider acceptance.
class WP_Error { public function __construct(public string $code, public string $message) {} }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function wp_get_environment_type(): string { return $GLOBALS['fixture_environment']; }
require '/var/www/html/wp-content/plugins/falcon-wf/src/Updates/UpdateManager.php';
foreach (['local','staging','development','production'] as $environment) {
    $GLOBALS['fixture_environment']=$environment;
    $allowed=in_array($environment,['local','staging'],true);
    if (is_wp_error(FalconWF\Updates\UpdateManager::validateSelection('v0.1.0-alpha.3'))===$allowed || is_wp_error(FalconWF\Updates\UpdateManager::validateSelection(''))) { throw new RuntimeException('Release environment policy failed: '.$environment); }
}
echo "PASS: release policy unit check: prerelease local/staging only; stable in all environments\n";
