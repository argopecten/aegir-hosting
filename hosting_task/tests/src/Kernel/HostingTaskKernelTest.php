<?php

namespace Drupal\Tests\hosting_task\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Kernel tests for Hosting task entities.
 *
 * @group hosting_task
 */
class HostingTaskKernelTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'hosting',
    'hosting_task',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('hosting_task');
    $this->installEntitySchema('hosting_task_log');
    $this->installConfig(['hosting']);
  }

  public function testCreateTaskEntity(): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('hosting_task');
    $task = $storage->create([
      'label' => 'install example.com',
      'task_type' => 'install',
      'status' => 'queued',
      'command' => 'provision-install',
      'args' => json_encode([]),
      'options' => json_encode([]),
      'context_name' => 'example.com',
    ]);
    $task->save();

    $this->assertNotEmpty($task->id());
  }

}
