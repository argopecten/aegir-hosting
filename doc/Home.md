# Hosting (Drupal 11)

Welcome to the Aegir Hosting module documentation. This is the Drupal 11 Hosting/Hostmaster frontend module providing a complete hosting management system with entity-based architecture, service-oriented design, and Drush 13 integration.

## Architecture

### Core Services
- **FeatureManager**: Feature discovery, enabling/disabling, and permission management
- **QueueDispatcher**: Schedule-based queue execution with cron integration
- **QueueRunner**: Queue worker execution and management
- **BackendInvoker**: Drush command execution for aegir-provision backend
- **ContextRegistry**: Context entity management and path alias creation
- **Logger**: Dedicated `hosting` logging channel

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

## Additional Documentation
- [Hosting D11 Architecture](hosting-d11.md): Complete system architecture document
- [Hosting D7 Reference](hosting-d7.md): Drupal 7 legacy reference

## Project Links
- **Homepage**: https://aegir.hu/
- **Source**: https://github.com/argopecten/aegir-hosting
- **Issues**: https://github.com/argopecten/aegir-hosting/issues
- **Wiki**: https://github.com/argopecten/aegir-hosting/wiki
