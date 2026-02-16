<?php

namespace WHMCS\Module\Server\Remnawave;

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Store per-service Remnawave data (user uuid, email, squad id) for the module.
 */
class ServiceData
{
    private const TABLE = 'mod_remnawave_service_data';

    public static function ensureTable(): void
    {
        if (Capsule::schema()->hasTable(self::TABLE)) {
            return;
        }
        Capsule::schema()->create(self::TABLE, function ($table) {
            $table->increments('id');
            $table->unsignedInteger('service_id')->unique();
            $table->string('squad_id', 64)->nullable();
            $table->string('user_uuid', 64);
            $table->string('client_email', 255);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public static function save(
        int $serviceId,
        string $userUuid,
        string $clientEmail,
        ?string $squadId = null
    ): void {
        self::ensureTable();
        $now = date('Y-m-d H:i:s');
        Capsule::table(self::TABLE)->updateOrInsert(
            ['service_id' => $serviceId],
            [
                'squad_id' => $squadId,
                'user_uuid' => $userUuid,
                'client_email' => $clientEmail,
                'updated_at' => $now,
                'created_at' => Capsule::table(self::TABLE)->where('service_id', $serviceId)->value('created_at') ?: $now,
            ]
        );
    }

    /**
     * @return array{squad_id: ?string, user_uuid: string, client_email: string}|null
     */
    public static function get(int $serviceId): ?array
    {
        self::ensureTable();
        $row = Capsule::table(self::TABLE)->where('service_id', $serviceId)->first();
        if ($row === null) {
            return null;
        }

        return [
            'squad_id' => $row->squad_id !== null ? (string) $row->squad_id : null,
            'user_uuid' => (string) $row->user_uuid,
            'client_email' => (string) $row->client_email,
        ];
    }

    public static function delete(int $serviceId): void
    {
        self::ensureTable();
        Capsule::table(self::TABLE)->where('service_id', $serviceId)->delete();
    }
}
