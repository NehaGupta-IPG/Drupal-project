<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the form API datetime element.
 *
 * @group Form
 */
class DatetimeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['form_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the time sub-element.
   */
  public function testTimeField(): void {
    $this->drupalGet('form-test/datetime-time');

    $edit = [
      'datetime_with_seconds_html5[date]' => '2016-01-15',
      'datetime_with_seconds_html5[time]' => '13:14:15',
      'datetime_without_seconds_html5[date]' => '2016-03-20',
      // This is need to simulate Chrome as it doesn't send seconds when they
      // are set as "00"
      // https://www.drupal.org/node/2723159#comment-11859609
      'datetime_without_seconds_html5[time]' => '16:17',
      'datetime_with_seconds_text[date]' => '2016-01-15',
      'datetime_with_seconds_text[time]' => '13:14:15',
      'datetime_without_seconds_text[date]' => '2016-03-20',
      'datetime_without_seconds_text[time]' => '16:17',
      'datetime_unusual_format[date]' => '15/03/2016',
      'datetime_unusual_format[time]' => '13-37-42',
    ];
    $this->submitForm($edit, 'Submit');

    $messages = $this->xpath('//div[@data-drupal-messages]');
    $this->assertTrue(!empty($messages));
    $this->assertStringContainsString('Success', $messages[0]->getText());

    $expected_values = [
      'datetime_with_seconds_html5[date]' => '2016-01-15',
      'datetime_with_seconds_html5[time]' => '13:14:15',
      'datetime_without_seconds_html5[date]' => '2016-03-20',
      'datetime_without_seconds_html5[time]' => '16:17:00',
      'datetime_with_seconds_text[date]' => '2016-01-15',
      'datetime_with_seconds_text[time]' => '13:14:15',
      'datetime_without_seconds_text[date]' => '2016-03-20',
      'datetime_without_seconds_text[time]' => '16:17',
      'datetime_unusual_format[date]' => '15/03/2016',
      'datetime_unusual_format[time]' => '13-37-42',
    ];

    foreach ($expected_values as $name => $value) {
      $input = $this->xpath('//input[@name=:name and @value=:value]', [
        ':name' => $name,
        ':value' => $value,
      ]);
      $this->assertTrue(!empty($input), $name);
    }
  }

}
