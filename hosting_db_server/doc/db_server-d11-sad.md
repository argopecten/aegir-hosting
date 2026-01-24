# DB Server (Drupal 11) - SAD

## 1. Purpose
Provide database service implementations and store credentials for provisioning.

## 2. Drupal 7 Behavior Summary
- Declares service type `db` and provider `mysql`.
- Stores db user/pass per server in `hosting_db_server`.
- Preserves password on server update if blank.
- Builds backend DSN (master_db) for context options.
- Helper to resolve DB server by hostname/IP.

## 3. Drupal 11 Architecture Alignment
- Plugin type: `hosting_service_type` for `db`.
- Provider plugin: `mysql`.
- Credentials stored on `hosting_service_instance` (encrypted or key-managed).
- Service for DSN generation used by task execution.

## 4. Components
### 4.1 Plugins
- Service type: `db` (title: Database)
- Provider: `mysql`
  - defaults: port 3306
  - form fields: `db_user`, `db_passwd`, `port`
  - DSN generation: `mysql://user:pass@host`

### 4.2 Services
- `DbServerResolver`
  - lookup by hostname or IP
- `DbCredentialsManager`
  - optional encryption and secret rotation

### 4.3 Routes/Forms/UI
- Provider settings shown in server form (via service manager).

## 5. Configuration
- Password storage strategy (plain/encrypted) in config.

## 6. Permissions
- Credentials visible only to users with `administer servers`.

## 7. Integration Points
- Task module uses provider DSN in context options.
- Site module uses DB server selection for provisioning.

## 8. Migration Notes
- Migrate `hosting_db_server` rows into service instance config.

## 9. Testing
- Kernel tests for credential persistence and DSN generation.
