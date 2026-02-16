# WHMCS Remnawave Server Module

WHMCS server provisioning module for [Remnawave](https://docs.rw/api/) panel. Provisions VPN users via the Remnawave HTTP API (Bearer token).

## Installation

1. Copy the contents of `modules/servers/remnawave/` into your WHMCS `modules/servers/remnawave/` directory.
2. Copy `includes/hooks/remnawave_server_port.php` to WHMCS `includes/hooks/` so that the Panel Port and Subscription URL section appears when editing a Remnawave server.
3. In WHMCS **Setup > Servers**, add a new server:
   - **Type**: Remnawave (V2Ray/Xray)
   - **Hostname**: Panel URL (e.g. `https://panel.example.com` or `https://panel.example.com/api`)
   - **Password**: API Token from Remnawave panel (API Tokens section). Used as Bearer token.
   - **Access Hash**: Optional; used as request timeout in seconds (default 30).
   - **Secure**: Verify SSL (recommended Yes).
4. On the same server form, after saving once, you can set **Panel Port**, **URI Path**, and **Subscription URL** (domain, port, path) if your panel or subscription is on a different host/port/path.

## Product configuration

For each product using this module:

- **Server for Squad List**: Select the Remnawave server to load internal squads.
- **Internal Squad ID**: UUID of the Remnawave Internal Squad (from "Select Squad" in admin or paste UUID). Users created for this product will be assigned to this squad.
- **Traffic (GB)**, **Expiry (days)**, **IP limit**, **Client Comment format**, **Start expiry after first use**: Same behaviour as the 3x-ui module where applicable.

## API alignment

The module uses the following API conventions. Align with the official [Remnawave API](https://docs.rw/api/) and adjust `lib/RemnawaveApi.php` if your panel version uses different paths or payloads:

- **Auth**: `Authorization: Bearer {token}`. Token from server **Password** in WHMCS.
- **Base URL**: Panel URL must end with `/api` (e.g. `https://panel.example.com/api`). The module appends `/api` if missing.
- **Endpoints used**:
  - `GET /internal-squads` — list squads (for product dropdown and login check).
  - `POST /users` — create user (payload: email, internalSquadIds, dataLimitBytes, expireAt, enabled, name/comment, limitIps).
  - `GET /users/{uuid}` — get user (subscription URL, etc.).
  - `PATCH /users/{uuid}` — update user (enabled, dataLimitBytes, expireAt, limitIps).
  - `DELETE /users/{uuid}` — delete user.
  - `GET /users/{uuid}/bandwidth` — traffic (up, down, total).
  - Optional: reset traffic, online list, last online, revoke IPs (paths in `RemnawaveApi.php`).

If your Remnawave version returns different JSON keys (e.g. `dataLimit` instead of `dataLimitBytes`, or `internalSquadIds` vs `squadIds`), update the payload in `remnawave.php` and the response handling in `RemnawaveApi.php` and `remnawave_get_subscription_info()`.

## Database

The module creates two tables when used:

- `mod_remnawave_server_config` — per-server port, base path, subscription domain/port/path.
- `mod_remnawave_service_data` — per-service user UUID, email, squad ID.

## Admin “Select Squad” (product config)

To populate the Internal Squad dropdown from the Remnawave server, the product config page can call the admin AJAX endpoint that returns squads. If you add a “Select Squad” button or dropdown that loads via AJAX, point it to:

`modules/servers/remnawave/admin/getsquads.php?serverid={server_id}`

(Requires admin session.) Response: `{ "success": true, "squads": [ { "id": "uuid", "name": "...", "remark": "..." }, ... ] }`.

## Comparison with 3x-ui module

- Remnawave uses **users** and **internal squads**; 3x-ui uses **inbounds** and **clients** on an inbound. Here one WHMCS service = one Remnawave user assigned to one squad.
- Auth is **Bearer token** (server Password), not session login.
- Subscription URL is built from user UUID (and optional subscription domain/path from server config).

## License

Proprietary. See whmcs.json.
