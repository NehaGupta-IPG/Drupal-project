<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime_range\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityInterface;
use Drupal\datetime_range\Plugin\Field\FieldWidget\DateRangeDatelistWidget;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the year range settings of the date range field.
 */
#[Group('datetime_range')]
class DateRangeYearRangeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test',
    'datetime_range',
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
      'field_name' => 'range',
      'id' => 'entity_test.range',
      'type' => 'daterange',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'range',
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
      'id' => 'entity_test.entity_test.range',
      'label' => 'Range',
    ])->save();
    $this->getEntityFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('range', [
        'type' => 'daterange_datelist',
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
  protected function getEntityFormDisplay(string $entity_type, string $bundle, string $form_mode): EntityInterface {
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
  #[DataProvider('providerTestDateRangeYearRange')]
  public function testDateRangeYearRange($from, $to, $from_year = NULL, $to_year = NULL): void {
    $from_year = $from_year ?: $from;
    $to_year = $to_year ?: $to;
    $from_year_not_exists = $from_year - 1;
    $to_year_not_exists = $to_year + 1;

    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
    $this->getEntityFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('range', [
        'type' => 'daterange_datelist',
        'weight' => 10,
        'settings' => [
          'year_range' => $from . ':' . $to,
        ] + DateRangeDatelistWidget::defaultSettings(),
      ])
      ->save();
    $this->drupalGet('/entity_test/add');
    $assert = $this->assertSession();
    $assert->fieldExists('range[0][value][year]');
    $assert->optionNotExists('range[0][value][year]', $from_year_not_exists);
    $assert->optionNotExists('range[0][value][year]', $to_year_not_exists);
    $assert->optionExists('range[0][value][year]', $from_year);
    $assert->optionExists('range[0][value][year]', $to_year);
  }

  /**
   * Data provider for testDateRangeYearRange().
   */
  public static function providerTestDateRangeYearRange(): array {
    return [
      'year:year' => ['1978', '2017'],
      'positive:year' => ['+3', '2040', date('Y') + 3],
      'negative:year' => ['-3', '2040', date('Y') - 3],
      'year:positive' => ['1978', '+3', NULL, date('Y') + 3],
      'year:negative' => ['1978', '-3', NULL, date('Y') - 3],
      'positive:positive' => ['+3', '+9', date('Y') + 3, date('Y') + 9],
      'negative:negative' => ['-9', '-3', date('Y') - 9, date('Y') - 3],
      'negative:positive' => ['-3', '+3', date('Y') - 3, date('Y') + 3],
    ];
  }

}
