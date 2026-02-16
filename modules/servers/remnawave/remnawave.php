<?php

/**
 * WHMCS Server Provisioning Module: Remnawave (V2Ray/Xray)
 *
 * Provisions VPN users via Remnawave HTTP API (Bearer token).
 *
 * @see https://docs.rw/api/
 */

use WHMCS\Module\Server\Remnawave\RemnawaveApi;
use WHMCS\Module\Server\Remnawave\ServerConfig;
use WHMCS\Module\Server\Remnawave\ServiceData;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/lib/RemnawaveApi.php';
require_once __DIR__ . '/lib/ServiceData.php';
require_once __DIR__ . '/lib/ServerConfig.php';

function remnawave_MetaData(): array
{
    return [
        'DisplayName' => 'Remnawave (V2Ray/Xray)',
        'APIVersion' => '1.2',
        'RequireServer' => true,
    ];
}

/**
 * Product config. Server: Hostname = Panel URL, Password = API Token; Access Hash = timeout (seconds).
 */
function remnawave_ConfigOptions(): array
{
    $serverOptions = ["0" => "-- Select server to load squads --"];
    try {
        $servers = \Illuminate\Database\Capsule\Manager::table("tblservers")
            ->where("type", "remnawave")
            ->orderBy("hostname")
            ->get();
        foreach ($servers as $s) {
            $label = $s->hostname ?: ("Server #" . $s->id);
            $serverOptions[(string) $s->id] = $label;
        }
    } catch (Exception $e) {
        // ignore
    }

    return [
        "Server for Squad List" => [
            "FriendlyName" => "Server for Squad List",
            "Type" => "dropdown",
            "Options" => $serverOptions,
            "Description" => "Select a Remnawave server to load internal squads. Then choose a squad.",
        ],
        "Internal Squad ID" => [
            "FriendlyName" => "Internal Squad ID",
            "Type" => "text",
            "Size" => "40",
            "Description" => "Remnawave Internal Squad UUID. Use 'Select Squad' in admin product config or paste UUID.",
        ],
        "Traffic (GB)" => [
            "FriendlyName" => "Traffic cap (GB)",
            "Type" => "text",
            "Size" => "10",
            "Default" => "50",
        ],
        "Expiry (days)" => [
            "FriendlyName" => "Expiry (days)",
            "Type" => "text",
            "Size" => "10",
            "Default" => "30",
            "Description" => "Validity in days. 0 = no expiry.",
        ],
        "IP limit" => [
            "FriendlyName" => "IP limit",
            "Type" => "text",
            "Size" => "5",
            "Default" => "2",
        ],
        "Client Comment format" => [
            "FriendlyName" => "Client Comment format",
            "Type" => "text",
            "Size" => "80",
            "Default" => "",
            "Description" => "Placeholders: {service_id}, {client_id}, {email}. Stored as user note/comment.",
        ],
        "Start expiry after first use" => [
            "FriendlyName" => "Start expiry after first use",
            "Type" => "yesno",
            "Description" => "If Yes, user is created with no expiry (0). Set duration later when they first use.",
        ],
    ];
}

