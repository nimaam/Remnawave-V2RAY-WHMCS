<?php

/**
 * Admin-only AJAX endpoint: returns internal squads list for a given Remnawave server.
 * Used by product edit page to populate the Internal Squad dropdown.
 *
 * GET/POST: serverid = (int) server ID
 * Response: JSON { "success": true, "squads": [ { "id": "uuid", "name": "...", "remark": "..." }, ... ] }
 */

define("ADMINAREA", true);

$docroot = realpath(dirname(__DIR__) . "/../../../..");
if (!$docroot || !is_file($docroot . "/init.php")) {
    $docroot = realpath(dirname(__DIR__) . "/../../..");
}
require_once $docroot . "/init.php";
require_once dirname(__DIR__) . "/lib/ServerConfig.php";
require_once dirname(__DIR__) . "/lib/RemnawaveApi.php";

use WHMCS\Module\Server\Remnawave\RemnawaveApi;
use WHMCS\Module\Server\Remnawave\ServerConfig;

header("Content-Type: application/json; charset=utf-8");

if (!isset($_SESSION["adminid"])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$serverId = (int) ($_REQUEST["serverid"] ?? 0);
if ($serverId <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid serverid"]);
    exit;
}

try {
    $server = \Illuminate\Database\Capsule\Manager::table("tblservers")->where("id", $serverId)->first();
    if (!$server || (string) $server->type !== "remnawave") {
        echo json_encode(["success" => false, "error" => "Server not found or not Remnawave"]);
        exit;
    }

    $hostname = $server->hostname;
    $port = ServerConfig::getPort($serverId);
    $basePath = ServerConfig::getBasePath($serverId);
    if (strpos($hostname, 'http') !== 0) {
        $hostname = 'https://' . $hostname;
    }
    $u = parse_url($hostname);
    $scheme = $u['scheme'] ?? 'https';
    $host = $u['host'] ?? $u['path'] ?? '';
    if ($host !== '') {
        $base = ($port !== null && $port > 0)
            ? ($scheme . '://' . $host . ':' . $port)
            : ($scheme . '://' . $host);
        $hostname = ($basePath !== null && $basePath !== '')
            ? rtrim($base, '/') . '/' . ltrim($basePath, '/')
            : $base;
    }

    $params = [
        "serverhostname" => $hostname,
        "serverusername" => $server->username,
        "serverpassword" => $server->password,
        "serversecure" => (bool) $server->secure,
        "serveraccesshash" => $server->accesshash ?? "30",
    ];

    $api = new RemnawaveApi($params);
    $api->login();
    $list = $api->inboundsList();

    $squads = [];
    foreach ($list as $s) {
        $id = $s['id'] ?? $s['uuid'] ?? '';
        $name = $s['name'] ?? $s['remark'] ?? $s['title'] ?? 'Squad #' . $id;
        $squads[] = [
            "id" => (string) $id,
            "name" => (string) $name,
            "remark" => (string) ($s['remark'] ?? $name),
        ];
    }

    echo json_encode(["success" => true, "squads" => $squads]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
