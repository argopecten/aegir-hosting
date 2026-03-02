# Add a Service Class

Create a new Drupal service for business logic in aegir-hosting. Services encapsulate business logic and are injected via DI.

## Rules

- Business logic belongs in Manager services, NOT in forms or entities
- Never use `\Drupal::service()` inside service classes — inject via constructor
- Service ID format: `{module}.{snake_case_name}` (e.g., `hosting_site.backup_manager`)
- One concern per service

## Steps

### 1. Create Service Class

Path: `{module}/src/Service/{ServiceName}.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

final class {ServiceName}
{
    use StringTranslationTrait;

    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        // Add other injected dependencies
    ) {}

    public function doSomething(): void
    {
        // Implementation
    }
}
```

### 2. Register in Services File

In `{module}/{module}.services.yml`:
```yaml
services:
  {module}.{service_name}:
    class: Drupal\{module}\Service\{ServiceName}
    arguments:
      - '@entity_type.manager'
      # - '@string_translation'  # if using StringTranslationTrait
      # - '@hosting.task_manager'  # if creating tasks
```

> Drupal 11 supports `autowire: true` as an alternative to explicit arguments.

### 3. Inject into Consumers

In forms and controllers — inject via constructor:
```php
public function __construct(
    private readonly {ServiceName} $serviceName,
) {}

public static function create(ContainerInterface $container): static
{
    return new static(
        $container->get('{module}.{service_name}'),
    );
}
```

In `.module` procedural code (only acceptable place for `\Drupal::service()`):
```php
\Drupal::service('{module}.{service_name}')->doSomething();
```

## Common Service Patterns

### Manager Service (CRUD + operations)
```php
final class SiteManager
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly TaskManager $taskManager,
    ) {}

    public function createSite(array $values): HostingSite
    {
        $site = $this->entityTypeManager->getStorage('hosting_site')->create($values);
        $site->save();
        return $site;
    }
}
```

### Resolver Service (calculations, lookups)
```php
final class TaskAvailabilityResolver
{
    public function isAvailable(ContentEntityInterface $entity, string $taskType): bool
    {
        // Logic to determine if task can be run
        return TRUE;
    }
}
```

### Builder Service (assembles render arrays)
```php
final class TaskLogBuilder
{
    public function build(HostingTask $task): array
    {
        return [
            '#theme' => 'hosting_task_queue',
            '#task' => $task,
        ];
    }
}
```

## Service Naming Convention

| Module | Service | Class |
|--------|---------|-------|
| `hosting` | `hosting.task_manager` | `TaskManager` |
| `hosting_site` | `hosting_site.site_manager` | `SiteManager` |
| `hosting_server` | `hosting_server.server_manager` | `ServerManager` |
| `hosting_platform` | `hosting_platform.platform_manager` | `PlatformManager` |

Read existing services (e.g., `hosting_site/src/Service/SiteManager.php`) before implementing.
