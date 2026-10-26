<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Entity;

use Drupal\KernelTests\KernelTestBase;
use Drupal\module_installer_config_test\Entity\TestConfigType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests third-party settings on the test_config_type config entity type.
 */
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
class TestConfigTypeThirdPartySettingsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['module_installer_config_test'];

  /**
   * Tests that third-party settings can be saved and loaded.
   */
  public function testThirdPartySettings(): void {
    $entity = TestConfigType::create([
      'id' => 'test',
      'name' => 'Test',
    ]);
    $entity->setThirdPartySetting('module_installer_config_test', 'foo', 'bar');
    // Saving fails with a schema error if the third-party settings are not
    // covered by config schema.
    $entity->save();

    $entity = TestConfigType::load('test');
    $this->assertSame(['module_installer_config_test'], $entity->getThirdPartyProviders());
    $this->assertSame('bar', $entity->getThirdPartySetting('module_installer_config_test', 'foo'));
  }

}
