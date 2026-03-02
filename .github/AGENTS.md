# AI Agent Guide — aegir-hosting (Frontend)

> **Repository**: Drupal 11 hosting automation modules
> **Local Path**: `web/modules/contrib/aegir-hosting/`
> **GitHub**: https://github.com/argopecten/aegir-hosting

## You Are Here

This is the **Frontend Component** — a collection of Drupal modules. It IS a Drupal module, so it uses Drupal APIs: entities, forms, services, hooks, routing, config, and database abstraction.

**This component handles**:
- ✅ Content entities (Server, Platform, Site, Task, Client, Package, Backup, etc.)
- ✅ Forms and validation (25 form classes)
- ✅ Task queue system (create → queue → dispatch → log)
- ✅ ContextRegistry (entity ↔ Drush alias sync)
- ✅ BackendInvoker (executes `provision:*` commands)
- ✅ Service plugin system (service types + providers)
- ❌ Infrastructure automation (→ aegir-provision)
- ❌ UI presentation (→ aegir-eldir)

## Architecture

```
User Action (form submit)
    ↓
Form → Entity save → Task creation (HostingTask entity)
    ↓
QueueDispatcher → Drupal Queue API
    ↓
HostingTaskQueueWorker (cron-driven)
    ↓
BackendInvoker → shell exec: drush provision:{command} @context
    ↓
TaskLogManager → HostingTaskLog entities
```

## Sub-Modules (8 + 1 test)

| Module | Purpose | Key Entities |
|--------|---------|-------------|
| `hosting` | Base module, settings, dashboard, sidebar | `HostingContext` |
| `hosting_server` | Server entity + service plugin system | `HostingServer`, `HostingServiceInstance` |
| `hosting_platform` | Platform entity + management | `HostingPlatform` |
| `hosting_site` | Site entity + all site operations | `HostingSite`, `HostingSiteBackup` |
| `hosting_task` | Task entity + queue + execution | `HostingTask`, `HostingTaskLog` |
| `hosting_client` | Multi-tenant client entity + access | `HostingClient`, `HostingClientUser`, `HostingPlatformAccess` |
| `hosting_package` | Package tracking across platforms | `HostingPackage`, `HostingPackageInstance` |
| `hosting_db_server` | Database server service plugin | — (uses service plugins) |
| `hosting_web_server` | Web server service plugin | — (uses service plugins) |

### Enable Order (Critical)

```
hosting → hosting_server → hosting_web_server → hosting_db_server
       → hosting_platform → hosting_site → hosting_task
       → hosting_client → hosting_package
```

## Content Entities (13)

| Entity | Module | Machine Name | Has ListBuilder | Has ViewBuilder |
|--------|--------|-------------|-----------------|-----------------|
| `HostingContext` | hosting | `hosting_context` | — | — |
| `HostingServer` | hosting_server | `hosting_server` | ✅ | ✅ |
| `HostingServiceInstance` | hosting_server | `hosting_service_instance` | — | — |
| `HostingPlatform` | hosting_platform | `hosting_platform` | ✅ | ✅ |
| `HostingSite` | hosting_site | `hosting_site` | ✅ | ✅ |
| `HostingSiteBackup` | hosting_site | `hosting_site_backup` | — | — |
| `HostingTask` | hosting_task | `hosting_task` | ✅ | ✅ |
| `HostingTaskLog` | hosting_task | `hosting_task_log` | — | — |
| `HostingClient` | hosting_client | `hosting_client` | ✅ | ✅ |
| `HostingClientUser` | hosting_client | `hosting_client_user` | — | — |
| `HostingPlatformAccess` | hosting_client | `hosting_platform_access` | — | — |
| `HostingPackage` | hosting_package | `hosting_package` | — | — |
| `HostingPackageInstance` | hosting_package | `hosting_package_instance` | — | — |

## Core Services (26)

### Base Module (`hosting.services.yml`)

| Service | Class | Purpose |
|---------|-------|---------|
| `hosting.context_registry` | `ContextRegistry` | Sync entities ↔ Drush YAML aliases |
| `hosting.backend_invoker` | `BackendInvoker` | Shell-execute `drush provision:*` commands |
| `hosting.queue_dispatcher` | `QueueDispatcher` | Enqueue tasks for cron processing |
| `hosting.queue_runner` | `QueueRunner` | Process queued tasks |
| `hosting.feature_manager` | `FeatureManager` | Enable/disable optional features |
| `hosting.sidebar_builder` | `HostingSidebarBuilder` | Build contextual sidebar |
| `hosting.icon_provider` | `HostingIconProvider` | SVG icon sprite URL (theme-agnostic) |

### Sub-Module Services

| Module | Service | Purpose |
|--------|---------|---------|
| hosting_server | `ServerManager` | Server CRUD + verify |
| hosting_server | `IpManager` | IP address management |
| hosting_server | `ServiceManager` | Service instance CRUD |
| hosting_platform | `PlatformManager` | Platform CRUD + verify |
| hosting_site | `SiteManager` | Site CRUD + all operations |
| hosting_site | `BackupManager` | Backup/restore coordination |
| hosting_site | `DomainValidator` | URI validation |
| hosting_task | `TaskManager` | Task lifecycle |
| hosting_task | `TaskLogManager` | Log capture + storage |
| hosting_task | `TaskLogBuilder` | Log rendering |
| hosting_task | `TaskAvailabilityResolver` | Available task resolution |
| hosting_client | `ClientManager` | Client CRUD |
| hosting_client | `ClientAccessManager` | Access control |
| hosting_package | `PackageDiscovery` | Discover packages on platforms |
| hosting_package | `PackageComparison` | Cross-platform comparison |
| hosting_package | `PackageInstanceSync` | Sync package data |
| hosting_package | `PackageVersionParser` | Semantic version parsing |
| hosting_db_server | `DbCredentialsManager` | Database credential management |
| hosting_db_server | `DbServerResolver` | Resolve DB server for site |

