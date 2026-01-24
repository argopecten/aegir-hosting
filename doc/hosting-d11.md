# Hosting Core (Drupal 11) - System Architecture Document

## 1. Purpose
Provide the Hostmaster frontend core for Drupal 11: feature toggles, queue scheduling, admin UI, context registry, and Drush 13 integration for dispatching backend tasks to aegir-provision.

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
- Drush 13 commands with PHP 8 attributes for dispatch/setup/import/pause/resume.
- Feature discovery via module config and feature manager service.
- Views integration via `ViewsData` and formatter plugins.
- Service injection using Drupal's dependency injection container.

## 4. Components
### 4.1 Entities and Storage
- `hosting_context` (content entity)
  - Base table: `hosting_context`
  - Fields:
    - `id` (primary key)
    - `uuid` (UUID)
    - `context_name` (string, required, entity label)
    - `entity_type` (string, required)
    - `entity_id` (integer, required)
  - Admin permission: `administer hosting`

### 4.2 Services (hosting.services.yml)
- `logger.channel.hosting`
  - Dedicated logging channel for hosting operations
  
- `hosting.feature_manager`
  - Class: `Drupal\hosting\Service\FeatureManager`
  - Dependencies: module_handler, config.factory, module_installer, entity_type.manager
  - Discover features and dependencies
  - Enable/disable features and apply role permissions
  
- `hosting.backend_invoker`
  - Class: `Drupal\hosting\Service\BackendInvoker`
  - Dependencies: config.factory, logger.channel.hosting
  - Execute backend Drush commands with alias and options
  
- `hosting.context_registry`
  - Class: `Drupal\hosting\Service\ContextRegistry`
  - Dependencies: entity_type.manager, path.validator, logger.channel.hosting
  - CRUD for context entity and alias creation
  
- `hosting.queue_runner`
  - Class: `Drupal\hosting\Service\QueueRunner`
  - Dependencies: queue, plugin.manager.queue_worker, logger.channel.hosting
  - Run specific queue workers
  
- `hosting.queue_dispatcher`
  - Class: `Drupal\hosting\Service\QueueDispatcher`
  - Dependencies: config.factory, keyvalue, hosting.queue_runner, datetime.time, logger.channel.hosting, database, module_handler
  - Calculate next run times based on config
  - Call QueueWorkers with computed items

### 4.3 Configuration-Based Features
- Features defined in `hosting.features.yml` config
  - `enabled`: array of enabled feature machine names
  
- Queue definitions in `hosting.settings.yml`
  - Queue properties:
    - `label`: Human-readable name
    - `description`: Queue description
    - `type`: serial, batch, or spread
    - `frequency`: seconds between runs
    - `items`: number of items per run
    - `enabled`: boolean
    - `queue_id`: Drupal queue ID
    - `max_threads`: maximum concurrent threads
    - `min_threads`: minimum threads
    - `threshold`: item threshold

### 4.4 Routes/Forms/UI
- Admin routes (hosting.routing.yml):
  - `/hosting` → `HostingFeaturesForm` (Features overview)
  - `/hosting/features` → `HostingFeaturesForm` (Feature toggles)
  - `/hosting/queues` → `HostingQueuesForm` (Queue schedule config)
  - `/hosting/settings` → `HostingSettingsForm` (General settings)
  
- Forms implemented:
  - `Drupal\hosting\Form\HostingFeaturesForm`
  - `Drupal\hosting\Form\HostingQueuesForm`
  - `Drupal\hosting\Form\HostingSettingsForm`
  
- Theme templates:
  - `hosting-queues-table.html.twig`

### 4.5 Views/Reporting
- Views data + formatters:
  - interval formatting (`hosting_format_interval` equivalent)
  - status formatting (text/image/class modes)
- Default views provided by submodules (site/platform/task/etc.)

