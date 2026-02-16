<?php

/**
 * WHMCS hook entrypoint. Copy this file to WHMCS includes/hooks/ so that
 * the Remnawave server config (Panel Port, Subscription URL) section appears
 * on Setup > Servers when editing a Remnawave server.
 */

if (!defined("WHMCS")) {
    exit;
}

$moduleHooksPath = dirname(__DIR__, 2) . '/modules/servers/remnawave/hooks.php';
if (is_file($moduleHooksPath)) {
    require_once $moduleHooksPath;
}
