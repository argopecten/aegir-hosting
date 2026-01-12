# Platform (Drupal 11) - SAD

## 1. Purpose
Manage platform records and provide verify/lock/unlock/delete tasks.

## 2. Drupal 7 Behavior Summary
- Platform node type with status constants (queued, enabled, locked, deleted).
- Fields: publish_path, makefile, web_server, verified, status.
- Tasks: verify, delete, lock, unlock.
- Platform list view and status helpers.
- Hostmaster platform is guarded from dangerous tasks.

## 3. Drupal 11 Architecture Alignment
- Content entity `hosting_platform`.
- Field API for publish_path, makefile, web_server reference, status, verified.
- Task hooks registered for verify/lock/unlock/delete.
- Access control for locked platforms.

## 4. Components
### 4.1 Entity and Fields
- `hosting_platform`
  - `publish_path` (string)
  - `makefile` (text)
  - `web_server` (entity ref to hosting_server)
  - `status` (enum)
  - `verified` (timestamp)
  - `make_working_copy` (boolean)

### 4.2 Services
- `PlatformManager`
  - publish path validation (unique + exists)
  - status transitions and lock enforcement

### 4.3 Routes/Forms/UI
- Platform entity form with publish path validation.
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
- Form validation tests for publish path uniqueness.
- Access tests for locked platforms.