### 4.6 Drush/CLI (Drush 13 with PHP 8 Attributes)
- **Class**: `Drupal\hosting\Commands\HostingCommands`
- **Commands**:
  - `hosting:dispatch` (alias: `hosting-dispatch`)
    - Dispatch queues based on configured schedules
    - Invokes `QueueDispatcher::dispatch()`
  - `hosting:setup` (alias: `hosting-setup`)
    - Enable dispatch in config
    - Display recommended cron setup
  - `hosting:import` (alias: `hosting-import`)
    - Import backend context into registry
    - Argument: context name (e.g., @example.com)
  - `hosting:pause` (alias: `hosting-pause`)
    - Set `dispatch_enabled` to FALSE
  - `hosting:resume` (alias: `hosting-resume`)
    - Set `dispatch_enabled` to TRUE

## 5. Configuration
### 5.1 hosting.settings.yml
```yaml
dispatch_enabled: true
backend:
  drush_path: /usr/bin/drush
  alias: '@hostmaster'
queues:
  tasks:
    label: 'Tasks'
    description: 'Execute provisioning tasks.'
    type: 'serial'
    frequency: 300
    items: 5
    enabled: true
    queue_id: 'hosting_task'
    max_threads: 6
    min_threads: 1
    threshold: 100
settings:
  default_profile: ''
  default_db_server: null
  ignore_default_profiles: false
  blocked_profiles: []
  require_disable_before_delete: true
  delete_force: false
  lock_platforms_by_default: false
  platform_automatic_site_import: true
  cron_method: 'drush'
  task_logs_types_display:
    - error
    - warning
    - info
    - message
    - notice
    - ok
    - status
    - success
```

### 5.2 hosting.features.yml
```yaml
enabled: []
```

## 6. Permissions (hosting.permissions.yml)
- **administer hosting**
  - Title: 'Administer hosting'
  - Description: 'Configure and manage the hosting system.'
  
- **administer hosting features**
  - Title: 'Administer hosting features'
  - Description: 'Enable or disable Hosting features.'
  
- **administer hosting queues**
  - Title: 'Administer hosting queues'
  - Description: 'Configure queue scheduling and execution.'
  
- **administer hosting settings**
  - Title: 'Administer hosting settings'
  - Description: 'Configure general Hosting settings.'
  
- **access disabled sites**
  - Title: 'Access disabled sites'
  - Description: 'View site disabled pages.'
  
- **access hosting queues**
  - Title: 'Access hosting queues'
  - Description: 'View queue status and listings.'

## 7. Integration Points
- **hosting_task**: Task entities, queue worker, and `hosting:task` Drush command
- **hosting_server**: Server entity and web server services
- **hosting_platform**: Platform entity and deployment management
- **hosting_site**: Site entity and domain management
- **hosting_client**: Client entity and access control
- **hosting_package**: Package registry and profile management
- **hosting_db_server**: Database server configuration
- **hosting_web_server**: Web server configuration

## 8. Hooks and Events
- `hosting_cron()`: Invokes `hosting.queue_dispatcher->dispatch()` on Drupal cron
- `hosting_theme()`: Registers `hosting_queues_table` template

## 9. Migration from Drupal 7
- Migrate `hosting_context` table to `hosting_context` content entity
- Migrate variable settings to `hosting.settings` config
- Convert feature registry from include files to config-based system
- Update node-based entities to content entities
- Replace legacy nodeapi hooks with entity hooks
- Convert Drush commands from Drush 8 to Drush 13 with PHP 8 attributes

## 10. Current Implementation Status
✅ **Completed:**
- Core service architecture (6 services)
- HostingContext entity with field definitions
- Configuration schema for settings and features
- Drush 13 commands with PHP 8 attributes
- Admin routes and forms
- Permission system
- Cron integration
- Logger channel
- Theme template system

⚠️ **In Progress:**
- Feature plugin/registry system
- Views integration and data handlers
- Queue worker plugins
- Task execution flow

## 11. Dependencies
- **PHP**: >=8.3
- **Drupal Core**: ^11.2
- **Drush**: 13.x
- **Backend**: argopecten/aegir-provision 11.x-dev
- **Theme**: argopecten/aegir-eldir 11.x-dev
