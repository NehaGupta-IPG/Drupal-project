<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Entity;

use Drupal\Core\Database\Database;
use Drupal\entity_test\Entity\EntityTestMultiValueBasefield;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

// cspell:ignore basefield

/**
 * Tests entity views with multivalue base fields.
 *
 * @group views
 */
class EntityViewsWithMultivalueBaseFieldTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test'];

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_entity_multivalue_basefield'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->installEntitySchema('entity_test_multivalue_basefield');

    EntityTestMultiValueBaseField::create([
      'name' => 'test',
    ])->save();
    EntityTestMultiValueBaseField::create([
      'name' => ['test2', 'test3'],
    ])->save();

    ViewTestData::createTestViews(static::class, ['views_test_config']);
  }

  /**
   * Tests entity views with multivalue base fields.
   */
  public function testView(): void {
    $view = Views::getView('test_entity_multivalue_basefield');
    $view->execute();
    // @todo Fix this test for MongoDB.
    if (Database::getConnection()->driver() != 'mongodb') {
      $this->assertIdenticalResultset($view, [
        ['name' => ['test']],
        ['name' => ['test2', 'test3']],
      ], ['name' => 'name']);
    }
  }

}
