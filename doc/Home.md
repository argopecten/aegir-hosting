# Hosting (Drupal 11)

Welcome to the Aegir Hosting module documentation. This is the Drupal 11 Hosting/Hostmaster frontend module providing a complete hosting management system with entity-based architecture, service-oriented design, and Drush 13 integration.

## Quick Links
- [Hosting D11 Architecture](hosting-d11.md): Complete system architecture document
- [Hosting D7 Reference](hosting-d7.md): Drupal 7 legacy reference

## Directory Structure

```
aegir-hosting/
├── src/                          # Core hosting module
│   ├── Entity/
│   │   └── HostingContext.php   # Entity ↔ Context registry
│   ├── Service/
│   │   ├── ContextRegistry.php  # Context synchronization
│   │   ├── BackendInvoker.php   # Drush command execution
│   │   ├── QueueDispatcher.php  # Task queue orchestration
│   │   └── FeatureManager.php   # Feature discovery/management
│   ├── Commands/
│   │   └── HostingCommands.php  # Drush commands (hosting:*)
│   ├── Form/
│   │   ├── HostingSettingsForm.php
│   │   ├── HostingFeaturesForm.php
│   │   └── HostingQueuesForm.php
│   └── Controller/
│       └── HostingController.php
├── hosting_site/                # Site entity module
│   ├── src/
│   │   ├── Entity/HostingSite.php
│   │   ├── Form/HostingSiteForm.php
│   │   └── Service/SiteManager.php
├── hosting_platform/            # Platform entity module
│   ├── src/
│   │   ├── Entity/HostingPlatform.php
│   │   ├── Form/HostingPlatformForm.php
│   │   └── Service/PlatformManager.php
├── hosting_server/              # Server entity module
│   ├── src/
│   │   ├── Entity/HostingServer.php
│   │   ├── Entity/HostingServiceInstance.php
│   │   ├── Form/HostingServerForm.php
│   │   └── Service/ServerManager.php
├── hosting_task/                # Task queue module
│   ├── src/
│   │   ├── Entity/HostingTask.php
│   │   ├── Form/HostingTaskForm.php
│   │   ├── Service/TaskManager.php
│   │   └── Plugin/QueueWorker/HostingTaskQueueWorker.php
├── hosting_client/              # Client entity module
├── hosting_package/             # Package tracking module
├── hosting_db_server/           # DB server specifics
└── hosting_web_server/          # Web server specifics
```

## Architecture

### Entity ↔ Context Mapping

The hosting system maintains a bidirectional mapping between Drupal entities and Provision contexts:

```
HostingSite (entity) ↔ @example.com (context)
HostingPlatform (entity) ↔ @platform_d11 (context)
HostingServer (entity) ↔ @server_master (context)
```

**Key Principle**: All data operations go through Drupal entities. Forms NEVER directly manipulate Provision contexts. The ContextRegistry service handles synchronization:

1. Entity is created/updated in Drupal
2. Entity save triggers context registration
3. ContextRegistry generates Drush alias YAML
4. Path alias created at `/hosting/c/{context_name}`
5. Backend operations queued through task system

### Core Services
- **ContextRegistry**: Entity ↔ Context synchronization and registry management
- **FeatureManager**: Feature discovery, enabling/disabling, and permission management
- **QueueDispatcher**: Schedule-based queue execution with cron integration
- **QueueRunner**: Queue worker execution and management
- **BackendInvoker**: Drush command execution for aegir-provision backend
- **Logger**: Dedicated `hosting` logging channel

### Manager Services (Business Logic Layer)
- **SiteManager**: Site entity business logic (domain validation, status management)
- **PlatformManager**: Platform entity operations and package scanning
- **ServerManager**: Server entity and service instance management
- **TaskManager**: Task creation, execution, and lifecycle management

### Entity System
- **HostingContext**: Content entity for backend context mapping
  - Fields: `context_name`, `entity_type`, `entity_id`
  - Manages relationships between Drupal entities and backend contexts

### Configuration
- **hosting.settings.yml**: Dispatch settings, backend config, queue definitions, general settings
- **hosting.features.yml**: Enabled features registry
- **hosting.schema.yml**: Configuration schema validation

