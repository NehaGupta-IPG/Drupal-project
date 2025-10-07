<?php

namespace Drupal\Tests\datetime_range\Functional\EntityResource\EntityTest;

use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\entity_test\Functional\Rest\EntityTestResourceTestBase;
use Drupal\Tests\rest\Functional\AnonResourceTestTrait;
use GuzzleHttp\RequestOptions;

/**
 * Base class for daterange field constraints.
 */
class EntityTestDateRangeTestBase extends EntityTestResourceTestBase {

  use AnonResourceTestTrait;

  /**
   * The starting ISO date string to use throughout the test.
   *
   * @var string
   */
  protected static string $startDateString;

  /**
   * The ending ISO date string to use throughout the test.
   *
   * @var string
   */
  protected static string $endDateString;

  /**
   * Daterange test field name.
   *
   * @var string
   */
  protected static string $fieldName = 'field_daterange';

  /**
   * Daterange field type.
   */
  protected static string $datetimeType = DateRangeItem::DATETIME_TYPE_DATETIME;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['datetime', 'datetime_range', 'entity_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Add daterange field.
    FieldStorageConfig::create([
      'field_name' => static::$fieldName,
      'type' => 'daterange',
      'entity_type' => static::$entityTypeId,
      'settings' => ['datetime_type' => static::$datetimeType],
    ])
      ->save();

    FieldConfig::create([
      'field_name' => static::$fieldName,
      'entity_type' => static::$entityTypeId,
      'bundle' => $this->entity->bundle(),
    ])
      ->save();

    // Reload entity so that it has the new field.
    $this->entity = $this->entityStorage->load($this->entity->id());
    $this->entity->set(static::$fieldName, [
      'value' => static::$startDateString,
      'end_value' => static::$endDateString,
    ]);
    $this->entity->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function createEntity() {
    $entity_test = EntityTest::create([
      'name' => 'Llama',
      'type' => static::$entityTypeId,
      static::$fieldName => [
        'value' => static::$startDateString,
        'end_value' => static::$endDateString,
      ],
    ]);
    $entity_test->setOwnerId(0);
    $entity_test->save();

    return $entity_test;
  }

  /**
   * {@inheritdoc}
   */
  protected function getExpectedNormalizedEntity() {
    return parent::getExpectedNormalizedEntity() + [
        static::$fieldName => [
          [
            'value' => $this->entity->get(static::$fieldName)->value,
            'end_value' => $this->entity->get(static::$fieldName)->end_value,
          ],
        ],
      ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getNormalizedPostEntity() {
    return parent::getNormalizedPostEntity() + [
        static::$fieldName => [
          [
            'value' => static::$startDateString,
            'end_value' => static::$endDateString,
          ],
        ],
      ];
  }

  /**
   * {@inheritdoc}
   */
  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options) {
    parent::assertNormalizationEdgeCases($method, $url, $request_options);
  }

  /**
   * Performs a REST call to test an edge case.
   *
   * @param string $method
   *   HTTP method.
   * @param \Drupal\Core\Url $url
   *   URL to request.
   * @param array $request_options
   *   Request options to apply.
   * @var mixed $value
   *   The test start date value.
   * @var mixed $end_value
   *   The test end date value.
   * @var string $message
   *   The expected error message.
   */
  protected function doEdgeCaseCall(string $method, Url $url, array $request_options, mixed $value, mixed $end_value, string $message): void {
    $normalization = $this->getNormalizedPostEntity();
    $normalization[static::$fieldName][0]['value'] = $value;
    $normalization[static::$fieldName][0]['end_value'] = $end_value;

    $request_options[RequestOptions::BODY] = $this->serializer->encode($normalization, static::$format);
    $response = $this->request($method, $url, $request_options);
    $this->assertResourceErrorResponse(422, $message, $response);
  }

}
