<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Url;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Tests\ViewTestData;
use Drupal\views_ui\ViewUI;
use Drupal\views_ui\Hook\ViewsUiHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ViewsBlock.
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class ViewsBlockTest extends ViewsKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_test_views',
    'views_ui',
  ];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view_block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp();

    ViewTestData::createTestViews(static::class, ['block_test_views']);
  }

  /**
   * Tests the editing links for ViewsBlockBase.
   */
  public function testOperationLinks(): void {
    $this->setUpCurrentUser(['uid' => 0]);

    $block = Block::create([
      'plugin' => 'views_block:test_view_block-block_1',
      'region' => 'content',
      'id' => 'machine_name',
      'theme' => 'stark',
    ]);

    // The anonymous user doesn't have the "administer block" permission.
    $viewsUiEntityOperation = new ViewsUiHooks();
    $this->assertEmpty($viewsUiEntityOperation->entityOperation($block));

    $this->setUpCurrentUser(['uid' => 1], ['administer views']);

    $operations = $viewsUiEntityOperation->entityOperation($block);
    $parameters = $operations['view-edit']['url']->getRouteParameters();
    $view = new ViewUI(View::load($parameters['view']));
    $form = $this->container->get('entity.form_builder')->getForm($view, 'edit', ['display_id' => $parameters['display_id']]);
    $this->assertContains('views_ui/admin.styling', $form['#attached']['library'] ?? []);

    // The admin user does have the "administer block" permission.
    $this->assertEquals([
      'view-edit' => [
        'title' => 'Edit view',
        'url' => Url::fromRoute('entity.view.edit_display_form', [
          'view' => 'test_view_block',
          'display_id' => 'block_1',
        ]),
        'weight' => 50,
      ],
    ], $operations);
  }

}
