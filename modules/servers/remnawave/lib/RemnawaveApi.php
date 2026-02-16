<?php

namespace WHMCS\Module\Server\Remnawave;

use Exception;

/**
 * API client for Remnawave panel (Bearer token auth).
 * Align endpoints with https://docs.rw/api/ when available.
 */
class RemnawaveApi
{
    private string $baseUrl;

    private string $token;

    private bool $verifySsl;

    private int $timeout;

    /** API path prefix (base URL already ends with /api in Remnawave). */
    private string $apiPath = '';

    public function __construct(array $serverParams)
    {
        $this->baseUrl = rtrim($serverParams['serverhostname'] ?? '', '/');
        if (strpos($this->baseUrl, 'http') !== 0) {
            $this->baseUrl = 'https://' . $this->baseUrl;
        }
        if (strpos($this->baseUrl, '/api') !== strlen($this->baseUrl) - 4) {
            $this->baseUrl = rtrim($this->baseUrl, '/') . '/api';
        }
        $this->token = (string) ($serverParams['serverpassword'] ?? '');
        $this->verifySsl = (bool) ($serverParams['serversecure'] ?? true);
        $this->timeout = (int) ($serverParams['serveraccesshash'] ?? 30);
        if ($this->timeout < 5) {
            $this->timeout = 30;
        }
    }

    private function getBaseUrl(): string
    {
        $u = parse_url($this->baseUrl);
        $scheme = $u['scheme'] ?? 'https';
        $host = $u['host'] ?? $u['path'] ?? '';
        $port = isset($u['port']) ? ':' . $u['port'] : '';
        $path = isset($u['path']) && $u['path'] !== '' ? rtrim($u['path'], '/') : '';

        return $scheme . '://' . $host . $port . $path;
    }

    private function request(string $method, string $path, $body = null): array
    {
        $url = $this->getBaseUrl() . '/' . ltrim($path, '/');
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
            }
        } elseif ($method === 'PATCH' || $method === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = is_string($body) ? $body : json_encode($body);
            }
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('API request failed: ' . $err);
        }

        $data = json_decode($response, true);
        if ($httpCode >= 400) {
            $msg = is_array($data) ? ($data['message'] ?? $data['error'] ?? $response) : $response;
            throw new Exception($msg);
        }

        return $data ?? [];
    }

    /**
     * Validate connection: ensure Bearer token works (list squads or metadata).
     */
    public function login(): void
    {
        $data = $this->get('internal-squads');
        if (isset($data['success']) && $data['success'] === false) {
            throw new Exception($data['message'] ?? 'Authentication failed');
        }
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path, null);
    }

    public function post(string $path, $body = null): array
    {
        return $this->request('POST', $path, $body);
    }

    public function patch(string $path, $body = null): array
    {
        return $this->request('PATCH', $path, $body);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path, null);
    }

    // ---------- Internal Squads / Inbounds (for product config) ----------

    /**
     * List internal squads (used like inbounds for product mapping).
     *
     * @return list<array>
     */
    public function inboundsList(): array
    {
        $data = $this->get('internal-squads');
        $list = $data['data'] ?? $data['items'] ?? $data['squads'] ?? $data['obj'] ?? [];
        if (isset($data['internalSquads'])) {
            $list = $data['internalSquads'];
        }

        return is_array($list) ? $list : [];
    }

    /**
     * Get one internal squad by id/uuid.
     */
    public function squadGet(string $id): array
    {
        $data = $this->get('internal-squads/' . rawurlencode($id));

        return $data['data'] ?? $data['obj'] ?? $data;
    }

    // ---------- Users ----------

    /**
     * Create user. Payload per Remnawave API (email, internalSquadIds, dataLimit, expireAt, etc.).
     *
     * @return array with user uuid and subscription url
     */
    public function createUser(array $payload): array
    {
        $data = $this->post('users', $payload);

        return $data['data'] ?? $data['user'] ?? $data['obj'] ?? $data;
    }

    /**
     * Get user by uuid or email.
     */
    public function getUser(string $uuidOrEmail): array
    {
        $data = $this->get('users/' . rawurlencode($uuidOrEmail));

        return $data['data'] ?? $data['user'] ?? $data['obj'] ?? $data;
    }

    /**
     * List users (optional filter by email).
     *
     * @return list<array>
     */
    public function listUsers(?string $email = null): array
    {
        $path = 'users';
        if ($email !== null && $email !== '') {
            $path .= '?email=' . rawurlencode($email);
        }
        $data = $this->get($path);
        $list = $data['data'] ?? $data['users'] ?? $data['items'] ?? $data['obj'] ?? [];

        return is_array($list) ? $list : [];
    }

    /**
     * Update user (enable/disable, traffic, expiry).
     */
    public function updateUser(string $uuid, array $payload): void
    {
        $this->patch('users/' . rawurlencode($uuid), $payload);
    }

    /**
     * Delete user by uuid.
     */
    public function deleteUser(string $uuid): void
    {
        $this->delete('users/' . rawurlencode($uuid));
    }

    /**
     * Get user traffic/bandwidth. Returns [up, down, total] in bytes where applicable.
     *
     * @return array<string, mixed>
     */
    public function getClientTraffics(string $userUuidOrEmail): array
    {
        $data = $this->get('users/' . rawurlencode($userUuidOrEmail) . '/bandwidth');
        $obj = $data['data'] ?? $data['obj'] ?? $data;
        if (isset($data['bandwidth'])) {
            $obj = $data['bandwidth'];
        }

        return is_array($obj) ? $obj : ['up' => 0, 'down' => 0, 'total' => 0];
    }

    /**
     * Reset user traffic (if supported by API).
     */
    public function resetClientTraffic(string $userUuid): void
    {
        $this->post('users/' . rawurlencode($userUuid) . '/actions/reset-traffic', (object) []);
    }

    /**
     * Get online clients (if supported).
     *
     * @return list<array>
     */
    public function onlines(): array
    {
        $data = $this->get('users/onlines');
        $list = $data['data'] ?? $data['obj'] ?? $data['users'] ?? [];

        return is_array($list) ? $list : [];
    }

    /**
     * Last online timestamps (if supported).
     *
     * @return array<string, int>
     */
    public function lastOnline(): array
    {
        $data = $this->get('users/last-online');
        $obj = $data['data'] ?? $data['obj'] ?? [];

        return is_array($obj) ? $obj : [];
    }

    /**
     * Revoke / clear client IPs (if supported).
     */
    public function clearClientIps(string $userUuidOrEmail): void
    {
        $this->post('users/' . rawurlencode($userUuidOrEmail) . '/actions/revoke', [
            'revokeOnlyPasswords' => false,
        ]);
    }
}
