<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests building the translatable project information.
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocaleParseJsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'locale',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('locale', [
      'locales_location',
      'locales_source',
      'locales_target',
    ]);
  }

  /**
   * Check that we can parse JS from stream wrappers.
   */
  public function testBuildProjects(): void {
    $public_path = 'public://locale_stream_wrapper_test.js';
    $contents = "[Drupal.t('The Pine Bluff Variant'),Drupal.t('Small potatoes'),Drupal.t('Paper hearts')]";
    file_put_contents($public_path, $contents);
    _locale_parse_js_file($public_path);
    // Now they should be in the database.
    $strings = $this->container
      ->get('locale.storage')
      ->getStrings([
        'type' => 'javascript',
        'name' => $public_path,
      ]);
    $this->assertNotEmpty($strings, 'The JS file created source strings to translate.');
  }

}
