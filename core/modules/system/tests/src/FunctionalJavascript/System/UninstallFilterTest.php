<?php

declare(strict_types=1);

namespace Drupal\Tests\system\FunctionalJavascript\System;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the modules uninstall filter.
 *
 * @group Module
 */
class UninstallFilterTest extends WebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['module_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the modules uninstall filter.
   */
  public function testFilter() : void {
    $account = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($account);

    $session = $this->getSession();

    $this->drupalGet('admin/modules/uninstall');
    $filter = $session->getPage()->find('css', '#edit-text');

    // Test filter using module machine name.
    $filter->setValue('module_test');
    $session->wait(1000);
    $result = $this->xpath('//table/tbody/tr[not(contains(@style, :style)) and @data-drupal-selector=:data-drupal-selector]', [
      ':style' => 'display: none',
      ':data-drupal-selector' => 'edit-module-test',
    ]);
    $this->assertCount(1, $result, 'Module test exists');

    // Test filter using module name.
    $filter->setValue('Module test');
    $session->wait(1000);
    $result = $this->xpath('//table/tbody/tr[not(contains(@style, :style)) and @data-drupal-selector=:data-drupal-selector]', [
      ':style' => 'display: none',
      ':data-drupal-selector' => 'edit-module-test',
    ]);
    $this->assertCount(1, $result, 'Module test exists');

    // Test non-existing module.
    $filter->setValue('Cron Queue test');
    $session->wait(1000);
    $result = $this->xpath('//table/tbody/tr[not(contains(@style, :style))]', [
      ':style' => 'display: none',
    ]);
    $this->assertCount(0, $result, 'Cron Queue test does not exist');
  }

}
