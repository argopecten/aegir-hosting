# AI Skills — aegir-hosting (Frontend)

> Actionable instruction sets for performing specialized tasks in the hosting frontend modules.
> Each skill is a step-by-step procedure an AI agent can follow to completion.

---

## Skill 1: Create a New Content Entity

**When**: You need to add a new entity type (e.g., `HostingDomain`, `HostingProfile`).

### Steps

1. **Decide which sub-module** owns the entity. If none fits, create a new sub-module (see Skill 7).

2. **Create entity class** at `{module}/src/Entity/{EntityName}.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Drupal\{module}\Entity;

   use Drupal\Core\Entity\ContentEntityBase;
   use Drupal\Core\Entity\EntityTypeInterface;
   use Drupal\Core\Field\BaseFieldDefinition;
   use Drupal\Core\Entity\Attribute\ContentEntityType;
   use Drupal\Core\StringTranslation\TranslatableMarkup;

   #[ContentEntityType(
       id: '{entity_id}',
       label: new TranslatableMarkup('{Entity Label}'),
       base_table: '{entity_id}',
       entity_keys: [
           'id' => 'id',
           'label' => 'title',
           'uuid' => 'uuid',
       ],
       handlers: [
           'form' => [
               'default' => \Drupal\{module}\Form\{EntityName}Form::class,
               'delete' => \Drupal\Core\Entity\ContentEntityDeleteForm::class,
           ],
           'list_builder' => \Drupal\{module}\Entity\{EntityName}ListBuilder::class,
           'view_builder' => \Drupal\{module}\Entity\{EntityName}ViewBuilder::class,
           'access' => \Drupal\Core\Entity\EntityAccessControlHandler::class,
       ],
       links: [
           'canonical' => '/{entity_id}/{hosting_{entity_id}}',
           'add-form' => '/{entity_id}/add',
           'edit-form' => '/{entity_id}/{hosting_{entity_id}}/edit',
           'delete-form' => '/{entity_id}/{hosting_{entity_id}}/delete',
       ],
   )]
   final class {EntityName} extends ContentEntityBase
   {
       public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array
       {
           $fields = parent::baseFieldDefinitions($entity_type);

           $fields['title'] = BaseFieldDefinition::create('string')
               ->setLabel(new TranslatableMarkup('Title'))
               ->setRequired(TRUE)
               ->setSetting('max_length', 255);

           $fields['status'] = BaseFieldDefinition::create('string')
               ->setLabel(new TranslatableMarkup('Status'))
               ->setDefaultValue('active');

           return $fields;
       }
   }
   ```

3. **Create ListBuilder** at `{module}/src/Entity/{EntityName}ListBuilder.php`:
   ```php
   final class {EntityName}ListBuilder extends EntityListBuilder
   {
       public function buildHeader(): array { /* columns */ }
       public function buildRow(EntityInterface $entity): array { /* row data */ }
   }
   ```

4. **Create ViewBuilder** at `{module}/src/Entity/{EntityName}ViewBuilder.php`:
   ```php
   final class {EntityName}ViewBuilder extends EntityViewBuilder
   {
       public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL): array
       {
           $build = parent::view($entity, $view_mode, $langcode);
           // Add custom render arrays
           return $build;
       }
   }
   ```

5. **Add routing** in `{module}/{module}.routing.yml`:
   ```yaml
   entity.{entity_id}.canonical:
     path: '/{entity_id}/{hosting_{entity_id}}'
     defaults:
       _entity_view: 'hosting_{entity_id}.full'
       _title_callback: '\Drupal\Core\Entity\Controller\EntityController::title'
     requirements:
       _permission: 'access content'
   ```

6. **Install**: Run `drush entity:updates` or reinstall the module.

### Anti-patterns
- ❌ Do NOT use config entities for infrastructure data — always content entities
- ❌ Do NOT create entities without a `status` field
- ❌ Do NOT skip the ListBuilder — all entities need admin listing

---

## Skill 2: Create a Task Operation Form

**When**: You need a new operation that the user triggers via UI (e.g., site rename, platform upgrade).

### Steps

