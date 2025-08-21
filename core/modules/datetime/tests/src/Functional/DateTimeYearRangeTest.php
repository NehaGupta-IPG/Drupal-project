<?php

namespace Drupal\Tests\datetime\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\datetime\Plugin\Field\FieldWidget\DateTimeDatelistWidget;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the year range settings of the date time field.
 *
 * @group datetime
 */
class DateTimeYearRangeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'datetime',
    'field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    FieldStorageConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'date',
      'id' => 'entity_test.date',
      'type' => 'datetime',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'date',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'id' => 'entity_test.entity_test.date',
      'label' => 'Date',
    ])->save();
    $this->getEntityFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('date', [
        'type' => 'datetime_datelist',
        'weight' => 10,
      ])
      ->save();
  }

  /**
   * Gets entity form display.
   *
   * @param string $entity_type
   *   Entity type ID.
   * @param string $bundle
   *   Bundle.
   * @param string $form_mode
   *   Form mode.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Form display.
   */
  protected function getEntityFormDisplay($entity_type, $bundle, $form_mode) {
    $entity_form_display = EntityFormDisplay::load($entity_type . '.' . $bundle . '.' . $form_mode);

    // If not found, create a fresh entity object. We do not preemptively create
    // new entity form display configuration entries for each existing entity
    // type and bundle whenever a new form mode becomes available. Instead,
    // configuration entries are only created when an entity form display is
    // explicitly configured and saved.
    if (!$entity_form_display) {
      $entity_form_display = EntityFormDisplay::create([
        'targetEntityType' => $entity_type,
        'bundle' => $bundle,
        'mode' => $form_mode,
        'status' => TRUE,
      ]);
    }

    return $entity_form_display;
  }

  /**
   * Test the year range widget setting.
   */
  public function testDateTimeYearRange() {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->drupalGet('/entity_test/add');
    $assert = $this->assertSession();
    $assert->fieldExists('date[0][value][year]');
    // Test the default 1900:2050 range.
    $assert->optionExists('date[0][value][year]', 1900);
    $assert->optionExists('date[0][value][year]', 2050);
    // Now change the settings.
    $this->getEntityFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('date', [
        'type' => 'datetime_datelist',
        'weight' => 10,
        'settings' => [
          'year_range' => '1978:2017',
        ] + DateTimeDatelistWidget::defaultSettings(),
      ])
      ->save();
    $this->drupalGet('/entity_test/add');
    $assert = $this->assertSession();
    $assert->fieldExists('date[0][value][year]');
    // Test the new 1978:2017 range.
    $assert->optionNotExists('date[0][value][year]', 1900);
    $assert->optionNotExists('date[0][value][year]', 2050);
    $assert->optionExists('date[0][value][year]', 1978);
    $assert->optionExists('date[0][value][year]', 2017);
  }

}
