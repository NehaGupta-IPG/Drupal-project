<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Kernel\Entity;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the generic entity bundle filter.
 */
#[Group('views')]
#[RunTestsInSeparateProcesses]
class FilterEntityBundleTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_entity_type_filter'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node'];

  /**
   * Tests the generic bundle filter.
   */
  public function testFilterEntity(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'test_bundle', 'name' => 'Test 1'])->save();
    NodeType::create(['type' => 'test_bundle_2', 'name' => 'Test 2'])->save();
    NodeType::create(['type' => '180575', 'name' => '180575'])->save();

    $bundle_info = $this->container->get('entity_type.bundle.info')->getBundleInfo('node');

    $entities['count'] = 0;

    foreach ($bundle_info as $key => $info) {
      for ($i = 0; $i < 3; $i++) {
        $entity = Node::create([
          'title' => $this->randomString(),
          'uid' => 1,
          'type' => $key,
        ]);
        $entity->save();
        $entities[$key][$entity->id()] = $entity;
        $entities['count']++;
      }
    }
    $view = Views::getView('test_entity_type_filter');

    // Tests \Drupal\views\Plugin\views\filter\Bundle::calculateDependencies().
    $expected = [
      'config' => [
        'node.type.180575',
        'node.type.test_bundle',
        'node.type.test_bundle_2',
      ],
      'module' => [
        'node',
      ],
    ];
    $this->assertSame($expected, $view->getDependencies());

    $this->executeView($view);

    // Test we have all the results, with all types selected.
    $this->assertCount($entities['count'], $view->result);

    // Test the valueOptions of the filter handler.
    $expected = [];
    foreach ($bundle_info as $key => $info) {
      $expected[$key] = $info['label'];
    }
    $this->assertSame($expected, $view->filter['type']->getValueOptions());

    $view->destroy();

    // Test each bundle type.
    foreach ($bundle_info as $key => $info) {
      // Test each bundle type.
      $view->initDisplay();
      $filters = $view->display_handler->getOption('filters');
      $filters['type']['value'] = [$key => $key];
      $view->display_handler->setOption('filters', $filters);
      $this->executeView($view);

      $this->assertSameSize($entities[$key], $view->result);

      $view->destroy();
    }

    // Test an invalid bundle type to make sure we have no results.
    $view->initDisplay();
    $filters = $view->display_handler->getOption('filters');
    $filters['type']['value'] = ['type_3' => 'type_3'];
    $view->display_handler->setOption('filters', $filters);
    $this->executeView($view);

    $this->assertEmpty($view->result);
  }

  /**
   * Tests a bundle filter whose relationship has been removed.
   *
   * The display reports the missing relationship rather than throwing while
   * the filter is initialized.
   */
  public function testMissingRelationship(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'test_bundle', 'name' => 'Test 1'])->save();

    $view = Views::getView('test_entity_type_filter');
    $view->setDisplay();
    $filters = $view->display_handler->getOption('filters');
    $filters['type']['relationship'] = 'removed_relationship';
    $view->display_handler->setOption('filters', $filters);

    $errors = array_map(fn ($error) => strip_tags((string) $error), $view->validate()['default']);
    $this->assertContains('The removed_relationship relationship used in filter criterion Content: Content type is not present in the Default display.', $errors);
  }

  /**
   * Tests adding a bundle filter to a view of a table with no entity type.
   *
   * The filter is initialized before a relationship can be chosen for it, so
   * the entity type cannot come from the base table of the view.
   */
  public function testBaseTableWithoutEntityType(): void {
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    NodeType::create(['type' => 'test_bundle', 'name' => 'Test 1'])->save();

    $view = View::create(['id' => 'test_bundle_no_entity_type', 'base_table' => 'views_test_data'])->getExecutable();
    $view->setDisplay();
    $view->addHandler('default', 'filter', 'node_field_data', 'type');

    $filter = $view->display_handler->getHandler('filter', 'type');
    $this->assertSame(['test_bundle' => 'Test 1'], $filter->getValueOptions());
  }

}