1. **Create form class** extending `HostingTaskConfirmFormBase`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Drupal\hosting_site\Form;

   use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;
   use Drupal\Core\Form\FormStateInterface;

   final class Site{Operation}Form extends HostingTaskConfirmFormBase
   {
       public function getFormId(): string
       {
           return 'hosting_site_{operation}_form';
       }

       public function getQuestion(): TranslatableMarkup
       {
           return new TranslatableMarkup('Are you sure you want to {operation} %site?', [
               '%site' => $this->entity->label(),
           ]);
       }

       public function getDescription(): TranslatableMarkup
       {
           return new TranslatableMarkup('This will {operation description}.');
       }

       protected function getTaskType(): string
       {
           return '{operation}';
       }

       public function buildForm(array $form, FormStateInterface $form_state): array
       {
           $form = parent::buildForm($form, $form_state);
           // Add additional form elements if needed
           return $form;
       }
   }
   ```

2. **Add route** in `hosting_site/hosting_site.routing.yml`:
   ```yaml
   hosting_site.{operation}:
     path: '/hosting_site/{hosting_site}/{operation}'
     defaults:
       _form: '\Drupal\hosting_site\Form\Site{Operation}Form'
       _title: '{Operation} Site'
     requirements:
       _permission: 'administer hosting site'
   ```

3. **Register task availability** — update `TaskAvailabilityResolver` if the operation has preconditions (e.g., site must be enabled).

4. **The base form handles**: task creation, queue dispatch, success message, and redirect. You only need to define the task type and any custom fields.

### Form Hierarchy

```
ConfirmFormBase (Drupal Core)
    └── HostingTaskConfirmFormBase (hosting_task)
            ├── SiteVerifyForm
            ├── SiteBackupForm
            ├── SiteRestoreForm
            ├── SiteCloneForm
            ├── SiteDisableForm
            ├── SiteEnableForm
            ├── SiteMigrateForm
            ├── SiteDeleteTaskForm
            ├── SiteResetPasswordForm
            ├── PlatformVerifyForm
            ├── PlatformDeleteTaskForm
            ├── PlatformLockForm
            ├── PlatformUnlockForm
            ├── PlatformMigrateForm
            └── ServerVerifyForm
```

---

## Skill 3: Add a Service Plugin

**When**: You need to add a new service type (e.g., DNS, CDN) or a new provider for an existing type (e.g., Nginx for HTTP).

### Adding a New Service Type

1. **Create type plugin** at `{module}/src/Plugin/HostingServiceType/{Name}ServiceType.php`:
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

2. **Register discovery**: Plugin is auto-discovered by `HostingServiceTypeManager`.

### Adding a New Service Provider

1. **Create provider plugin** at `{module}/src/Plugin/HostingServiceProvider/{Name}ServiceProvider.php`:
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

2. **The plugin system** maps to provision's `ServiceRegistry` — the `service_type` must match a provision service interface.

### Existing Plugin Map

| Service Type | Provider | Provision Interface |
|-------------|----------|-------------------|
| `http` (HttpServiceType) | `apache` (ApacheServiceProvider) | `HttpServiceInterface` |
| `db` (DbServiceType) | `mysql` (MysqlServiceProvider) | `DbServiceInterface` |

---

## Skill 4: Work with ContextRegistry

**When**: You need to sync Drupal entities with Drush YAML aliases.

### How ContextRegistry Works

`ContextRegistry` bridges Drupal entities and provision's YAML context system:

```
Entity Save → ContextRegistry::syncToContext($entity)
                → Build YAML data from entity fields
                → Write to drush/sites/aegir/{name}.site.yml
                → (Optionally) Run provision:save

Entity Load ← ContextRegistry::syncFromContext($alias)
                ← Read YAML from drush/sites/aegir/
                ← Map YAML fields to entity properties
                ← Create/update entity
