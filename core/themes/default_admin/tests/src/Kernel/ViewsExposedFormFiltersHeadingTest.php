<?php

declare(strict_types=1);

namespace Drupal\Tests\default_admin\Kernel;

use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the visible heading added to views exposed filter forms.
 *
 * @see \Drupal\default_admin\Hook\FormHooks::formViewsExposedFormAlter()
 */
#[Group('default_admin')]
#[RunTestsInSeparateProcesses]
class ViewsExposedFormFiltersHeadingTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    // Install and activate the theme, so that its
    // #[Hook('form_views_exposed_form_alter')] implementation is registered.
    // Installing the theme rebuilds the container, which is what registers
    // the hook implementation.
    // @see \Drupal\Core\Hook\ThemeHookCollectorPass
    \Drupal::service('theme_installer')->install(['default_admin']);
    $this->container = \Drupal::getContainer();
    \Drupal::theme()->setActiveTheme(\Drupal::service(ThemeInitializationInterface::class)->initTheme('default_admin'));
  }

  /**
   * Tests the heading of a view's exposed filter form.
   */
  public function testFiltersHeading(): void {
    $storage = View::create([
      'id' => 'test_exposed_filters_heading',
      'label' => 'Test view',
      'module' => 'views',
      'base_table' => 'views_test_data',
      'base_field' => 'id',
    ]);
    $executable = $storage->getExecutable();
    $executable->newDisplay('default', 'Default', 'default');
    $display = $executable->displayHandlers->get('default');
    $display->setOption('title', 'Test view');
    $display->setOption('exposed_form', ['type' => 'basic']);
    $display->setOption('filters', [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'plugin_id' => 'string',
        'exposed' => TRUE,
        'expose' => [
          'identifier' => 'name',
          'label' => 'Name',
        ],
      ],
    ]);
    $storage->save();

    $view = Views::getView('test_exposed_filters_heading');
    $view->setDisplay('default');
    $view->initHandlers();
    $view->build();

    $exposed_form = $view->display_handler->getPlugin('exposed_form');
    $form = $exposed_form->renderExposedForm();
    $output = (string) \Drupal::service('renderer')->renderInIsolation($form);

    $this->assertMatchesRegularExpression('#<h2[^>]*>Filter Test view</h2>#', $output);
    $this->assertStringNotContainsString('aria-label', $output);
  }

}
