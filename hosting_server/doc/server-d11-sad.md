# Server (Drupal 11) - SAD

## 1. Purpose
Provide the Hosting Server domain for Drupal 11: store server records, attach service implementations (web/db/etc.), manage IP address lists, and coordinate verify/delete tasks.

## 2. Drupal 7 Behavior Summary
- Node type: `server` with status codes (queued, enabled, locked, deleted).
- Service registry uses `hook_hosting_service_type()` and `hook_hosting_service()` to attach service instances.
- Service data stored in `hosting_service` (service/type/restart_cmd/port/available).
- Server data stored in `hosting_server` (human_name/verified/status).
- IP addresses stored in `hosting_ip_addresses` and validated on form save.
- Server form builds per-service configuration tabs.
- Tasks: verify and delete (via `hook_hosting_tasks()`).
- Views: default `hosting_server_list` table with dynamic service columns.
- Integration with SSL IP allocation via `hosting_ssl_cert_ips` when removing IPs.

## 3. Drupal 11 Architecture Alignment
- Content entity `hosting_server` replaces node type.
- Content entity `hosting_service_instance` represents service assignment per server.
- Multi-value field for IP addresses on `hosting_server`.
- Plugin API for service types and service providers.
- Services: server manager, service manager, IP manager.
- Entity hooks or events trigger verify tasks.
- ViewsData provider + default view config for server listing.

## 4. Components
### 4.1 Entities and Fields
- `hosting_server`
  - `hostname` (string, required, unique)
  - `human_name` (string, optional)
  - `status` (list integer: queued/enabled/locked/deleted)
  - `verified` (timestamp)
  - `ip_addresses` (multi-value string, validated as IP)
- `hosting_service_instance`
  - `server` (entity reference to hosting_server)
  - `service_type` (string, e.g. http, db)
  - `provider` (string, e.g. apache, mysql)
  - `available` (boolean)
  - `port` (integer)
  - `restart_cmd` (string)
  - `config` (map/serialized provider-specific fields)

### 4.2 Services
- `hosting.server_manager`
  - validate hostname (FQDN/IP), enforce uniqueness
  - set default status transitions
- `hosting.service_manager`
  - plugin discovery for service types/providers
  - build provider config forms and apply submitted values
- `hosting.ip_manager`
  - validate IPs
  - optional allocation hooks for SSL integration

### 4.3 Plugins
- Service type plugin: `http`, `db`, etc.
- Service provider plugins: `apache`, `mysql`, etc.

### 4.4 Routes/Forms/UI
- Entity form for `hosting_server`:
  - hostname + human name
  - IP list widget
  - per-service settings in tabs (from provider plugins)
- Hide delete action until status == deleted (mirrors D7 behavior).
- Admin config to set default DB server (`hosting.settings`).

### 4.5 Views/Reporting
- Views data for `hosting_server` and `hosting_service_instance`.
- Default view `hosting_server_list`:
  - columns: status, hostname, human_name, services
  - row classes based on status
  - optional dynamic service columns similar to D7

### 4.6 Tasks and Events
- Task types for server: verify, delete.
- Event subscriber on server save/delete to enqueue verify (unless suppressed).

## 5. Configuration
- `hosting.settings` default DB server setting.

## 6. Permissions
- `administer servers`, `create server`, `view server`, `edit server`, `delete server`.
- Access control handler uses permission + optional client grants.

## 7. Integration Points
- Service providers from `web_server` and `db_server` modules.
- SSL/IP allocation integration (optional, via IP manager).
- Task module for verify/delete execution and logs.

## 8. Migration Notes
- Migrate `hosting_server` and `hosting_service` tables into new entities.
- Convert `hosting_ip_addresses` into field values.

## 9. Testing
- Kernel tests for entity schema and service instance persistence.
- Functional tests for server form tabs and validation.
