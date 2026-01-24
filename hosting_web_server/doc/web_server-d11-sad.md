# Web Server (Drupal 11) - SAD

## 1. Purpose
Provide HTTP service implementations for servers, starting with Apache.

## 2. Drupal 7 Behavior Summary
- Declares service type `http` and provider `apache`.
- Apache provider supplies defaults: port 80 and restart command detection.
- Uses shared service configuration in server form.

## 3. Drupal 11 Architecture Alignment
- Plugin type: `hosting_service_type` for `http`.
- Plugin type: `hosting_service_provider` for `apache`.
- Provider exposes defaults and form elements used by the server form.

## 4. Components
### 4.1 Plugins
- Service type plugin: `http` (title: Web).
- Provider plugin: `apache` with:
  - defaults: port 80
  - restart command detection (filesystem lookup)
  - form fields: `port`, `restart_cmd`

### 4.2 Routes/Forms/UI
- Provider form rendered on Server entity edit form via `hosting.service_manager`.

### 4.3 Views/Reporting
- No module-specific views; service data appears in server views.

## 5. Configuration
- No module-specific config; uses server service instance config.

## 6. Permissions
- Inherits server permissions.

## 7. Integration Points
- Interacts with SSL providers (optional).
- Used by platform/site provisioning when building backend context options.

## 8. Migration Notes
- Migrate `hosting_service` rows where `service=http` and `type=apache` into service instances.

## 9. Testing
- Kernel tests for provider defaults.
- Functional tests for form rendering on Server edit.