## Features
- Configurable queue dispatch with serial/batch/spread scheduling
- Context registry entity with optional `hosting/c/<name>` alias management
- Admin UI at `/hosting` for features, queues, and settings
- Drush 13 commands with PHP 8 attributes
- Cron-triggered automatic queue execution
- Service-oriented architecture with dependency injection
- Template system for custom UI rendering

## Submodules
- **hosting_task**: Task entities, queue worker, and `hosting:task` Drush command
- **hosting_server**: Server entity and services
- **hosting_platform**: Platform entity and deployment management
- **hosting_site**: Site entity and domain management
- **hosting_client**: Client entity and access control
- **hosting_package**: Package registry and profile management
- **hosting_db_server**: Database server configuration
- **hosting_web_server**: Web server configuration

## Configuration
- Settings live in `hosting.settings` config object
- Feature toggles live in `hosting.features` config object
- Queue state tracked in key/value storage (`hosting.queue_state`)
- Backend config: Drush path and hostmaster alias
- Queue definitions: frequency, items, threads, thresholds

## Drush Commands
All commands support legacy aliases for backward compatibility:

- `drush hosting:dispatch` (`hosting-dispatch`)
  - Execute queues based on configured schedules
- `drush hosting:setup` (`hosting-setup`)
  - Initialize dispatch configuration and display cron setup
- `drush hosting:import @example.com` (`hosting-import`)
  - Import backend context into frontend registry
- `drush hosting:pause` (`hosting-pause`)
  - Temporarily disable queue dispatch
- `drush hosting:resume` (`hosting-resume`)
  - Re-enable queue dispatch

## Permissions
- `administer hosting`: Full system administration
- `administer hosting features`: Manage feature toggles
- `administer hosting queues`: Configure queue schedules
- `administer hosting settings`: Modify general settings
- `access disabled sites`: View disabled site pages
- `access hosting queues`: View queue status

## Requirements
- PHP >= 8.3
- Drupal Core ^11.2
- Drush 13.x
- argopecten/aegir-provision 11.x-dev (backend)
- argopecten/aegir-eldir 11.x-dev (theme)

## Backend Integration
Backend provisioning requires aegir-provision (Drush 13 backend) configured via `hosting.settings`:
```yaml
backend:
  drush_path: /usr/bin/drush
  alias: '@hostmaster'
```

The BackendInvoker service executes Drush provision commands, translating entity operations into backend tasks. All backend operations are queued through the task system for asynchronous execution.

## Development Guidelines

### Entity-First Architecture
1. **Always use the entity layer** for data operations
2. Entities automatically sync to Provision contexts via `ContextRegistry`
3. Never directly manipulate Provision contexts from forms or controllers
4. Use manager services for business logic, keeping forms thin

### Service Layer Pattern
- Business logic belongs in manager services (SiteManager, PlatformManager, etc.)
- Forms should delegate validation and processing to services
- Keep controllers focused on routing and rendering
- Use dependency injection for all services

### Context Naming Convention
- Context names are stored **without** the `@` prefix in entities
- The `@` prefix is only used when invoking Drush commands
- Example: Store `example.com`, invoke as `@example.com`

### Common Anti-Patterns to Avoid
```php
// ❌ WRONG: Direct context manipulation
$contextRepo->save($context->set('domain', $value));

// ✅ CORRECT: Use entity API
$entity->set('domain', $value);
$entity->save();  // Triggers context sync

// ❌ WRONG: Business logic in forms
public function validateForm() {
  if (!filter_var($domain, FILTER_VALIDATE_DOMAIN)) { ... }
}

// ✅ CORRECT: Delegate to manager service
public function validateForm() {
  if (!$this->siteManager->isDomainValid($domain)) { ... }
}
```

## Additional Documentation
- [Hosting D11 Architecture](hosting-d11.md): Complete system architecture document
- [Hosting D7 Reference](hosting-d7.md): Drupal 7 legacy reference

## Project Links
- **Homepage**: https://aegir.hu/
- **Source**: https://github.com/argopecten/aegir-hosting
- **Issues**: https://github.com/argopecten/aegir-hosting/issues
- **Wiki**: https://github.com/argopecten/aegir-hosting/wiki
