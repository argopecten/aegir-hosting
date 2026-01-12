# Hosting Core (Drupal 11) - SAD

## 1. Purpose
Provide the Hostmaster frontend core for Drupal 11: feature toggles, queue scheduling, admin UI, context registry, and Drush integration for dispatching backend tasks.

## 2. Drupal 7 Behavior Summary
- `hosting.module` defines admin routes, permissions, blocks, and form alters.
- Node hooks are routed through `hosting_nodeapi()` to per-type handlers.
- Features subsystem discovers `hosting.feature.*.inc` definitions and can enable/disable modules and assign permissions.
- Queue subsystem supports serial/batch/spread scheduling with calculated frequency and items.
- Dispatch command (`hosting-dispatch`) triggers queue runs; cron is installed via `hosting-setup`.
- Context registry table `hosting_context` maps node IDs to backend context names and creates `hosting/c/<name>` path aliases.
- Settings form covers default profile, platform lock behavior, auto import, delete policies, cron method, log type visibility.
- Task queue summary and queue list blocks shown in the UI.
- Views handlers for interval/status formatting.

## 3. Drupal 11 Architecture Alignment
- Config API replaces variables for all settings and feature toggles.
- Content entity `hosting_context` replaces the table for context mapping.
- Queue scheduling implemented as a service (`hosting.queue_dispatcher`) plus cron hook.
- Drush 12 commands for dispatch/setup/import/pause/resume.
- Feature discovery via annotated plugin or YAML registry (instead of legacy include files).
- Views integration via `ViewsData` and formatter plugins.

## 4. Components
### 4.1 Entities and Storage
- `hosting_context` (content entity)
  - `context_name` (string, unique)
  - `entity_type` (string)
  - `entity_id` (integer)

### 4.2 Services
- `hosting.feature_manager`
  - discover features and dependencies
  - enable/disable features and apply role permissions
- `hosting.queue_dispatcher`
  - calculate next run times based on config
  - call QueueWorkers with computed items
- `hosting.queue_runner`
  - run specific queue workers
- `hosting.backend_invoker`
  - execute backend drush commands with alias and options
- `hosting.context_registry`
  - CRUD for context entity and alias creation

### 4.3 Plugins
- `hosting_feature` plugin (or YAML registry)
  - keys: title, description, module, required, dependencies, role permissions
- `hosting_queue` plugin
  - types: serial, batch, spread
  - properties: frequency, items, enabled, max_threads, threshold

### 4.4 Routes/Forms/UI
- Admin routes:
  - `/admin/hosting` (overview/features)
  - `/admin/hosting/features` (feature toggles)
  - `/admin/hosting/queues` (queue schedule config)
  - `/admin/hosting/settings` (general settings)
- Public routes:
  - `/hosting/queues` (queue list/summary)
  - `/hosting/disabled` and `/hosting/maintenance` pages
- Form behaviors:
  - hide package/task edit forms (UI disabled)
  - display task queued messages after node saves

### 4.5 Views/Reporting
- Views data + formatters:
  - interval formatting (`hosting_format_interval` equivalent)
  - status formatting (text/image/class modes)
- Default views provided by submodules (site/platform/task/etc.)

### 4.6 Drush/CLI
- `hosting:dispatch` (queue execution)
- `hosting:setup` (initial cron/dispatch setup)
- `hosting:import` (import backend context)
- `hosting:pause` / `hosting:resume`

## 5. Configuration
- `hosting.settings`
  - dispatch enabled
  - backend drush path + alias
  - queue definitions (frequency/items/enabled)
  - general settings (lock platforms, delete policies, auto import)
- `hosting.features`
  - enabled features list

## 6. Permissions
- `administer hosting`
- `administer hosting features`
- `administer hosting queues`
- `administer hosting settings`
- `access disabled sites`
- `access hosting queues`

## 7. Integration Points
- Task module for queue definitions and task execution.
- Server/platform/site modules for feature-provided node types.
- Client module for access rules.
- Package module for profile selection and platform sync.

## 8. Migration Notes
- Migrate `hosting_context` table to `hosting_context` entity.
- Migrate variable settings to `hosting.settings` config.
- Convert feature registry from include files to plugin or YAML.

## 9. Testing
- Kernel tests for config defaults and context registry.
- Functional tests for admin forms and queue dispatch.
