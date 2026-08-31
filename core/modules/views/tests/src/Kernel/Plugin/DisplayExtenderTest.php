<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Drupal\views_test_data\Plugin\views\display_extender\DisplayExtenderTest as DisplayExtenderTestData;
use Drupal\views_test_data\Plugin\views\display_extender\DisplayExtenderTest3;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the display extender plugins.
 *
 * @see \Drupal\views_test_data\Plugin\views\display_extender\DisplayExtenderTest
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class DisplayExtenderTest extends ViewsKernelTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Tests display extenders.
   */
  #[IgnoreDeprecations]
  public function testDisplayExtenders(): void {
    $this->expectUserDeprecationMessage('Views::getEnabledDisplayExtenders() is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use Views::getDisplayExtenders() instead. See https://www.drupal.org/node/3568650');
    $this->assertArrayHasKey('display_extender_test', Views::getEnabledDisplayExtenders());

    $view = Views::getView('test_view');
    $view->initDisplay();

    $this->assertArrayHasKey('display_extender_test', $view->display_handler->getExtenders());

    $display_extender = $view->display_handler->getExtenders()['display_extender_test'];
    $this->assertInstanceOf(DisplayExtenderTestData::class, $display_extender);

    $view->preExecute();
    $this->assertTrue($display_extender->testState['preExecute'], 'Make sure the display extender was able to react on preExecute.');
    $view->execute();
    $this->assertTrue($display_extender->testState['query'], 'Make sure the display extender was able to react on query.');
  }

  /**
   * Tests display extenders validation.
   */
  public function testDisplayExtendersValidate(): void {
    DisplayExtenderTest3::enableErrors();

    $view = Views::getView('test_view');
    $errors = $view->validate();

    foreach ($view->displayHandlers as $id => $display) {
      $this->assertArrayHasKey($id, $errors);
      $this->assertContains('Display extender test error.', $errors[$id], "Error message found for $id display");
    }
  }

}
