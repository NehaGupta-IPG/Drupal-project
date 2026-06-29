<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime\Functional\Update;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the addition of the year_range setting.
 *
 * @see datetime_post_update_default_year_range()
 */
#[Group('update')]
#[RunTestsInSeparateProcesses]
class YearRangeUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set up the daterange field and config.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'date',
      'id' => 'node.date',
      'type' => 'datetime',
      'settings' => [
        'target_type' => 'node',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'date',
      'entity_type' => 'node',
      'bundle' => 'page',
      'id' => 'node.page.date',
      'label' => 'Date',
    ])->save();
    EntityFormDisplay::load('node.page.default')
      ->setComponent('date', [
        'type' => 'datetime_datelist',
        'weight' => 10,
        'settings' => [],
      ])->save();

    // Restore the config to its state before
    // datetime_post_update_default_year_range
    $content = \Drupal::configFactory()
      ->getEditable('core.entity_form_display.node.page.default')
      ->get('content');
    unset($content['date']['settings']['year_range']);
    \Drupal::configFactory()->getEditable('core.entity_form_display.node.page.default')
      ->set('content', $content)
      ->save();
  }

  /**
   * Tests the upgrade path adding the new year_range config.
   */
  public function testDateTimeYearRangeUpdate(): void {
    // Verify the year_range setting is not present before the update.
    $entityFormDisplay = EntityFormDisplay::load('node.page.default');
    self::assertArrayNotHasKey('year_range', $entityFormDisplay->getComponent('date')['settings']);
    $this->runUpdates();
    $entityFormDisplay = EntityFormDisplay::load('node.page.default');
    self::assertArrayHasKey('year_range', $entityFormDisplay->getComponent('date')['settings']);
    self::assertEquals('1900:2050', $entityFormDisplay->getComponent('date')['settings']['year_range']);
  }

}
