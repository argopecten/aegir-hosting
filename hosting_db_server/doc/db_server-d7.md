# DB Server (hosting_db_server) — Architecture

## Purpose
Adds database service type and MySQL credentials management to servers.

## Key Responsibilities
- Declares `db` service type and `mysql` service implementation.
- Persists DB user/password per server revision.
- Resolves DB server by hostname/IP.

## Data Model
- `hosting_db_server`: `nid`, `vid`, `db_user`, `db_passwd`.

## Core Functions / APIs
- `hosting_db_server_hosting_service()` / `hosting_db_server_hosting_service_type()`.
- `hosting_get_db_server()` resolves by hostname/IP.
- `hosting_get_sites_on_db_server()` enumerates sites on server.
- `hosting_db_server_nodeapi_server_presave()` preserves password on save.

## Service Class
- `hostingService_db_mysql`:
  - form fields for db_user/db_passwd
  - context options include `master_db` DSN
  - import parses DSN

## Views
- Declares Views API (`hosting_db_server_views_api()`), but no default views here.
