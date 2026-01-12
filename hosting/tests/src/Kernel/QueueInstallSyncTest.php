<?php

namespace Drupal\Tests\hosting\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that queue definitions sync into config on module install.
 */
class QueueInstallSyncTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'hosting'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['hosting']);
  }

  public function testQueueDefinitionsSyncOnInstall(): void {
    $config = $this->config('hosting.settings');
    $this->assertArrayNotHasKey('test_queue', $config->get('queues') ?? []);

    $this->container->get('module_installer')->install(['hosting_queue_test']);

    $queues = $this->config('hosting.settings')->get('queues');
    $this->assertArrayHasKey('test_queue', $queues);
    $this->assertSame('Test queue', $queues['test_queue']['label']);
    $this->assertTrue($queues['test_queue']['enabled']);
    $this->assertSame('serial', $queues['test_queue']['type']);
  }

}
