# Client (hosting_client) — Architecture

## Purpose
Multi‑client support: client node type, user association, access control, and platform access.

## Key Responsibilities
- Client node lifecycle and validation.
- User ↔ client mapping and access grants.
- Client‑specific platform access rules.
- Optional client‑user registration flow.

## Data Model
- `hosting_client`: client metadata (`nid`, `vid`, `uname`).
- `hosting_client_user`: many‑to‑many user/client relation.
- `hosting_platform_client_access`: per‑client platform access.

## Core Functions / APIs
- Access control:
  - `hosting_client_node_access()`
  - `hosting_client_node_grants()` and `hosting_client_node_access_records()`
- Lookup helpers: `hosting_get_client()`, `hosting_get_client_by_uname()`, `_hosting_get_clients()`.
- User association: `hosting_client_user_*` in `hosting_client.access.inc`.

## Forms / UI
- Client node form: `hosting_client_form()` (title + machine name).
- User profile form alterations:
  - assign/remove clients on user edit
- Platform access form:
  - `_hosting_client_platform_access()` and `hosting_client_platform_access_form()`
- Client config form: `hosting_client_configure()`.

## Views
- Views data: `client/includes/views/hosting_client.views.inc`.
- Default view: `hosting_client_list`.
