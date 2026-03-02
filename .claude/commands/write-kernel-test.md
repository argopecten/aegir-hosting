# Write a Kernel Test

Write a Drupal kernel test for service logic, entity operations, or queue behavior in aegir-hosting.

## Existing Tests (Reference First)

- `hosting_task/tests/src/Kernel/HostingTaskKernelTest.php`
- `tests/src/Kernel/QueueInstallSyncTest.php`

## Steps

### 1. Create Test Class

**For module-specific tests**: `{module}/tests/src/Kernel/{Name}KernelTest.php`
**For cross-module tests**: `tests/src/Kernel/{Name}KernelTest.php`

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
        'hosting_task',
        'hosting_server',
        // Add all required modules for the feature under test
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installEntitySchema('hosting_context');
        $this->installEntitySchema('hosting_server');
        $this->installEntitySchema('hosting_task');
        $this->installEntitySchema('hosting_task_log');
        // Install schemas for all entities you test
        $this->installConfig(['hosting', 'hosting_task']);
    }

    public function testSomething(): void
    {
        // Arrange
        $server = \Drupal::entityTypeManager()
            ->getStorage('hosting_server')
            ->create(['title' => 'Test Server', 'status' => 1]);
        $server->save();

        // Act
        $loaded = \Drupal::entityTypeManager()
            ->getStorage('hosting_server')
            ->load($server->id());

        // Assert
        $this->assertNotNull($loaded);
        $this->assertEquals('Test Server', $loaded->label());
    }
}
```

### 2. Common Test Patterns

**Testing a service**:
```php
public function testServiceDoesX(): void
{
    $service = $this->container->get('hosting_site.site_manager');
    $result = $service->doX($entity);
    $this->assertSomething($result);
}
```

**Testing entity lifecycle (hooks)**:
```php
public function testEntityInsertTriggersContextSync(): void
{
    $server = HostingServer::create(['title' => 'web1']);
    $server->save();
    // Verify side effects
    $context = \Drupal::entityTypeManager()
        ->getStorage('hosting_context')
        ->loadByProperties(['context_name' => 'server_web1']);
    $this->assertNotEmpty($context);
}
```

**Testing the task queue**:
```php
public function testTaskCreation(): void
{
    $taskManager = $this->container->get('hosting.task_manager');
    $task = $taskManager->createTask($entity, 'verify');
    $this->assertEquals('queued', $task->get('task_status')->value);
    // Check queue item was added
    $queue = \Drupal::queue('hosting_task_queue');
    $this->assertEquals(1, $queue->numberOfItems());
}
```

### 3. Run Tests

```bash
# Run all hosting kernel tests
cd web && ../vendor/bin/phpunit -c core modules/contrib/aegir-hosting/tests/

# Run a specific test file
cd web && ../vendor/bin/phpunit -c core modules/contrib/aegir-hosting/hosting_task/tests/src/Kernel/HostingTaskKernelTest.php

# Run a specific test method
cd web && ../vendor/bin/phpunit -c core --filter testSomething modules/contrib/aegir-hosting/

# With verbose output
cd web && ../vendor/bin/phpunit -c core -v modules/contrib/aegir-hosting/
```

## Module Install Order in Tests

Follow the enable order:
```php
protected static $modules = [
    'system',
    'user',
    'hosting',          // Base module first
    'hosting_task',     // Then hosting_task
    'hosting_server',   // Then hosting_server
    'hosting_web_server',
    'hosting_db_server',
    'hosting_platform',
    'hosting_site',     // hosting_site last (depends on all above)
];
```

## Critical Gaps (Good Areas for New Tests)

- ContextRegistry sync (entity save → YAML file written)
- BackendInvoker command construction
- TaskAvailabilityResolver conditions
- Queue dispatch on entity save
- Task status transitions

Read existing kernel tests before implementing new ones to match the testing style.
