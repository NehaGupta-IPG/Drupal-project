<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Config;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests config schema deprecation.
 */
#[Group('config')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class ConfigSchemaDeprecationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config_schema_deprecated_test',
    'system',
  ];

  /**
   * Tests config schema deprecation.
   */
  public function testConfigSchemaDeprecation(): void {
    $this->expectUserDeprecationMessage('The \'complex_structure_deprecated\' config schema is deprecated in drupal:9.1.0 and is removed from drupal 10.0.0. Use the \'complex_structure\' config schema instead. See http://drupal.org/node/the-change-notice-nid.');
    $config = $this->config('config_schema_deprecated_test.settings');
    $config
      ->set('complex_structure_deprecated.type', 'fruits')
      ->set('complex_structure_deprecated.products', ['apricot', 'apple'])
      ->save();
    $this->assertSame(['type' => 'fruits', 'products' => ['apricot', 'apple']], $config->get('complex_structure_deprecated'));
  }

  /**
   * Tests the country.default config schema deprecation.
   */
  public function testCountryDefaultConfigSchemaDeprecation(): void {
    $this->installConfig(['system']);
    $this->expectUserDeprecationMessage("The 'country.default' config schema is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0.");
    $config = $this->config('system.date');
    $config
      ->set('country.default', 'US')
      ->save();
    $this->assertSame('US', $config->get('country.default'));
  }

}