```

### Syncing After Entity Changes

```php
// In your entity form submit handler or service:
$contextRegistry = \Drupal::service('hosting.context_registry');
$contextRegistry->syncToContext($entity);
```

### Importing from Context

```php
$contextRegistry = \Drupal::service('hosting.context_registry');
$entity = $contextRegistry->syncFromContext('@server_web2');
```

### YAML ↔ Entity Field Mapping

| YAML Key | Entity Field | Entity Type |
|----------|-------------|-------------|
| `provision.type: server` | — | `HostingServer` |
| `provision.ip` | `ip_addresses` | `HostingServer` |
| `provision.http_service` | ServiceInstance (`http`) | `HostingServer` |
| `provision.type: platform` | — | `HostingPlatform` |
| `provision.root` | `publish_path` | `HostingPlatform` |
| `provision.server` | `web_server` (entity ref) | `HostingPlatform` |
| `provision.type: site` | — | `HostingSite` |
| `provision.uri` | `title` | `HostingSite` |
| `provision.platform` | `platform` (entity ref) | `HostingSite` |
| `provision.db_server` | `db_server` (entity ref) | `HostingSite` |

---

## Skill 5: Create a New Service Class

**When**: You need business logic not tied to a specific entity but used across the module.

### Steps

1. **Create service class** at `{module}/src/Service/{Name}.php`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Drupal\{module}\Service;

   use Drupal\Core\Entity\EntityTypeManagerInterface;

   final class {Name}
   {
       public function __construct(
           private readonly EntityTypeManagerInterface $entityTypeManager,
       ) {}

       public function doSomething(): void
       {
           // Implementation
       }
   }
   ```

2. **Register in `{module}/{module}.services.yml`**:
   ```yaml
   services:
     {module}.{service_name}:
       class: Drupal\{module}\Service\{Name}
       arguments:
         - '@entity_type.manager'
   ```

3. **Inject into forms/controllers** via constructor DI or `\Drupal::service('{module}.{service_name}')`.

### Service Naming Convention

- Service ID: `{module}.{snake_case_name}` (e.g., `hosting_site.backup_manager`)
- Class name: PascalCase (e.g., `BackupManager`)
- One service per file, one concern per service

---

## Skill 6: Add a Task Queue Integration

**When**: A new operation needs background processing via the task queue.

### Steps

1. **Define the task type** — it must match a `provision:*` command name (e.g., `verify`, `install`, `backup`).

2. **Create the task** via `TaskManager`:
   ```php
   $taskManager = \Drupal::service('hosting_task.task_manager');
   $task = $taskManager->createTask($entity, 'verify');
   ```

3. **Queue dispatch happens automatically** — `TaskManager::createTask()` creates the entity and queues it.

4. **Execution flow**:
   - Cron runs → `HostingTaskQueueWorker::processItem()` picks up task
   - Sets task status to `processing`
   - `BackendInvoker::invoke('verify', $context)` executes the Drush command
   - Stdout/stderr captured → `TaskLogManager` creates `HostingTaskLog` entries
   - Task status updated to `success`, `error`, or `warning`

5. **Add availability conditions** in `TaskAvailabilityResolver` if the task has preconditions:
   ```php
   // Example: backup is only available for enabled sites
   if ($taskType === 'backup' && $entity->get('status')->value !== 'enabled') {
       return FALSE;
   }
   ```

### Task States

| State | Meaning |
|-------|---------|
| `queued` | Created, waiting for cron |
| `processing` | Currently executing |
| `success` | Completed successfully |
| `error` | Failed |
| `warning` | Completed with warnings |

---

## Skill 7: Create a New Sub-Module

**When**: You need a new feature area that doesn't fit in existing sub-modules.

### Steps

1. **Create directory**: `hosting_{name}/`

2. **Create info file** `hosting_{name}/hosting_{name}.info.yml`:
   ```yaml
   name: 'Hosting {Name}'
   type: module
   description: 'Description of the sub-module'
   package: Hosting
   core_version_requirement: ^11
   dependencies:
     - hosting:hosting
   ```

3. **Create module file** `hosting_{name}/hosting_{name}.module` (even if empty — needed for hook discovery):
   ```php
   <?php

   declare(strict_types=1);

   /**
    * @file
    * Hosting {Name} module.
    */
   ```