## Plugin System

Two custom plugin types for extensible service management:

### HostingServiceType (What service categories exist)

- **Manager**: `HostingServiceTypeManager`
- **Interface**: `HostingServiceTypeInterface`
- **Base**: `HostingServiceTypeBase`
- **Implementations**: `HttpServiceType`, `DbServiceType`

### HostingServiceProvider (What implementations exist per type)

- **Manager**: `HostingServiceProviderManager`
- **Interface**: `HostingServiceProviderInterface`
- **Base**: `HostingServiceProviderBase`
- **Implementations**: `ApacheServiceProvider`, `MysqlServiceProvider`

## Task Queue Flow

1. **Task creation**: `TaskManager::createTask($entity, $taskType)` → creates `HostingTask` entity in `queued` state
2. **Queue dispatch**: `QueueDispatcher` adds task ID to Drupal queue `hosting_task_queue`
3. **Cron processing**: `HostingTaskQueueWorker::processItem()` dequeues and executes
4. **Backend execution**: `BackendInvoker::invoke($command, $context)` → shell exec `drush provision:{command} @{context}`
5. **Log capture**: stdout/stderr → `HostingTaskLog` entities via `TaskLogManager`
6. **Status update**: Task entity updated to `success`, `error`, or `warning`

### Task States

`queued` → `processing` → `success` | `error` | `warning`

## ContextRegistry (Entity ↔ Context Sync)

`ContextRegistry` is the bridge between Drupal entities and Drush YAML aliases:

- **Entity → Context**: When a server/platform/site entity is saved, `ContextRegistry` writes a YAML alias to `drush/sites/aegir/`
- **Context → Entity**: On import, reads YAML and creates/updates entities
- Uses the `provision:save` command format for YAML structure

## Forms (25)

### Entity Forms (CRUD)
`HostingServerForm`, `HostingPlatformForm`, `HostingSiteForm`, `HostingClientForm`, `HostingServiceInstanceForm`, `HostingClientSettingsForm`

### Task Operation Forms (confirm + execute)
`ServerVerifyForm`, `PlatformVerifyForm`, `PlatformDeleteTaskForm`, `PlatformLockForm`, `PlatformUnlockForm`, `PlatformMigrateForm`, `SiteVerifyForm`, `SiteBackupForm`, `SiteRestoreForm`, `SiteCloneForm`, `SiteDeleteTaskForm`, `SiteDisableForm`, `SiteEnableForm`, `SiteMigrateForm`, `SiteResetPasswordForm`

### Settings/Admin Forms
`HostingSettingsForm`, `HostingFeaturesForm`, `HostingQueuesForm`

### Base Class
`HostingTaskConfirmFormBase` — parent for all task operation forms

## Templates (6)

| Template | Purpose |
|----------|---------|
| `hosting-dashboard.html.twig` | Main dashboard page |
| `hosting-navigation.html.twig` | Navigation elements |
| `hosting-sidebar.html.twig` | Contextual sidebar |
| `hosting-info-table.html.twig` | Entity info display |
| `hosting-queues-table.html.twig` | Queue status display |
| `hosting-task-queue.html.twig` | Task queue display |

## Development Rules

1. **All classes `final`** unless they're base classes for extension
2. **Breaking changes allowed** — no update hooks, no backward compatibility
3. **Modern PHP 8.3+** — typed properties, constructor promotion, attributes
4. **Drupal 11 APIs only** — no deprecated D9/D10 apis
5. **Services via DI** — inject services in constructors, declare in `*.services.yml`
6. **Entity annotations** → PHP 8 attributes (Drupal 11 style)
7. **Forms extend proper base** — task forms extend `HostingTaskConfirmFormBase`
8. **ContextRegistry sync** — always update YAML aliases when entities change

## Known Issues

- **Entity status inconsistency**: Task state stored as string field but validated inconsistently across modules
- **Only 2 kernel tests** — `HostingTaskKernelTest` + `QueueInstallSyncTest`. No functional or unit tests. Critical gap.
- **No REST/JSON:API** exposure — entities not exposed via web APIs

## Statistics

| Metric | Count |
|--------|-------|
| Sub-modules | 8 (+1 test) |
| Content entities | 13 |
| Services | 26 |
| Forms | 25 |
| Plugins | 4 implementations |
| Plugin managers | 2 |
| Templates | 6 |
| Routing files | 6 |
| Hook modules | 9 |
| Kernel tests | 2 |
| CSS files | 1 |
| Config schemas | 1 |

## Related Components

| Component | Path | When to reference |
|-----------|------|-------------------|
| Provision (Backend) | `vendor/argopecten/aegir-provision/` | Command implementation, context format |
| Eldir (Theme) | `web/themes/contrib/aegir-eldir/` | Template rendering, CSS |
| Main repo | `.github/AGENTS.md` | Cross-component architecture |
