<?php

declare(strict_types=1);

namespace Drupal\Tests\views_ui\Kernel;

use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests for the filters from the UI.
 */
#[Group('views_ui')]
#[RunTestsInSeparateProcesses]
class FilterUITest extends ViewsKernelTestBase {

  use UserCreationTrait;

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_filter_groups'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'system',
    'text',
    'user',
    'views',
    'views_test_config',
    'views_test_data',
    'views_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUpFixtures(): void {
    parent::setUpFixtures();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['node', 'user']);
  }

  /**
   * Tests that filters with a missing group render in the Views UI.
   */
  public function testFilterWithMissingGroup(): void {
    $this->setUpCurrentUser(permissions: [
      'administer views',
      'administer site configuration',
    ]);

    $view = View::load('test_filter_groups');
    $display = $view->get('display');
    $display['page']['display_options']['filters']['status']['group'] = 3;
    $view->set('display', $display);
    $view->save();

    $this->drupalGet('admin/structure/views/view/test_filter_groups/edit/page');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextNotContains('Undefined array key 0');
    $this->assertSession()->linkExists('Content: Published (= Yes)');
  }

}
