<?php

/**
 * Add "Panel Port" and "Subscription URL" section to Remnawave server config page.
 * Copy includes/hooks/remnawave_server_port.php to WHMCS includes/hooks/ if the section does not appear.
 */

use WHMCS\Module\Server\Remnawave\ServerConfig;

if (!defined("WHMCS")) {
    exit;
}

require_once __DIR__ . '/lib/ServerConfig.php';

add_hook('AdminAreaFooterOutput', 1, function (array $params) {
    $serverId = (int) ($params['id'] ?? $_GET['id'] ?? 0);
    $port = '';
    $basePath = '';
    $subDomain = '';
    $subPort = '';
    $subUriPath = '';
    if ($serverId > 0) {
        $port = (string) (ServerConfig::getPort($serverId) ?? '');
        $basePath = (string) (ServerConfig::getBasePath($serverId) ?? '');
        $subDomain = (string) (ServerConfig::getSubDomain($serverId) ?? '');
        $subPort = (string) (ServerConfig::getSubPort($serverId) ?? '');
        $subUriPath = (string) (ServerConfig::getSubUriPath($serverId) ?? '');
    }
    $portEsc = htmlspecialchars($port, ENT_QUOTES, 'UTF-8');
    $basePathEsc = htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8');
    $subDomainEsc = htmlspecialchars($subDomain, ENT_QUOTES, 'UTF-8');
    $subPortEsc = htmlspecialchars($subPort, ENT_QUOTES, 'UTF-8');
    $subUriPathEsc = htmlspecialchars($subUriPath, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<script>
(function() {
    var href = typeof window.location !== 'undefined' ? (window.location.href || '') : '';
    if (href.indexOf('configservers') === -1) return;

    function run() {
        var form = document.querySelector('form[action*="configservers"]') || document.querySelector('form[action*="servers"]') || document.querySelector('#frmServerConfig') || document.querySelector('.admin-content form') || document.querySelector('form');
        if (!form) return;

        var typeSelect = form.querySelector('select[name="type"]') || form.querySelector('#inputType') || form.querySelector('select[id*="Type"]');
        if (!typeSelect) return;
        var isRemnawave = (typeSelect.value === 'remnawave');
        if (!isRemnawave) {
            var section = document.getElementById('remnawave_panel_port_section');
            if (section) section.remove();
            return;
        }

        if (document.getElementById('remnawave_panel_port_section')) return;

        var allRows = form.querySelectorAll('tr');
        var insertAfter = null;
        for (var i = 0; i < allRows.length; i++) {
            var text = (allRows[i].innerText || allRows[i].textContent || '').toLowerCase();
            if (text.indexOf('access hash') !== -1) {
                insertAfter = allRows[i];
                break;
            }
        }

        var portVal = '{$portEsc}';
        var pathVal = '{$basePathEsc}';
        var subDomainVal = '{$subDomainEsc}';
        var subPortVal = '{$subPortEsc}';
        var subPathVal = '{$subUriPathEsc}';
        var section = document.createElement('tr');
        section.id = 'remnawave_panel_port_section';
        section.innerHTML = '<td colspan="2" class="fieldarea" style="padding:0;vertical-align:top"><div class="panel panel-default" style="margin:15px 0"><div class="panel-heading"><h4 class="panel-title">Remnawave — Panel URL</h4></div><div class="panel-body"><p class="text-muted">Panel Port and URI Path. Password = API Token (Bearer).</p><div class="form-group"><label for="remnawave_port_input">Panel Port</label><input type="number" name="remnawave_port" id="remnawave_port_input" class="form-control" value="' + (portVal || '') + '" placeholder="443" min="1" max="65535" style="max-width:120px" /></div><div class="form-group"><label for="remnawave_base_path_input">URI Path</label><input type="text" name="remnawave_base_path" id="remnawave_base_path_input" class="form-control" value="' + (pathVal || '') + '" placeholder="/your-path" style="max-width:320px" /></div></div></div><div class="panel panel-default" style="margin:15px 0"><div class="panel-heading"><h4 class="panel-title">Subscription URL (for client)</h4></div><div class="panel-body"><p class="text-muted">Used to build the subscription link. Result: <code>https://domain:port/path/</code> + user UUID.</p><div class="form-group"><label for="remnawave_sub_domain_input">Subscription domain</label><input type="text" name="remnawave_sub_domain" id="remnawave_sub_domain_input" class="form-control" value="' + (subDomainVal || '') + '" placeholder="panel.domain.com" style="max-width:320px" /></div><div class="form-group"><label for="remnawave_sub_port_input">Subscription port</label><input type="number" name="remnawave_sub_port" id="remnawave_sub_port_input" class="form-control" value="' + (subPortVal || '') + '" placeholder="443" min="1" max="65535" style="max-width:120px" /></div><div class="form-group"><label for="remnawave_sub_uri_path_input">Subscription URI path</label><input type="text" name="remnawave_sub_uri_path" id="remnawave_sub_uri_path_input" class="form-control" value="' + (subPathVal || '') + '" placeholder="/sub/" style="max-width:320px" /></div></div></div></td>';

        if (insertAfter && insertAfter.nextSibling) {
            insertAfter.parentNode.insertBefore(section, insertAfter.nextSibling);
        } else if (insertAfter) {
            insertAfter.parentNode.appendChild(section);
        } else {
            var tbody = form.querySelector('tbody') || form.querySelector('table tbody');
            if (tbody) tbody.appendChild(section);
        }
    }

    function onTypeChange() {
        var form = document.querySelector('form[action*="configservers"]') || document.querySelector('form[action*="servers"]') || document.querySelector('#frmServerConfig') || document.querySelector('.admin-content form') || document.querySelector('form');
        if (!form) return;
        var typeSelect = form.querySelector('select[name="type"]') || form.querySelector('#inputType');
        if (!typeSelect) return;
        var section = document.getElementById('remnawave_panel_port_section');
        if (typeSelect.value === 'remnawave') {
            if (!section) run();
        } else if (section) {
            section.remove();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() { run(); setTimeout(run, 400); setTimeout(run, 1200); });
    } else {
        run();
        setTimeout(run, 400);
        setTimeout(run, 1200);
    }

    document.addEventListener('change', function(e) {
        if (e.target && (e.target.name === 'type' || e.target.id === 'inputType')) onTypeChange();
    });
})();
</script>
HTML;
});

add_hook('ServerEdit', 1, function (array $params) {
    $serverId = (int) ($params['serverid'] ?? 0);
    if ($serverId <= 0) {
        return;
    }
    $port = isset($_POST['remnawave_port']) && $_POST['remnawave_port'] !== '' ? (int) $_POST['remnawave_port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535)) {
        $port = null;
    }
    $basePath = isset($_POST['remnawave_base_path']) ? trim((string) $_POST['remnawave_base_path']) : null;
    if ($basePath === '') {
        $basePath = null;
    }
    ServerConfig::setPortAndBasePath($serverId, $port, $basePath);
    $subDomain = isset($_POST['remnawave_sub_domain']) ? trim((string) $_POST['remnawave_sub_domain']) : null;
    if ($subDomain === '') {
        $subDomain = null;
    }
    $subPort = isset($_POST['remnawave_sub_port']) && $_POST['remnawave_sub_port'] !== '' ? (int) $_POST['remnawave_sub_port'] : null;
    if ($subPort !== null && ($subPort < 1 || $subPort > 65535)) {
        $subPort = null;
    }
    $subUriPath = isset($_POST['remnawave_sub_uri_path']) ? trim((string) $_POST['remnawave_sub_uri_path']) : null;
    if ($subUriPath === '') {
        $subUriPath = null;
    }
    ServerConfig::setSubscriptionSettings($serverId, $subDomain, $subPort, $subUriPath);
});

add_hook('ServerAdd', 1, function (array $params) {
    $serverId = (int) ($params['serverid'] ?? 0);
    if ($serverId <= 0) {
        return;
    }
    $port = isset($_POST['remnawave_port']) && $_POST['remnawave_port'] !== '' ? (int) $_POST['remnawave_port'] : null;
    if ($port !== null && ($port < 1 || $port > 65535)) {
        $port = null;
    }
    $basePath = isset($_POST['remnawave_base_path']) ? trim((string) $_POST['remnawave_base_path']) : null;
    if ($basePath === '') {
        $basePath = null;
    }
    ServerConfig::setPortAndBasePath($serverId, $port, $basePath);
    $subDomain = isset($_POST['remnawave_sub_domain']) ? trim((string) $_POST['remnawave_sub_domain']) : null;
    if ($subDomain === '') {
        $subDomain = null;
    }
    $subPort = isset($_POST['remnawave_sub_port']) && $_POST['remnawave_sub_port'] !== '' ? (int) $_POST['remnawave_sub_port'] : null;
    if ($subPort !== null && ($subPort < 1 || $subPort > 65535)) {
        $subPort = null;
    }
    $subUriPath = isset($_POST['remnawave_sub_uri_path']) ? trim((string) $_POST['remnawave_sub_uri_path']) : null;
    if ($subUriPath === '') {
        $subUriPath = null;
    }
    ServerConfig::setSubscriptionSettings($serverId, $subDomain, $subPort, $subUriPath);
});
