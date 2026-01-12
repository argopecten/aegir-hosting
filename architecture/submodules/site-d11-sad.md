# Site (Drupal 11) - SAD

## 1. Purpose
Manage hosted sites, drive provisioning tasks, and expose domain-related utilities.

## 2. Drupal 7 Behavior Summary
- Site node type with status constants (queued, enabled, disabled, deleted).
- Fields: client, db_server, db_name, platform, profile, language, cron_key, verified.
- Tasks: install, verify, backup, enable, disable, delete, login-reset.
- Domain validation and canonical URL helpers.
- Backups stored in `hosting_site_backups`.
- Form callbacks update profile/language options based on platform.
- Views: default site list with custom fields.

## 3. Drupal 11 Architecture Alignment
- Content entity `hosting_site`.
- Content entity `hosting_site_backup`.
- Service for domain validation and canonical URL rules.
- Task definitions for site lifecycle actions.

## 4. Components
### 4.1 Entities and Fields
- `hosting_site`
  - `domain` (string)
  - `client` (entity ref to hosting_client)
  - `platform` (entity ref to hosting_platform)
  - `db_server` (entity ref to hosting_server)
  - `db_name` (string)
  - `profile` (entity ref to hosting_package)
  - `language` (string)
  - `status` (enum)
  - `verified`, `last_cron`, `cron_key` (timestamps/strings)
- `hosting_site_backup`
  - `site`, `web_server`, `filename`, `description`, `size`, `timestamp`

### 4.2 Services
- `SiteManager`
  - validation of domain and policy checks
  - status transitions and task creation
- `DomainValidator`
  - implement allow/deny rules and external hooks
- `BackupManager`
  - create/delete/list backups

### 4.3 Routes/Forms/UI
- Site entity form with dynamic platform/profile/language selectors.
- Add-site route on platform view.
- Backup list and delete confirmation forms.
- Optional goto-site route (redirect to canonical URL).

### 4.4 Views/Reporting
- Default view `hosting_site_list`.
- Custom field handlers for canonical URL, language, and status.

## 5. Configuration
- Site policy settings in `hosting.settings`:
  - require disable before delete
  - delete force
  - auto import

## 6. Permissions
- `administer sites`, `create site`, `view site`, `edit site`, `delete site`.
- Client-based access filtering when Client module is enabled.

## 7. Integration Points
- Task module for lifecycle tasks and log display.
- Platform and DB server modules for provisioning context.
- Optional quota integration (event subscriber or service).

## 8. Migration Notes
- Migrate `hosting_site` and `hosting_site_backups`.
- Map D7 status codes to D11 enum values.

## 9. Testing
- Form tests for domain validation and profile selection.
- Task integration tests for install/verify flows.
