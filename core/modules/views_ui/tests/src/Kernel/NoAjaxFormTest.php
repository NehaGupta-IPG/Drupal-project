<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Kernel;

use Drupal\Core\Asset\AttachedAssets;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views_ui\Form\Ajax\Display;
use Drupal\views_ui\Form\Ajax\EditDetails;
use Drupal\views_ui\Form\Ajax\RearrangeFilter;
use Drupal\views_ui\ViewUI;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests assets attached to standalone Views UI forms.
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class NoAjaxFormTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_ui', 'user'];

  /**
   * Tests that standalone forms include the Views UI styling library.
   */
  #[DataProvider('providerForms')]
  public function testFormLibraries(string $form_class, array $arguments, string $display_id, ?string $library = NULL): void {
    $this->container->get('state')->set('views_test_data.form_library', $library);
    $view = new ViewUI(View::create([
      'id' => 'test',
      'label' => 'Test',
      'base_table' => 'views_test_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
        ],
      ],
    ]));
    $form = new $form_class(...$arguments)->getForm($view, $display_id, 'nojs');
    $this->assertContains('views_ui/admin.styling', $form['#attached']['library'] ?? []);
    if ($library) {
      $this->assertContains($library, $form['#attached']['library']);
      $assets = AttachedAssets::createFromRenderArray($form);
      $fonts = $this->container->get('asset.resolver')->getFontAssets($assets);
      $this->assertSame('core/themes/default_admin/font/inter.woff2', $fonts[0]['data']);
    }
  }

  /**
   * Provides different Views UI configuration forms.
   */
  public static function providerForms(): array {
    return [
      'filter groups' => [RearrangeFilter::class, [], 'default', 'default_admin/global-styling'],
      'display title' => [Display::class, ['title'], 'default'],
      'view details' => [EditDetails::class, [], ''],
    ];
  }

}
