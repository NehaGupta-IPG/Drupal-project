<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Functional\Update;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Tests update of the 'absolute_url' file and image field formatter setting.
 *
 * @see file_post_update_set_default_absolute_url()
 *
 * @group file
 */
class FileFieldFormatterUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-9.4.0.bare.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/test-file-field-formatter-update.php',
    ];
  }

  /**
   * Test that default values are being set for the field formatters.
   */
  public function testFieldFormatterDefaultValuesChange(): void {
    $displays = EntityViewDisplay::loadMultiple();
    foreach ($displays as $display) {
      /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
      $fields_settings = $display->get('content');
      foreach ($fields_settings as $settings) {
        if (!empty($settings['type'])) {
          switch ($settings['type']) {
            case 'file_url_plain':
            case 'image_url':
              $this->assertArrayNotHasKey('absolute_url', $settings['settings']);
              break;

          }
        }
      }
    }
    $this->runUpdates();

    $displays = EntityViewDisplay::loadMultiple();
    foreach ($displays as $display) {
      /** @var \Drupal\Core\Entity\Entity\EntityViewDisplay $display */
      $fields_settings = $display->get('content');
      foreach ($fields_settings as $settings) {
        if (!empty($settings['type'])) {
          switch ($settings['type']) {
            case 'file_url_plain':
            case 'image_url':
              $this->assertArrayHasKey('absolute_url', $settings['settings']);
              $this->assertFalse($settings['settings']['absolute_url']);
              break;
          }
        }
      }
    }
  }

}
