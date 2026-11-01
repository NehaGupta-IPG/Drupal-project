<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Components;

use Drupal\Core\Render\Component\Exception\IncompatibleComponentSchema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests invalid render options for components.
 */
#[Group('sdc')]
#[RunTestsInSeparateProcesses]
class ComponentInvalidReplacementTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['sdc_test_replacements_invalid'];

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['sdc_theme_test'];

  /**
   * Ensure that component replacement validates the schema compatibility.
   */
  public function testInvalidDefinitionTheme(): void {
    try {
      $this->manager->getDefinitions();
      $this->fail('Expected exception not thrown.');
    }
    catch (IncompatibleComponentSchema $e) {
      // Errors for all the invalid replacements are reported.
      $this->assertStringContainsString('"sdc_test_replacements_invalid:foo" is incompatible with the component is wants to replace "sdc_theme_test:foo"', $e->getMessage());
      // A component without props cannot be replaced by a component adding
      // required props.
      $this->assertStringContainsString('"sdc_test_replacements_invalid:bar" is incompatible with the component is wants to replace "sdc_theme_test:bar"', $e->getMessage());
      $this->assertStringContainsString('Some of the new required properties are not allowed by the original schema: [heading].', $e->getMessage());
    }
  }

}
