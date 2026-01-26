# Hosting (Drupal 11)

The Drupal 11 Hosting/Hostmaster frontend module provides a complete hosting management system with entity-based architecture, service-oriented design, and Drush 13 integration.

## Overview

Aegir Hosting is the Drupal 11 frontend for Aegir Hostmaster, providing the entity layer, forms, and task management system for the hosting platform. It defines the canonical data model where all hosting operations originate through Drupal entities that sync to backend Provision contexts.

**Key Principle**: All data operations go through Drupal entities. The system maintains a bidirectional mapping between Drupal entities and Provision contexts via the ContextRegistry service.

## Architecture

### Entity ↔ Context Mapping

The hosting system maintains a bidirectional mapping between Drupal entities and Provision contexts:

```
HostingSite (entity) ↔ @example.com (context)
HostingPlatform (entity) ↔ @platform_d11 (context)
HostingServer (entity) ↔ @server_master (context)
```

The `HostingContext` entity serves as the registry, linking Drupal entities to Provision context names and creating path aliases at `/hosting/c/{context_name}`.

### Core Services
- **ContextRegistry**: Entity ↔ Context synchronization and registry management
- **FeatureManager**: Feature discovery, enabling/disabling, and permission management
- **QueueDispatcher**: Schedule-based queue execution with cron integration
- **QueueRunner**: Queue worker execution and management
- **BackendInvoker**: Drush command execution for aegir-provision backend
- **Logger**: Dedicated `hosting` logging channel

### Manager Services
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

The BackendInvoker service executes Drush provision commands from PHP, translating entity operations into backend tasks. All backend operations are queued through the task system for asynchronous execution.

## Development Guidelines

### Entity-First Architecture
- **Always use the entity layer** for data operations
- Entities automatically sync to Provision contexts via `ContextRegistry`
- Never directly manipulate Provision contexts from forms or controllers
- Use manager services for business logic, keeping forms thin

### Service Layer Pattern
- Business logic belongs in manager services (SiteManager, PlatformManager, etc.)
- Forms should delegate validation and processing to services
- Keep controllers focused on routing and rendering
- Use dependency injection for all services

### Context Naming
- Context names are stored **without** the `@` prefix in entities
- The `@` prefix is only used when invoking Drush commands
- Example: Store `example.com`, invoke as `@example.com`

## Contributing

When contributing to this project:
- Follow Drupal coding standards
- Use entity API for all data operations
- Implement manager services for business logic
- Write comprehensive tests for new features
- Document all public APIs and services

## Documentation
See `/doc/` directory for detailed architecture documentation:
- `Home.md`: Wiki landing page
- `hosting-d11.md`: Complete system architecture document
- `hosting-d7.md`: Drupal 7 legacy reference
