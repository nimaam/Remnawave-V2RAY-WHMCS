<?php

namespace WHMCS\Module\Server\Remnawave;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Store/retrieve per-server options (port, base path, subscription URL) for Remnawave module.
 */
class ServerConfig
{
    private const TABLE = 'mod_remnawave_server_config';

    public static function ensureTable(): void
    {
        if (Capsule::schema()->hasTable(self::TABLE)) {
            if (!Capsule::schema()->hasColumn(self::TABLE, 'base_path')) {
                Capsule::schema()->table(self::TABLE, function ($table) {
                    $table->string('base_path', 255)->nullable()->after('port');
                });
            }
            if (!Capsule::schema()->hasColumn(self::TABLE, 'sub_domain')) {
                Capsule::schema()->table(self::TABLE, function ($table) {
                    $table->string('sub_domain', 255)->nullable()->after('base_path');
                    $table->unsignedSmallInteger('sub_port')->nullable()->after('sub_domain');
                    $table->string('sub_uri_path', 255)->nullable()->after('sub_port');
                });
            }

            return;
        }
        Capsule::schema()->create(self::TABLE, function ($table) {
            $table->unsignedInteger('server_id')->primary();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('base_path', 255)->nullable();
            $table->string('sub_domain', 255)->nullable();
            $table->unsignedSmallInteger('sub_port')->nullable();
            $table->string('sub_uri_path', 255)->nullable();
        });
    }

    public static function getPort(int $serverId): ?int
    {
        self::ensureTable();
        $val = Capsule::table(self::TABLE)->where('server_id', $serverId)->value('port');

        return $val !== null ? (int) $val : null;
    }

    public static function getBasePath(int $serverId): ?string
    {
        self::ensureTable();
        $val = Capsule::table(self::TABLE)->where('server_id', $serverId)->value('base_path');

        return $val !== null && $val !== '' ? trim((string) $val) : null;
    }

    public static function setPort(int $serverId, ?int $port): void
    {
        self::ensureTable();
        $existing = Capsule::table(self::TABLE)->where('server_id', $serverId)->first();
        $data = ['port' => $port];
        if ($existing) {
            $data['base_path'] = $existing->base_path ?? null;
        }
        Capsule::table(self::TABLE)->updateOrInsert(
            ['server_id' => $serverId],
            $data
        );
    }

    public static function setBasePath(int $serverId, ?string $basePath): void
    {
        self::ensureTable();
        $basePath = $basePath !== null && $basePath !== '' ? trim($basePath) : null;
        if ($basePath !== null) {
            $basePath = '/' . ltrim($basePath, '/');
        }
        $existing = Capsule::table(self::TABLE)->where('server_id', $serverId)->first();
        $data = ['base_path' => $basePath];
        if ($existing) {
            $data['port'] = $existing->port;
        }
        Capsule::table(self::TABLE)->updateOrInsert(
            ['server_id' => $serverId],
            $data
        );
    }

    public static function setPortAndBasePath(int $serverId, ?int $port, ?string $basePath): void
    {
        self::ensureTable();
        $basePath = $basePath !== null && $basePath !== '' ? trim($basePath) : null;
        if ($basePath !== null) {
            $basePath = '/' . ltrim($basePath, '/');
        }
        $existing = Capsule::table(self::TABLE)->where('server_id', $serverId)->first();
        $data = ['port' => $port, 'base_path' => $basePath];
        if ($existing) {
            $data['sub_domain'] = $existing->sub_domain ?? null;
            $data['sub_port'] = $existing->sub_port ?? null;
            $data['sub_uri_path'] = $existing->sub_uri_path ?? null;
        }
        Capsule::table(self::TABLE)->updateOrInsert(
            ['server_id' => $serverId],
            $data
        );
    }

    public static function getSubDomain(int $serverId): ?string
    {
        self::ensureTable();
        $val = Capsule::table(self::TABLE)->where('server_id', $serverId)->value('sub_domain');

        return $val !== null && $val !== '' ? trim((string) $val) : null;
    }

    public static function getSubPort(int $serverId): ?int
    {
        self::ensureTable();
        $val = Capsule::table(self::TABLE)->where('server_id', $serverId)->value('sub_port');

        return $val !== null ? (int) $val : null;
    }

    public static function getSubUriPath(int $serverId): ?string
    {
        self::ensureTable();
        $val = Capsule::table(self::TABLE)->where('server_id', $serverId)->value('sub_uri_path');

        return $val !== null && $val !== '' ? trim((string) $val) : null;
    }

    public static function setSubscriptionSettings(
        int $serverId,
        ?string $subDomain,
        ?int $subPort,
        ?string $subUriPath
    ): void {
        self::ensureTable();
        $subDomain = $subDomain !== null && $subDomain !== '' ? trim($subDomain) : null;
        $subUriPath = $subUriPath !== null && $subUriPath !== '' ? trim($subUriPath) : null;
        if ($subUriPath !== null) {
            $subUriPath = '/' . ltrim($subUriPath, '/');
        }
        $exists = Capsule::table(self::TABLE)->where('server_id', $serverId)->exists();
        if ($exists) {
            Capsule::table(self::TABLE)->where('server_id', $serverId)->update([
                'sub_domain' => $subDomain,
                'sub_port' => $subPort,
                'sub_uri_path' => $subUriPath,
            ]);
        } else {
            Capsule::table(self::TABLE)->insert([
                'server_id' => $serverId,
                'port' => null,
                'base_path' => null,
                'sub_domain' => $subDomain,
                'sub_port' => $subPort,
                'sub_uri_path' => $subUriPath,
            ]);
        }
    }
}