4. **Create services file** `hosting_{name}/hosting_{name}.services.yml`:
   ```yaml
   services:
     hosting_{name}.{service}:
       class: Drupal\hosting_{name}\Service\{ServiceClass}
       arguments: []
   ```

5. **Create routing file** `hosting_{name}/hosting_{name}.routing.yml` if the module has pages.

6. **Follow the enable order** — add dependency on `hosting` at minimum, plus any other sub-modules.

7. **Enable**: `drush en hosting_{name}`

---

## Skill 8: Write a Kernel Test

**When**: You need to test service logic, entity operations, or queue behavior.

### Steps

1. **Create test class** at `tests/src/Kernel/{Name}KernelTest.php` or `{module}/tests/src/Kernel/`:
   ```php
   <?php

   declare(strict_types=1);

   namespace Drupal\Tests\{module}\Kernel;

   use Drupal\KernelTests\KernelTestBase;

   final class {Name}KernelTest extends KernelTestBase
   {
       protected static $modules = [
           'system',
           'user',
           'hosting',
           'hosting_server',
           // Add required modules
       ];

       protected function setUp(): void
       {
           parent::setUp();
           $this->installEntitySchema('hosting_server');
           $this->installEntitySchema('hosting_context');
           // Install schemas for entities you test
       }

       public function testSomething(): void
       {
           // Arrange
           $entity = \Drupal::entityTypeManager()
               ->getStorage('hosting_server')
               ->create(['title' => 'Test Server']);
           $entity->save();

           // Act
           $loaded = \Drupal::entityTypeManager()
               ->getStorage('hosting_server')
               ->load($entity->id());

           // Assert
           $this->assertNotNull($loaded);
           $this->assertEquals('Test Server', $loaded->label());
       }
   }
   ```

2. **Run**: `cd web && ../vendor/bin/phpunit -c core modules/contrib/aegir-hosting/tests/`

3. **Existing tests** to follow as examples:
   - `hosting_task/tests/src/Kernel/HostingTaskKernelTest.php`
   - `tests/src/Kernel/QueueInstallSyncTest.php`

---

## Skill 9: Debug Entity-to-Backend Flow

**When**: An operation fails between entity save and backend execution.

### Diagnostic Steps

1. **Check task entity status**:
   ```php
   $task = \Drupal::entityTypeManager()
       ->getStorage('hosting_task')
       ->load($task_id);
   $status = $task->get('task_status')->value;
   ```

2. **Check task logs**:
   ```php
   $logs = \Drupal::entityTypeManager()
       ->getStorage('hosting_task_log')
       ->loadByProperties(['task_id' => $task_id]);
   ```

3. **Verify ContextRegistry sync** — check YAML file exists:
   ```bash
   cat drush/sites/aegir/{context_name}.site.yml
   ```

4. **Test backend directly** — bypass the queue:
   ```bash
   drush provision:verify @server_master --debug -vvv
   ```

5. **Check queue status**:
   ```bash
   drush queue:list | grep hosting
   drush queue:run hosting_task_queue
   ```

6. **Common failure points**:
   | Symptom | Cause | Fix |
   |---------|-------|-----|
   | Task stays `queued` | Cron not running | `drush cron` or check crontab |
   | Task goes to `error` | Backend command failed | Check task logs for stderr |
   | No YAML file | ContextRegistry didn't sync | Call `$contextRegistry->syncToContext($entity)` |
   | Wrong YAML | Entity fields don't map | Check field mapping in ContextRegistry |
   | "Command not found" | Provision not installed | `drush provision:verify @server_master` should work |

---

## Coding Standards

- PHP 8.3+ strict: `declare(strict_types=1);` in every file
- All classes `final` (except base classes meant for extension)
- Drupal 11 APIs: PHP 8 attributes for entities, typed properties
- Services via DI — never use `\Drupal::service()` in service/entity classes (only in procedural code)
- Entity machine names: `hosting_{name}` prefix always
- Module machine names: `hosting_{name}` prefix always
- Service IDs: `{module}.{snake_case}` format
- Form IDs: `hosting_{module}_{operation}_form`
- No update hooks — clean slate approach
- No backward compatibility concerns
