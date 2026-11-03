<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Plugin;

use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the AJAX options of displays.
 *
 * @see \Drupal\views\Plugin\views\display\DisplayPluginInterface::getAjaxOptions()
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class DisplayAjaxOptionsTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_view'];

  /**
   * Tests that AJAX is used on all elements by default.
   */
  public function testDefaultAjaxOptions(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $this->assertSame([
      'pager' => TRUE,
      'sorting' => TRUE,
      'exposed_form' => TRUE,
    ], $view->display_handler->getAjaxOptions());
  }

  /**
   * Tests storing the AJAX options.
   */
  public function testStoredAjaxOptions(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->display_handler->setOption('use_ajax', TRUE);
    $view->display_handler->setOption('use_ajax_options', [
      'pager' => TRUE,
      'sorting' => FALSE,
      'exposed_form' => FALSE,
    ]);
    // Saving the view checks the configuration against its schema.
    $view->save();

    $view = Views::getView('test_view');
    $view->setDisplay();
    $this->assertSame([
      'pager' => TRUE,
      'sorting' => FALSE,
      'exposed_form' => FALSE,
    ], $view->display_handler->getAjaxOptions());

    // Missing elements default to using AJAX.
    $view->display_handler->setOption('use_ajax_options', ['pager' => FALSE]);
    $this->assertSame([
      'pager' => FALSE,
      'sorting' => TRUE,
      'exposed_form' => TRUE,
    ], $view->display_handler->getAjaxOptions());
  }

  /**
   * Tests that the AJAX options are added to drupalSettings per view.
   */
  public function testDrupalSettings(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();
    $view->display_handler->setOption('use_ajax', TRUE);
    $view->display_handler->setOption('use_ajax_options', [
      'pager' => FALSE,
      'sorting' => TRUE,
      'exposed_form' => FALSE,
    ]);
    $view->preview();

    $settings = $view->element['#attached']['drupalSettings']['views'];
    $key = 'views_dom_id:' . $view->dom_id;
    $this->assertArrayHasKey($key, $settings['ajaxViews']);
    $this->assertSame([
      'pager' => FALSE,
      'sorting' => TRUE,
      'exposed_form' => FALSE,
    ], $settings['ajaxOptions'][$key]);
  }

}
