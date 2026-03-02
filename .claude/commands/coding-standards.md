# Aegir Hosting Coding Standards

Reference for coding standards in the aegir-hosting frontend modules.

## PHP Rules

- `declare(strict_types=1);` on its own line immediately after `<?php` in every PHP file
- Blank line between `<?php`, `declare()`, and `namespace` — follow PSR-12
- All new classes `final` unless they are base classes designed for extension
- PHP 8.3+ features: typed properties, named arguments, constructor promotion, `match`, `readonly`
- All method parameters and return types must be explicitly typed (no `mixed` unless unavoidable)

## Drupal 11 Patterns

### Entity Attributes (NOT docblock annotations)

```php
// ✅ Drupal 11 — PHP 8 attribute
#[ContentEntityType(
    id: 'hosting_site',
    label: new TranslatableMarkup('Hosting Site'),
    admin_permission: 'administer hosting',
    handlers: [
        'route_provider' => ['html' => AdminHtmlRouteProvider::class],
    ],
    ...
)]
final class HostingSite extends ContentEntityBase { }

// ❌ Old annotation style — do NOT use
/**
 * @ContentEntityType(
 *   id = "hosting_site",
 *   ...
 * )
 */
```

### Hook Attributes (NOT procedural hooks)

```php
// ✅ Drupal 11 — Hook class in src/Hook/{Module}Hooks.php
namespace Drupal\hosting_site\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Entity\EntityInterface;

final class HostingSiteHooks
{
    #[Hook('entity_insert')]
    public function entityInsert(EntityInterface $entity): void { }

    #[Hook('hosting_site_presave')]
    public function hostingSitepresave(EntityInterface $entity): void { }
}

// ❌ Old procedural hook — do NOT use
function hosting_site_entity_insert(EntityInterface $entity): void { }
```

Hook classes are auto-discovered — no registration needed.

### Dependency Injection

```php
// ✅ Constructor DI — in services, forms, controllers
final class SiteManager
{
    public function __construct(
        private readonly EntityTypeManagerInterface $entityTypeManager,
        private readonly TaskManager $taskManager,
    ) {}
}

// ✅ Acceptable — in .module procedural code only
\Drupal::service('hosting.task_manager')->createTask($entity, 'verify');

// ❌ Never — in service/entity/form classes
\Drupal::service('hosting.task_manager');
```

### Service Definitions

```yaml
# {module}/{module}.services.yml
services:
  hosting_site.site_manager:
    class: Drupal\hosting_site\Service\SiteManager
    arguments:
      - '@entity_type.manager'
      - '@hosting.task_manager'
    # autowire: true  # supported in Drupal 11
```

## Naming Conventions

| What | Convention | Example |
|------|-----------|---------|
| Entity machine names | `hosting_{name}` | `hosting_site` |
| Module machine names | `hosting_{name}` | `hosting_site` |
| Services | `{module}.{snake_case}` | `hosting.context_registry` |
| Form IDs | `hosting_{module}_{operation}_form` | `hosting_site_verify_form` |
| Task routes | `{module}.task.{operation}` | `hosting_site.task.verify` |
| Route paths | `/hosting/{type_plural}/{param}/{op}` | `/hosting/sites/{hosting_site}/verify` |
| Hook classes | `src/Hook/{Module}Hooks.php` | `src/Hook/HostingSiteHooks.php` |

## `use` Statement Ordering (PSR-12)

```php
use Drupal\Core\Entity\Attribute\ContentEntityType;   // Core first
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hosting_task\Service\TaskManager;           // Then contrib/custom
```

## Entity Checklist

Every entity requires:
- [ ] `admin_permission` in `#[ContentEntityType]` attribute
- [ ] `route_provider` handler (`AdminHtmlRouteProvider::class`)
- [ ] `{module}.permissions.yml` defining access permissions
- [ ] `\d+` on entity ID params in route requirements

## What to Check Before Every Commit

1. `declare(strict_types=1);` present in every new PHP file
2. All new classes are `final`
3. All type hints present on parameters and return types
4. No procedural `hook_*()` functions — use `#[Hook]` classes
5. No `\Drupal::service()` inside classes — use constructor DI
6. Entity param `\d+` in routing requirements
7. Permissions defined in `{module}.permissions.yml`
8. No update hooks added

## Task Form Rules

- All task operation forms extend `HostingTaskConfirmFormBase`
- Business logic belongs in Manager services — forms only delegate
- Form ID format: `hosting_{module}_{operation}_form`
- Route name format: `{module}.task.{operation}`

Display the relevant coding standards for the code the user is working on.
