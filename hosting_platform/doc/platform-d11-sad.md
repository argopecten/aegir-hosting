# Platform (Drupal 11) - SAD

## 1. Purpose
Manage platform records and provide verify/lock/unlock/delete tasks. Platforms must be Composer-based Drupal projects.

## 2. Drupal 7 Behavior Summary
- Platform node type with status constants (queued, enabled, locked, deleted).
- Fields: publish_path, makefile, web_server, verified, status.
- Tasks: verify, delete, lock, unlock.
- Platform list view and status helpers.
- Hostmaster platform is guarded from dangerous tasks.

**Note:** Drupal 7 supported makefile and drush make. This is no longer supported in Drupal 11.

## 3. Drupal 11 Architecture Alignment
- Content entity `hosting_platform`.
- Field API for publish_path, web_server reference, status, verified.
- Task hooks registered for verify/lock/unlock/delete.
- Access control for locked platforms.
- **Composer requirement:** All platforms must have a valid composer.json file.

## 4. Components
### 4.1 Entity and Fields
- `hosting_platform`
  - `publish_path` (string) - Must be an absolute path to a directory containing composer.json
  - `web_server` (entity ref to hosting_server)
  - `status` (enum)
  - `verified` (timestamp)

### 4.2 Services
- `PlatformManager`
  - publish path validation (must be absolute, must exist, must be unique)
  - composer.json validation (must exist, must be valid JSON, must have 'type' property)
  - status transitions and lock enforcement

### 4.3 Routes/Forms/UI
- Platform entity form with publish path and composer.json validation.
- Action links for verify/lock/unlock/delete.
- Optional inline edit link from Site entity.

### 4.4 Views/Reporting
- Default view `hosting_platform_list` with status and site count columns.

## 5. Configuration
- Default platform lock behavior in `hosting.settings`.

## 6. Permissions
- `administer platforms`, `create platform`, `view platform`, `edit platform`, `delete platform`.
- `view locked platforms`, `create sites on locked platforms`.

## 7. Integration Points
- Task module for lifecycle tasks.
- Package sync on verify.
- Client module for platform access restrictions.

## 8. Migration Notes
- Migrate `hosting_platform` table to entity storage.

## 9. Testing
- Form validation tests for publish path uniqueness and composer.json presence.
- Access tests for locked platforms.
- Composer.json validation edge cases (missing file, invalid JSON, missing 'type' property).
