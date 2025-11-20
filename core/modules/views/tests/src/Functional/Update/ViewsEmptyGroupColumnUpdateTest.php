<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests fixing entity field handlers with empty group columns.
 *
 * @see views_post_update_empty_entity_field_group_column()
 */
#[Group('Update')]
#[CoversFunction('views_post_update_empty_entity_field_group_column')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class ViewsEmptyGroupColumnUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/test_empty_group_column.php',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installModulesFromClassProperty($this->container);
  }

  /**
   * Ensures the update populates missing group columns.
   */
  public function testViewsPostUpdateEmptyGroupColumn(): void {
    $view = View::load('test_empty_group_column');
    $data = $view->toArray();
    $this->assertSame('', $data['display']['default']['display_options']['fields']['title']['group_column']);

    $this->runUpdates();

    $view = View::load('test_empty_group_column');
    $data = $view->toArray();
    $this->assertSame('value', $data['display']['default']['display_options']['fields']['title']['group_column']);
  }

}
