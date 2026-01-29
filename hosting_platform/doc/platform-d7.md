# Platform (hosting_platform) — Architecture (Drupal 7 Legacy)

**Note:** This document describes the Drupal 7 implementation. The Drupal 11 version has removed support for makefile and drush make. All platforms must now be Composer-based Drupal projects with a composer.json file.

## Purpose
Defines Platform node type and operations (verify, lock, unlock, delete).

## Key Responsibilities
- Platform node lifecycle and validation.
- Task hooks: verify/lock/unlock/delete.
- Platform filtering and access logic.

## Data Model (Drupal 7)
- `hosting_platform`: (`publish_path`, `makefile`, `web_server`, `verified`, `status`, `make_working_copy`).

**Removed in Drupal 11:** `makefile` and `make_working_copy` fields are no longer supported.

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
