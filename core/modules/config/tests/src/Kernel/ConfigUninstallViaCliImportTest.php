<?php

declare(strict_types=1);

namespace Drupal\Tests\config\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests importing configuration from files into active configuration.
 */
#[Group('config')]
#[RunTestsInSeparateProcesses]
class ConfigUninstallViaCliImportTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'config'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['system']);
    $this->copyConfig($this->container->get('config.storage'), $this->container->get('config.storage.sync'));
  }

  /**
   * Tests that the config module can be uninstalled via CLI config import.
   *
   * @see \Drupal\config\ConfigSubscriber
   */
  public function testConfigUninstallViaCli(): void {
    $this->assertTrue($this->container->get('module_handler')->moduleExists('config'));
    $sync = $this->container->get('config.storage.sync');
    $extensions = $sync->read('core.extension');
    unset($extensions['module']['config']);
    $sync->write('core.extension', $extensions);
    $this->configImporter()->import();
    $this->assertFalse($this->container->get('module_handler')->moduleExists('config'));
  }

}
