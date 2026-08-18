<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Functional\Update;

use Drupal\Component\Utility\NestedArray;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the update of the boolean filter setting.
 *
 * @see views_post_update_boolean_filter_accept_null()
 */
#[Group('Update')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class ViewsBooleanFilterAcceptNullUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-11.3.0.filled.standard.php.gz',
      __DIR__ . '/../../../fixtures/update/boolean_filter_accept_null.php',
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
   * Tests the upgrade path setting default accept_null setting.
   */
  public function testViewsPostUpdateAddDefaultTableCssClass(): void {
    $view = View::load('test_boolean_filter_accept_null');
    $data = $view->toArray();
    $this->assertArrayNotHasKey('accept_null', NestedArray::getValue($data, [
      'display', 'default', 'display_options', 'filters', 'status',
    ]));
    $this->assertArrayNotHasKey('accept_null', NestedArray::getValue($data, [
      'display', 'default', 'display_options', 'filters', 'created',
    ]));
    // Existing values before the update is run.
    $this->assertArrayHasKey('accept_null', NestedArray::getValue($data, [
      'display', 'default', 'display_options', 'filters', 'promote',
    ]));

    $this->runUpdates();

    $view = View::load('test_boolean_filter_accept_null');
    $data = $view->toArray();

    // Defaults to false.
    static::assertFalse(NestedArray::getValue($data, [
      'display', 'default', 'display_options', 'filters', 'status', 'accept_null',
    ]));
    // Non-boolean fields are left untouched.
    $this->assertArrayNotHasKey('accept_null', NestedArray::getValue($data, [
      'display', 'default', 'display_options', 'filters', 'created',
    ]));
    // Existing settings are persisted.
    static::assertTrue(NestedArray::getValue($data, [
      'display', 'default', 'display_options', 'filters', 'promote', 'accept_null',
    ]));
  }

}
