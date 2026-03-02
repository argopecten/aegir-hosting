# Add a Service Plugin

Add a new service type (e.g., DNS, CDN) or a new provider for an existing type (e.g., Nginx for HTTP) to the aegir-hosting plugin system.

## Plugin System Overview

Two custom plugin types for extensible service management:

| Plugin Type | Manager | Base Class | Purpose |
|-------------|---------|-----------|---------|
| `HostingServiceType` | `HostingServiceTypeManager` | `HostingServiceTypeBase` | Defines categories of services |
| `HostingServiceProvider` | `HostingServiceProviderManager` | `HostingServiceProviderBase` | Implements a service category |

Existing plugins:
| Service Type | Provider | Provision Interface |
|-------------|----------|-------------------|
| `http` (HttpServiceType) | `apache` (ApacheServiceProvider) | `HttpServiceInterface` |
| `db` (DbServiceType) | `mysql` (MysqlServiceProvider) | `DbServiceInterface` |

## Option A: Add a New Service Type

### 1. Create type plugin

Path: `{module}/src/Plugin/HostingServiceType/{Name}ServiceType.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\HostingServiceType;

use Drupal\hosting_server\Plugin\HostingServiceTypeBase;

/**
 * @HostingServiceType(
 *   id = "{name}",
 *   label = @Translation("{Name} Service"),
 *   description = @Translation("Description of the service type")
 * )
 */
final class {Name}ServiceType extends HostingServiceTypeBase {}
```

Plugin is auto-discovered by `HostingServiceTypeManager` via annotation.

### 2. Ensure corresponding provision service interface exists

The `service_type` id must correspond to a service interface in aegir-provision's `ServiceRegistry` (e.g., `{Name}ServiceInterface`).

## Option B: Add a New Service Provider

### 1. Create provider plugin

Path: `{module}/src/Plugin/HostingServiceProvider/{Name}ServiceProvider.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\HostingServiceProvider;

use Drupal\hosting_server\Plugin\HostingServiceProviderBase;

/**
 * @HostingServiceProvider(
 *   id = "{name}",
 *   label = @Translation("{Name}"),
 *   service_type = "{type_id}",
 *   description = @Translation("Description")
 * )
 */
final class {Name}ServiceProvider extends HostingServiceProviderBase {}
```

The `service_type` must match an existing `HostingServiceType` plugin ID (`http` or `db`, or a new custom type).

### 2. Create corresponding sub-module (if needed)

Convention: `hosting_{name}server/` for new server service types.

In `hosting_{name}server/hosting_{name}server.info.yml`:
```yaml
name: 'Hosting {Name} Server'
type: module
description: '{Name} service provider for Aegir hosting.'
package: Hosting
core_version_requirement: ^11
dependencies:
  - hosting:hosting_server
```

## Relationship to aegir-provision

Service plugins on the frontend (hosting) mirror service implementations on the backend (provision):

```
Frontend (aegir-hosting)          Backend (aegir-provision)
HostingServiceType: http     ↔    HttpServiceInterface
HostingServiceProvider: apache ↔  ApacheService implements HttpServiceInterface
```

When adding a new provider, the corresponding provision service implementation must exist or be created.

Read existing plugins (`hosting_web_server/`, `hosting_db_server/`) before implementing.
