# Create a Task Operation Form

Create a new background operation that users trigger via the UI (e.g., site rename, platform upgrade). This covers the full flow: form → task → queue → backend.

## Architecture Reminder

ALL operations must flow: **Form → Entity → TaskManager → Queue → BackendInvoker → provision:command**

NEVER call provision commands directly from forms.

## User Input Needed

Ask the user for:
1. Operation name (e.g., `rename`, `upgrade`)
2. Entity type it operates on (`hosting_site`, `hosting_platform`, `hosting_server`)
3. Task type string (must match the `provision:*` command — e.g., `rename` for `provision:rename`)
4. Any preconditions (e.g., site must be enabled, platform must be locked)

## Steps

### 1. Create Form Class

Path: `{module}/src/Form/{Entity}{Operation}Form.php`

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Form;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hosting_task\Form\HostingTaskConfirmFormBase;

final class {Entity}{Operation}Form extends HostingTaskConfirmFormBase
{
    public function getFormId(): string
    {
        return 'hosting_{module}_{operation}_form';
    }

    public function getQuestion(): TranslatableMarkup
    {
        return new TranslatableMarkup('Are you sure you want to {operation} %entity?', [
            '%entity' => $this->getEntity()->label(),
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
}
```

The base form handles: task creation, queue dispatch, success message, and redirect.

### 2. Add Route

In `{module}/{module}.routing.yml`:
```yaml
{module}.task.{operation}:
  path: '/hosting/{entity_type_plural}/{entity_param}/{operation}'
  defaults:
    _form: '\Drupal\{module}\Form\{Entity}{Operation}Form'
    _title: '{Operation} {Entity}'
  requirements:
    _permission: 'administer hosting'
    {entity_param}: \d+
```

> Route naming: `{module}.task.{operation}`. Path: `/hosting/{type_plural}/{entity_param}/{operation}`. Always add `\d+` validation for entity ID params.

### 3. Add Task Availability (if conditional)

In `TaskAvailabilityResolver` if the operation has preconditions:
```php
// Example: operation only available for enabled sites
if ($taskType === '{operation}' && $entity->get('status')->value !== 'enabled') {
    return FALSE;
}
```

### 4. Ensure Backend Command Exists

The task type must match a `provision:{operation}` command in aegir-provision. Check `vendor/argopecten/aegir-provision/src/Drush/Commands/`. If missing, create it (see cross-repo workflow).

### 5. Verify the Flow

```bash
# Clear cache after adding routes/forms
drush cr

# Trigger operation directly for testing
drush php:eval "\Drupal::service('hosting.task_manager')->createTask(\Drupal::entityTypeManager()->getStorage('{entity_type}')->load(1), '{operation}');"

# Run queue
drush queue:run hosting_task_queue

# Check task logs
drush sql:query "SELECT * FROM hosting_task_log ORDER BY id DESC LIMIT 20"
```

## Task States

| State | Meaning |
|-------|---------|
| `queued` | Created, waiting for cron |
| `processing` | Currently executing |
| `success` | Completed successfully |
| `error` | Failed |
| `warning` | Completed with warnings |

## Form Hierarchy Reference

```
ConfirmFormBase (Drupal Core)
    └── HostingTaskConfirmFormBase (hosting_task)
            ├── SiteVerifyForm, SiteBackupForm, SiteRestoreForm
            ├── SiteCloneForm, SiteDisableForm, SiteEnableForm
            ├── SiteMigrateForm, SiteDeleteTaskForm, SiteResetPasswordForm
            ├── PlatformVerifyForm, PlatformDeleteTaskForm
            ├── PlatformLockForm, PlatformUnlockForm, PlatformMigrateForm
            └── ServerVerifyForm
```

Read existing task forms (e.g., `hosting_site/src/Form/SiteVerifyForm.php`) before implementing.
