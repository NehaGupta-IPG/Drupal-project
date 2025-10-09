<?php

declare(strict_types=1);

namespace Drupal\Tests\datetime_range\Functional\EntityResource\EntityTest;

use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\datetime_range\Plugin\Field\FieldType\DateRangeItem;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the daterange field constraint with 'allday' items.
 */
#[Group('datetime_range')]
class EntityTestAllDayTest extends EntityTestDateRangeTestBase {

  /**
   * {@inheritdoc}
   */
  protected static string $startDateString = '2017-02-28T13:00:00';

  /**
   * {@inheritdoc}
   */
  protected static string $endDateString = '2017-03-01T12:59:59';

  /**
   * {@inheritdoc}
   */
  protected static string $datetimeType = DateRangeItem::DATETIME_TYPE_ALLDAY;

  /**
   * {@inheritdoc}
   */
  protected function assertNormalizationEdgeCases($method, Url $url, array $request_options):void {
    parent::assertNormalizationEdgeCases($method, $url, $request_options);

    if ($this->entity->getEntityType()->hasKey('bundle')) {
      $fieldName = static::$fieldName;
      $fieldFormat = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;

      // DX: 422 when date type is incorrect.

      $value = ['2017', '03', '01', '21', '53', '00'];
      $end_value = static::$endDateString;
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range start value must be a string.\n{$fieldName}.0.value: This value should be of the correct primitive type.\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      $value = static::$startDateString;
      $end_value = ['2017', '03', '02', '21', '53', '00'];
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range end value must be a string.\n{$fieldName}.0.end_value: This value should be of the correct primitive type.\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      $value = ['2017', '03', '01', '21', '53', '00'];
      $end_value = ['2017', '03', '02', '21', '53', '00'];
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range start value must be a string.\n{$fieldName}.0: The date range end value must be a string.\n{$fieldName}.0.value: This value should be of the correct primitive type.\n{$fieldName}.0.end_value: This value should be of the correct primitive type.\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      // DX: 422 when date format is incorrect.

      $value = '2017-03-01';
      $end_value = static::$endDateString;
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range start value '{$value}' is invalid for the format '{$fieldFormat}'\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      $value = static::$startDateString;
      $end_value = '2017-03-02';
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range end value '{$end_value}' is invalid for the format '{$fieldFormat}'\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      $value = '2017-03-01';
      $end_value = '2017-03-02';
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range start value '{$value}' is invalid for the format '{$fieldFormat}'\n{$fieldName}.0: The date range end value '{$end_value}' is invalid for the format '{$fieldFormat}'\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      // DX: 422 when date format is incorrect.

      $value = '2016-13-55T20:02:00';
      $end_value = static::$endDateString;
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range start value '{$value}' did not parse properly for the format '{$fieldFormat}'\n{$fieldName}.0.value: This value should be of the correct primitive type.\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      $value = static::$startDateString;
      $end_value = '2018-13-56T20:02:00';
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range end value '{$end_value}' did not parse properly for the format '{$fieldFormat}'\n{$fieldName}.0.end_value: This value should be of the correct primitive type.\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      $value = '2016-13-55T20:02:00';
      $end_value = '2018-13-56T20:02:00';
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range start value '{$value}' did not parse properly for the format '{$fieldFormat}'\n{$fieldName}.0: The date range end value '{$end_value}' did not parse properly for the format '{$fieldFormat}'\n{$fieldName}.0.value: This value should be of the correct primitive type.\n{$fieldName}.0.end_value: This value should be of the correct primitive type.\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);

      // DX: 422 when the end date is before the start date.

      $value = static::$endDateString;
      $end_value = static::$startDateString;
      $message = "Unprocessable Entity: validation failed.\n{$fieldName}.0: The date range end value '{$end_value}' must be equal to or after the start value '{$value}'\n";
      $this->doEdgeCaseCall($method, $url, $request_options, $value, $end_value, $message);
    }
  }

}
