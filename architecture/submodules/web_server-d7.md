# Web Server (hosting_web_server) — Architecture

## Purpose
Provides HTTP/web service type for servers (Apache implementation).

## Key Responsibilities
- Declares `http` service type and `apache` service implementation.
- Supplies service class with port/restart command defaults.

## Data Model
- No own tables; relies on `hosting_service` from `hosting_server`.

## Core Functions / APIs
- `hosting_web_server_hosting_service()` → maps `apache` to `http`.
- `hosting_web_server_hosting_service_type()` → declares `http` service type.
- Service class: `hostingService_http_apache` in `hosting_web_server.service.inc`:
  - default port 80
  - restart command detection

## Forms / UI
- Uses shared service form in `hosting_server` (port/restart command from service class).

## Views
- No module‑specific views; service fields appear in server views.

## Drush
- None in this core module.
