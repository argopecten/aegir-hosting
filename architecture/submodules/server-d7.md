# Server (hosting_server) — Architecture

## Purpose
Defines the Server node type and the shared service framework used by web/db/etc. services.

## Key Responsibilities
- Server node lifecycle: create/update/delete/load/view.
- Service registry and service instances per server.
- IP address management for servers.
- Task integration for verify/delete.

## Data Model
- `hosting_server`: server metadata (`nid`, `vid`, `human_name`, `verified`, `status`).
- `hosting_service`: service assignments (`nid`, `vid`, `service`, `type`, `restart_cmd`, `port`, `available`).
- `hosting_ip_addresses`: IP list per server.

## Core Functions / APIs
- Service registry: `hosting_server_services()`, `hosting_services_new_object()`, `hosting_services_add()`.
- Service lifecycle: `hosting_server_init_services()`, `hosting_server_services_from_post()`, `hosting_server_invoke_services()`.
- Status helpers: `_hosting_server_status_codes()`, `_hosting_server_status()`, `_hosting_server_list_class()`.
- IP CRUD: `_hosting_ip_view()`, `_hosting_ip_save()`, `_hosting_ip_validate()`, `_hosting_ip_load()`, `_hosting_ip_delete()`.
- IP allocation: `hosting_ip_allocate()`, `hosting_server_ip_allocate()`.

## Forms / UI
- Server node form `hosting_server_form()` with:
  - hostname + human name
  - IP address list
  - per‑service config via vertical tabs
- Form alter hides delete button until server is marked deleted.

## Views
- Views data: `server/includes/views/hosting_server.views.inc`
  - Custom fields: services, status, human_name.
- Default view: `hosting_server_list` (table; status class; services columns).
- Custom handlers: `hosting_server_handler_field_services`, `hosting_server_handler_field_status`, `hosting_server_handler_field_human_name`.

## Drush / Hooks
- Drush context options and import in `server/hosting_server.drush.inc`.
- Hook API in `server/hosting_server.api.php`:
  - `hook_hosting_service_type()`, `hook_hosting_service()`
  - `hook_hosting_servers_titles_alter()`.
