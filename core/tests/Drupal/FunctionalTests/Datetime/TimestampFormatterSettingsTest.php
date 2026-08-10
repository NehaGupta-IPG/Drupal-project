<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Datetime;

use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the settings of the TimestampFormatter core field formatter.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class TimestampFormatterSettingsTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'field_ui'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalLogin($this->drupalCreateUser([
      'access administration pages',
      'administer entity_test display',
      'view the administration theme',
    ]));

    FieldStorageConfig::create([
      'field_name' => 'field_timestamp',
      'entity_type' => 'entity_test',
      'type' => 'timestamp',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_timestamp',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    EntityViewDisplay::create([
      'targetEntityType' => 'entity_test',
      'bundle' => 'entity_test',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_timestamp', ['type' => 'timestamp'])->save();
  }

  /**
   * Tests that the fallback configuration label is not saved as a setting.
   *
   * The label only introduces the settings below it, so saving it would store
   * a setting that has no schema, which makes saving the display fail.
   */
  public function testTimeDiffDescriptionIsNotSaved(): void {
    $this->drupalGet('entity_test/structure/entity_test/display/default');
    $this->submitForm([], 'field_timestamp_settings_edit');
    $this->submitForm([
      'fields[field_timestamp][settings_edit_form][settings][time_diff][enabled]' => TRUE,
    ], 'Update');
    $this->submitForm([], 'Save');

    $settings = EntityViewDisplay::load('entity_test.entity_test.default')->getComponent('field_timestamp')['settings'];
    $this->assertArrayNotHasKey('description', $settings['time_diff']);
  }

}
