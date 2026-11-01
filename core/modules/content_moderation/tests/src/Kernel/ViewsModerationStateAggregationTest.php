<?php

declare(strict_types=1);

namespace Drupal\Tests\content_moderation\Kernel;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the views moderation_state field aggregation integration.
 *
 * @group content_moderation
 */
class ViewsModerationStateAggregationTest extends ViewsKernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'content_moderation',
    'workflows',
    'workflow_type_test',
    'entity_test',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('entity_test_no_bundle');
    $this->installSchema('node', 'node_access');
    $this->installConfig('content_moderation');

    $node_type = NodeType::create([
      'type' => 'example',
      'name' => 'Example',
    ]);
    $node_type->save();

    ConfigurableLanguage::createFromLangcode('fr')->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()->addState('zz_draft', 'ZZ Draft');
    $workflow->getTypePlugin()->addState('aa_draft', 'AA Draft');
    $workflow->getTypePlugin()->addEntityTypeAndBundle('node', 'example');
    $workflow->save();
  }

  /**
   * Tests aggregation with a standard data base table.
   */
  public function testSortBaseTable(): void {
    $this->enableModules(['content_moderation_test_views']);
    $this->installConfig(['content_moderation_test_views']);

    // Create two revisions. The sorted revision will be 'zz_draft' since it
    // will be attached to the default revision of the entity.
    $first_node = Node::create([
      'type' => 'example',
      'title' => 'Foo',
      'moderation_state' => 'aa_draft',
    ]);
    $first_node->save();
    $first_node->moderation_state = 'zz_draft';
    $first_node->save();

    // Create a second published node, which falls between aa_draft and zz_draft
    // for the purposes of testing.
    $second_node = Node::create([
      'type' => 'example',
      'title' => 'Foo',
      'moderation_state' => 'published',
    ]);
    $second_node->save();

    // Add assertions for aggregation below

  }

  /**
   * Tests aggregation with the revision base table.
   */
  public function testAggregationRevisionBaseTable(): void {
    // See ViewsModerationStateSortTest.php and ViewsModerationStateFilterTest.php
  }

  /**
   * Assert the results of a views aggregation.
   *
   * @param string $view_id
   *   The ID of the view.
   * @param string $column
   *   The column associated with each row.
   * @param array $expected
   *   The expected results array.
   *
   * @internal
   */
  protected function assertAggregationResults(string $view_id, string $column, string $order, array $expected): void {
    // For ideas refer to ViewsModerationStateSortTest.php and ViewsModerationStateFilterTest.php.
  }

}