function remnawave_getServerParams(array $params): array
{
    $serverId = (int) ($params['serverid'] ?? 0);
    $port = null;

    if ($serverId > 0) {
        $port = ServerConfig::getPort($serverId);
    }

    if (($port === null || $port <= 0) && ($params['serverhostname'] ?? '') !== '') {
        $hostname = $params['serverhostname'];
        $norm = preg_replace('#^https?://#', '', $hostname);
        $norm = preg_replace('#:\d+$#', '', $norm);
        $server = \Illuminate\Database\Capsule\Manager::table('tblservers')
            ->where('type', 'remnawave')
            ->where(function ($q) use ($hostname, $norm) {
                $q->where('hostname', $hostname)
                    ->orWhere('hostname', 'like', '%' . $norm . '%');
            })
            ->first();
        if ($server) {
            $serverId = (int) $server->id;
            $port = ServerConfig::getPort($serverId);
        }
    }

    if ($port === null || $port <= 0) {
        $hash = (string) ($params['serveraccesshash'] ?? '');
        if (preg_match('/^(\d+),(\d+)$/', $hash, $m)) {
            $port = (int) $m[2];
        }
    }

    $hostname = trim($params['serverhostname'] ?? '');
    if ($hostname === '') {
        return $params;
    }
    if (strpos($hostname, 'http') !== 0) {
        $hostname = 'https://' . $hostname;
    }
    $u = parse_url($hostname);
    $scheme = $u['scheme'] ?? 'https';
    $host = $u['host'] ?? $u['path'] ?? '';
    if ($host === '') {
        return $params;
    }

    $basePath = $serverId > 0 ? ServerConfig::getBasePath($serverId) : null;
    if ($basePath === null && ($params['serverhostname'] ?? '') !== '') {
        $hn = $params['serverhostname'];
        $norm = preg_replace('#^https?://#', '', $hn);
        $norm = preg_replace('#:\d+$#', '', $norm);
        $server = \Illuminate\Database\Capsule\Manager::table('tblservers')
            ->where('type', 'remnawave')
            ->where(function ($q) use ($hn, $norm) {
                $q->where('hostname', $hn)->orWhere('hostname', 'like', '%' . $norm . '%');
            })
            ->first();
        if ($server) {
            $basePath = ServerConfig::getBasePath((int) $server->id);
        }
    }

    $base = ($port !== null && $port > 0)
        ? ($scheme . '://' . $host . ':' . $port)
        : ($scheme . '://' . $host);
    $params['serverhostname'] = $basePath !== null && $basePath !== ''
        ? rtrim($base, '/') . '/' . ltrim($basePath, '/')
        : $base;

    return $params;
}

function remnawave_enrich_params_from_service(array $params): array
{
    $serviceId = (int) ($params['serviceid'] ?? 0);
    if ($serviceId <= 0) {
        return $params;
    }
    try {
        $hosting = \Illuminate\Database\Capsule\Manager::table('tblhosting')
            ->where('id', $serviceId)
            ->first();
        if ($hosting === null) {
            return $params;
        }
        if (empty($params['client']['email']) && !empty($hosting->userid)) {
            $client = \Illuminate\Database\Capsule\Manager::table('tblclients')
                ->where('id', $hosting->userid)
                ->first();
            if ($client !== null && !empty($client->email)) {
                $params['client'] = $params['client'] ?? [];
                $params['client']['email'] = $client->email;
            }
        }
        if (empty($params['packageid']) && empty($params['pid']) && !empty($hosting->packageid)) {
            $params['packageid'] = $hosting->packageid;
        }
        if (empty($params['clientid']) && !empty($hosting->userid)) {
            $params['clientid'] = $hosting->userid;
        }
    } catch (Exception $e) {
        // ignore
    }

    return $params;
}

