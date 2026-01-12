# Platform (hosting_platform) — Architecture

## Purpose
Defines Platform node type and operations (verify, lock, unlock, delete).

## Key Responsibilities
- Platform node lifecycle and validation.
- Task hooks: verify/lock/unlock/delete.
- Platform filtering and access logic.

## Data Model
- `hosting_platform`: (`publish_path`, `makefile`, `web_server`, `verified`, `status`, `make_working_copy`).

## Core Functions / APIs
- Platform queries: `_hosting_get_platforms()`, `_hosting_get_enabled_platforms()`, `_hosting_get_locked_platforms()`.
- Status helpers: `_hosting_platform_status_codes()` and label/class mapping.
- Task actions: `hosting_platform_verify_action()`, `hosting_platform_lock_action()`, etc.
- Validation: `hosting_platform_form_publish_path_validate()`, `hosting_platform_validate()`.

## Forms / UI
- Platform node form: `hosting_platform_form()`.
- Custom “Edit Platform” tab on site nodes.

## Views
- Views data + handlers in `platform/includes/views`.
- Default view: `hosting_platform_list`.

## Drush
- Drush context options/import and post‑task handlers in `hosting_platform.drush.inc`.
