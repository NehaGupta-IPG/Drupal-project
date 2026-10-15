<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use Drupal\Core\Render\Component\Exception\ComponentNotFoundException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the component plugin manager.
 */
#[Group('sdc')]
#[RunTestsInSeparateProcesses]
class ComponentPluginManagerTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'sdc_test', 'sdc_test_replacements'];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_theme_test'];

  /**
   * Test that components render correctly.
   */
  public function testFindEmptyMetadataFile(): void {
    // Test that empty component metadata files are valid, since there is no
    // required property.
    $this->assertNotEmpty(
      $this->manager->find('sdc_theme_test:bar'),
    );
    // Test that if the folder name does not match the machine name, the
    // component is still available.
    $this->assertNotEmpty(
      $this->manager->find('sdc_theme_test:foo'),
    );
  }

  /**
   * Test that the machine name is grabbed from the *.component.yml.
   *
   * And not from the enclosing directory.
   */
  public function testMismatchingFolderName(): void {
    $this->expectException(ComponentNotFoundException::class);
    $this->manager->find('sdc_theme_test:mismatching-folder-name');
  }

  /**
   * Tests that component definitions can be filtered per consumer.
   */
  public function testGetFilteredDefinitions(): void {
    $definitions = $this->manager->getDefinitions();
    $this->assertArrayHasKey('sdc_test:my-banner', $definitions);

    // The "Banner" component is removed for the sdc_test consumer.
    // @see \Drupal\sdc_test\Hook\SdcTestHooks::pluginFilterComponentSdcTestAlter()
    unset($definitions['sdc_test:my-banner']);
    $this->assertSame(array_keys($definitions), array_keys($this->manager->getFilteredDefinitions('sdc_test')));

    // The original definitions are unchanged.
    $this->assertArrayHasKey('sdc_test:my-banner', $this->manager->getDefinitions());
  }

}