function remnawave_getProductConfig(array $params): array
{
    $serviceId = (int) ($params['serviceid'] ?? 0);
    $packageId = (int) ($params['packageid'] ?? $params['pid'] ?? 0);

    if ($packageId <= 0 && $serviceId > 0) {
        try {
            $h = \Illuminate\Database\Capsule\Manager::table('tblhosting')->where('id', $serviceId)->first();
            if ($h !== null && !empty($h->packageid)) {
                $packageId = (int) $h->packageid;
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    $trafficGb = (int) ($params['configoption3'] ?? 0);
    $expiryDays = (int) ($params['configoption4'] ?? 0);
    if ($trafficGb <= 0 && !empty($params['configoptions']) && is_array($params['configoptions'])) {
        $t = $params['configoptions']['Traffic cap (GB)'] ?? $params['configoptions']['Traffic (GB)'] ?? null;
        if ($t !== null && (string) $t !== '') {
            $trafficGb = (int) $t;
        }
    }

    if ($packageId > 0) {
        try {
            $product = \Illuminate\Database\Capsule\Manager::table('tblproducts')
                ->where('id', $packageId)
                ->first();
            if ($product !== null) {
                $opt3 = $product->configoption3 ?? $product->moduleconfigoption3 ?? null;
                if ($trafficGb <= 0 && $opt3 !== null && (string) $opt3 !== '') {
                    $v = (int) $opt3;
                    if ($v > 0) {
                        $trafficGb = $v;
                    }
                }
                $opt4 = $product->configoption4 ?? $product->moduleconfigoption4 ?? null;
                if ($expiryDays <= 0 && $opt4 !== null && (string) $opt4 !== '') {
                    $v = (int) $opt4;
                    if ($v > 0) {
                        $expiryDays = $v;
                    }
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    if ($trafficGb <= 0) {
        $trafficGb = 50;
    }
    if ($expiryDays <= 0) {
        $expiryDays = 30;
    }

    return [
        'server_for_list' => (int) ($params['configoption1'] ?? 0),
        'squad_id' => trim((string) ($params['configoption2'] ?? '')),
        'traffic_gb' => $trafficGb,
        'expiry_days' => $expiryDays,
        'ip_limit' => (int) ($params['configoption5'] ?? 2),
        'comment_format' => (string) ($params['configoption6'] ?? ''),
        'start_expiry_after_first_use' => (bool) ($params['configoption7'] ?? $params['configoption8'] ?? false),
    ];
}

function remnawave_client_email(array $params): string
{
    $email = (string) ($params['client']['email'] ?? '');

    return $email !== '' ? $email : 'whmcs_' . ($params['serviceid'] ?? '0') . '@client.local';
}

function remnawave_client_comment(array $params, array $productConfig): string
{
    $email = (string) ($params['client']['email'] ?? '');
    $format = $productConfig['comment_format'];
    $format = str_replace('{service_id}', (string) ($params['serviceid'] ?? ''), $format);
    $format = str_replace('{client_id}', (string) ($params['clientid'] ?? ''), $format);
    $format = str_replace('${email}', $email, $format);
    $format = str_replace('{email}', $email, $format);

    return trim($format);
}

function remnawave_CreateAccount(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $params = remnawave_enrich_params_from_service($params);
        $productConfig = remnawave_getProductConfig($params);
        $squadId = $productConfig['squad_id'];
        if ($squadId === '') {
            return "Product Internal Squad ID is not set.";
        }

        $api = new RemnawaveApi($params);
        $api->login();

        $email = remnawave_client_email($params);
        $comment = remnawave_client_comment($params, $productConfig);
        $totalGb = $productConfig['traffic_gb'];
        $expiryDays = $productConfig['expiry_days'];
        $ipLimit = $productConfig['ip_limit'];
        $noExpiry = $productConfig['start_expiry_after_first_use'];

        $dataLimitBytes = $totalGb > 0 ? (int) ($totalGb * 1024 * 1024 * 1024) : 0;
        $expireAt = (!$noExpiry && $expiryDays > 0)
            ? (time() + $expiryDays * 86400) * 1000
            : 0;

        $payload = [
            'email' => $email,
            'internalSquadIds' => [$squadId],
            'dataLimitBytes' => $dataLimitBytes,
            'expireAt' => $expireAt,
            'enabled' => true,
        ];
        if ($comment !== '') {
            $payload['name'] = $comment;
            $payload['comment'] = $comment;
        }
        if ($ipLimit > 0) {
            $payload['limitIps'] = $ipLimit;
        }

        $user = $api->createUser($payload);
        $userUuid = $user['uuid'] ?? $user['id'] ?? $user['userId'] ?? null;
        if ($userUuid === null) {
            return "API did not return user UUID.";
        }
        $userUuid = (string) $userUuid;

        ServiceData::save((int) $params['serviceid'], $userUuid, $email, $squadId);

        $displayName = $comment !== '' ? $comment : $email;
        \Illuminate\Database\Capsule\Manager::table('tblhosting')
            ->where('id', (int) $params['serviceid'])
            ->update([
                'username' => $email,
                'domain' => $userUuid,
            ]);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_SuspendAccount(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return "Service data not found. Create account first.";
        }

        $api = new RemnawaveApi($params);
        $api->login();
        $api->updateUser($data['user_uuid'], ['enabled' => false]);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_UnsuspendAccount(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return "Service data not found.";
        }

        $api = new RemnawaveApi($params);
        $api->login();
        $api->updateUser($data['user_uuid'], ['enabled' => true]);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_TerminateAccount(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $serviceId = (int) $params['serviceid'];
        $data = ServiceData::get($serviceId);
        if (!$data) {
            ServiceData::delete($serviceId);

            return 'success';
        }

        $api = new RemnawaveApi($params);
        $api->login();
        $api->deleteUser($data['user_uuid']);
        ServiceData::delete($serviceId);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_ChangePackage(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $productConfig = remnawave_getProductConfig($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return "Service data not found.";
        }

        $api = new RemnawaveApi($params);
        $api->login();

        $dataLimitBytes = $productConfig['traffic_gb'] > 0
            ? (int) ($productConfig['traffic_gb'] * 1024 * 1024 * 1024)
            : 0;
        $expireAt = $productConfig['expiry_days'] > 0
            ? (time() + $productConfig['expiry_days'] * 86400) * 1000
            : 0;

        $payload = [
            'dataLimitBytes' => $dataLimitBytes,
            'expireAt' => $expireAt,
            'limitIps' => $productConfig['ip_limit'],
        ];
        $api->updateUser($data['user_uuid'], $payload);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_TestConnection(array $params): array
{
    try {
        $params = remnawave_getServerParams($params);
        $api = new RemnawaveApi($params);
        $api->login();
        $api->inboundsList();

        return [
            'success' => true,
            'error' => '',
        ];
    } catch (Exception $e) {
        logModuleCall('remnawave', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

function remnawave_AdminCustomButtonArray(): array
{
    return [
        'Sync status' => 'SyncStatus',
        'Reset Traffic' => 'ResetTraffic',
        'Reset IP' => 'ClearIps',
    ];
}

function remnawave_SyncStatus(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return "No service data.";
        }
        $api = new RemnawaveApi($params);
        $api->login();
        $api->getClientTraffics($data['user_uuid']);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', 'SyncStatus', $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_ResetTraffic(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return "No service data.";
        }
        $api = new RemnawaveApi($params);
        $api->login();
        $api->resetClientTraffic($data['user_uuid']);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', 'ResetTraffic', $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_ClearIps(array $params): string
{
    try {
        $params = remnawave_getServerParams($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return "No service data.";
        }
        $api = new RemnawaveApi($params);
        $api->login();
        $api->clearClientIps($data['user_uuid']);

        return 'success';
    } catch (Exception $e) {
        logModuleCall('remnawave', 'ClearIps', $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }
}

function remnawave_build_subscription_urls(array $params, array $data): array
{
    $userUuid = $data['user_uuid'];
    $serverId = (int) ($params['serverid'] ?? 0);
    $subDomain = $serverId > 0 ? ServerConfig::getSubDomain($serverId) : null;

    if ($subDomain !== null && $subDomain !== '') {
        $subPort = $serverId > 0 ? ServerConfig::getSubPort($serverId) : null;
        $subUriPath = $serverId > 0 ? ServerConfig::getSubUriPath($serverId) : null;
        $path = $subUriPath !== null && $subUriPath !== '' ? rtrim($subUriPath, '/') . '/' : '/sub/';
        $base = 'https://' . $subDomain . ':' . ($subPort > 0 ? $subPort : 443);
        $subscriptionUrl = $base . $path . $userUuid;
        $subscriptionJsonUrl = $base . preg_replace('#/sub/?$#', '/json/', $path) . $userUuid;
    } else {
        $panelUrl = rtrim($params['serverhostname'] ?? '', '/');
        if (strpos($panelUrl, 'http') !== 0) {
            $panelUrl = 'https://' . $panelUrl;
        }
        $base = preg_replace('#/api/?$#', '', $panelUrl);
        $subscriptionUrl = $base . '/sub/' . $userUuid;
        $subscriptionJsonUrl = $base . '/json/' . $userUuid;
    }

    return [
        'subscription_url' => $subscriptionUrl,
        'subscription_json_url' => $subscriptionJsonUrl,
    ];
}

/**
 * @return array<string, mixed>|null
 */
function remnawave_get_subscription_info(array $params, array $data): ?array
{
    try {
        $api = new RemnawaveApi($params);
        $api->login();

        $traffic = $api->getClientTraffics($data['user_uuid']);
        $lastOnline = $api->lastOnline();
        $onlines = $api->onlines();

        $up = (int) ($traffic['up'] ?? $traffic['upload'] ?? 0);
        $down = (int) ($traffic['down'] ?? $traffic['download'] ?? 0);
        $totalBytes = (int) ($traffic['total'] ?? $traffic['dataLimitBytes'] ?? 0);
        $used = $up + $down;
        $usedGb = round($used / (1024 * 1024 * 1024), 2);
        $totalGb = $totalBytes > 0 ? round($totalBytes / (1024 * 1024 * 1024), 2) : 0;
        $remainedGb = $totalBytes > 0 ? max(0, round(($totalBytes - $used) / (1024 * 1024 * 1024), 2)) : null;

        $status = 'Active';
        if ($totalBytes > 0) {
            $status = $used >= $totalBytes ? 'Inactive' : 'Active';
        } else {
            $status = 'Unlimited';
        }

        $email = $data['client_email'];
        $lastTs = $lastOnline[$email] ?? $lastOnline[$data['user_uuid']] ?? 0;
        $lastOnlineStr = $lastTs ? date('Y-m-d H:i', (int) ($lastTs / 1000)) : '-';
        $onlineNow = false;
        foreach ($onlines as $o) {
            $oUuid = (string) ($o['uuid'] ?? $o['id'] ?? '');
            $oEmail = (string) ($o['email'] ?? '');
            if ($oUuid === $data['user_uuid'] || $oEmail === $email) {
                $onlineNow = true;
                break;
            }
        }

        $urls = remnawave_build_subscription_urls($params, $data);
        $user = $api->getUser($data['user_uuid']);
        $subscriptionUrl = $user['subscriptionUrl'] ?? $user['subscription_url'] ?? $urls['subscription_url'];
        $subscriptionJsonUrl = $user['subscriptionJsonUrl'] ?? $urls['subscription_json_url'];

        return [
            'sub_id' => $data['user_uuid'],
            'client_email' => $email,
            'status' => $status,
            'down_gb' => round($down / (1024 * 1024 * 1024), 2),
            'up_gb' => round($up / (1024 * 1024 * 1024), 2),
            'traffic_used_gb' => $usedGb,
            'traffic_total_gb' => $totalGb,
            'traffic_percent' => $totalBytes > 0 ? min(100, round($used / $totalBytes * 100, 1)) : 0,
            'remained_gb' => $remainedGb,
            'last_online' => $lastOnlineStr,
            'online_now' => $onlineNow,
            'expiry' => '-',
            'subscription_url' => $subscriptionUrl,
            'subscription_json_url' => $subscriptionJsonUrl,
            'service_uris' => [],
        ];
    } catch (Exception $e) {
        return null;
    }
}

function remnawave_AdminServicesTabFields(array $params): array
{
    $out = [];
    try {
        $params = remnawave_getServerParams($params);
        $data = ServiceData::get((int) $params['serviceid']);
        if (!$data) {
            return ['Remnawave Status' => 'Not provisioned yet.', 'Traffic' => '-', 'Last online' => '-'];
        }

        $info = remnawave_get_subscription_info($params, $data);
        if ($info === null) {
            $out['Remnawave Status'] = 'Could not load subscription data.';

            return $out;
        }

        $out['Client email'] = htmlspecialchars($info['client_email']);
        $trafficStr = $info['traffic_used_gb'] . ' GB / '
            . ($info['traffic_total_gb'] > 0 ? $info['traffic_total_gb'] . ' GB' : '∞');
        $out['Traffic'] = $trafficStr;
        $out['Last online'] = $info['last_online'];
        $out['Online now'] = $info['online_now'] ? 'Yes' : 'No';
        $out['Status'] = $info['status'];

        $subUrl = $info['subscription_url'];
        $subJsonUrl = $info['subscription_json_url'];
        $subUrlEsc = htmlspecialchars($subUrl, ENT_QUOTES, 'UTF-8');
        $qrSub = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($subUrl);
        $qrJson = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($subJsonUrl);

        $html = '<div class="panel panel-default" style="margin-top:8px"><div class="panel-body">';
        $html .= '<p><strong>User UUID:</strong> <code>' . htmlspecialchars($data['user_uuid']) . '</code></p>';
        $html .= '<p><strong>Subscription URL:</strong><br><input type="text" class="form-control input-sm" value="' . $subUrlEsc . '" readonly style="max-width:100%"></p>';
        $html .= '<p><img src="' . htmlspecialchars($qrSub) . '" alt="Sub QR" width="120" height="120"></p>';
        $html .= '<p><strong>Subscription JSON URL:</strong><br><input type="text" class="form-control input-sm" value="' . htmlspecialchars($subJsonUrl) . '" readonly style="max-width:100%"></p>';
        $html .= '<p><img src="' . htmlspecialchars($qrJson) . '" alt="Sub JSON QR" width="120" height="120"></p>';
        $html .= '</div></div>';
        $out['Subscription &amp; links'] = $html;
    } catch (Exception $e) {
        $out['Remnawave Error'] = $e->getMessage();
    }

    return $out;
}

function remnawave_ClientArea(array $params): array
{
    $params = remnawave_getServerParams($params);
    $data = ServiceData::get((int) $params['serviceid']);
    $vars = [
        'client_email' => '',
        'traffic_used_gb' => 0,
        'traffic_total_gb' => 0,
        'traffic_percent' => 0,
        'last_online' => '',
        'online_now' => false,
        'subscription_url' => '',
        'subscription_json_url' => '',
        'service_uris' => [],
        'config_links' => [],
        'sub_id' => '',
        'status' => '',
        'remained_gb' => null,
        'expiry' => '-',
        'error' => '',
    ];

    if (!$data) {
        $vars['error'] = 'Service not provisioned yet.';

        return [
            'tabOverviewReplacementTemplate' => 'clientarea.tpl',
            'templateVariables' => $vars,
        ];
    }

    $info = remnawave_get_subscription_info($params, $data);
    if ($info !== null) {
        $vars['client_email'] = $info['client_email'];
        $vars['traffic_used_gb'] = $info['traffic_used_gb'];
        $vars['traffic_total_gb'] = $info['traffic_total_gb'];
        $vars['traffic_percent'] = $info['traffic_percent'];
        $vars['last_online'] = $info['last_online'];
        $vars['online_now'] = $info['online_now'];
        $vars['subscription_url'] = $info['subscription_url'];
        $vars['subscription_json_url'] = $info['subscription_json_url'];
        $vars['service_uris'] = $info['service_uris'];
        $vars['config_links'] = array_merge([$info['subscription_url']], $info['service_uris']);
        $vars['sub_id'] = $info['sub_id'];
        $vars['status'] = $info['status'];
        $vars['remained_gb'] = $info['remained_gb'];
        $vars['expiry'] = $info['expiry'];
    } else {
        $vars['error'] = 'Could not load subscription data.';
    }

    return [
        'tabOverviewReplacementTemplate' => 'clientarea.tpl',
        'templateVariables' => $vars,
    ];
}
