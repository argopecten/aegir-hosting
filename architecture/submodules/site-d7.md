# Site (hosting_site) — Architecture

## Purpose
Defines Site node type and site provisioning logic.

## Key Responsibilities
- Site node lifecycle and validation.
- Site tasks: install/verify/backup/enable/disable/delete/login-reset.
- Domain checks and canonical URL handling.
- Backups, quotas, and status tracking.

## Data Model
- `hosting_site`: (`client`, `db_server`, `db_name`, `platform`, `profile`, `language`, `verified`, `status`, `cron_key`, etc.).
- `hosting_site_backups`: backup entries (file, description, size, timestamp).

## Core Functions / APIs
- URL helpers: `hosting_site_canonical_url()`, `_hosting_site_url()`.
- Status helpers: `hosting_site_status_codes()`, `_hosting_site_list_class()`.
- Domain rules: `hosting_site_allow_domain()`, `hosting_site_clean_domain()`.
- Task integration: `hosting_site_hosting_tasks()` + task hook handlers in `hosting_site.drush.inc`.

## Forms / UI
- Site node form in `hosting_site.form.inc`:
  - platform/profile selection
  - language selection
  - db_server selection
  - js callbacks for platform/language updates
- Backup forms in `hosting_site.backups.inc`.

## Views
- Views data + handlers in `site/includes/views`.
- Default view: `hosting_site_list` (includes canonical URL and language handlers).

## Drush
- Extensive task hooks for install/verify/backup/delete/etc. in `hosting_site.drush.inc`.
