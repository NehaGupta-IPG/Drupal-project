<?php

declare(strict_types=1);

namespace Drupal\Tests\node\Kernel\Views;

use Drupal\Core\Database\Database;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use Drupal\views\Tests\ViewTestData;

/**
 * Tests the integration of node_revision table of node module.
 *
 * @group node
 */
class RevisionRelationshipsTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'node_test_views',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp($import_test_views);

    $this->installSchema('node', 'node_access');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    ConfigurableLanguage::createFromLangcode('fr')->save();

    ViewTestData::createTestViews(static::class, ['node_test_views']);
  }

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_node_revision_nid', 'test_node_revision_vid'];

  /**
   * Create a node with revision and rest result count for both views.
   */
  public function testNodeRevisionRelationship(): void {
    $type = NodeType::create(['type' => 'page', 'name' => 'page']);
    $type->save();
    $node = Node::create(['type' => 'page', 'title' => 'test', 'uid' => 1]);
    $node->save();

    // Add a translation.
    $translation = $node->addTranslation('fr', $node->toArray());
    $translation->save();
    // Create revision of the node.
    $node->setNewRevision(TRUE);
    $node->save();

    // Here should be two rows for each translation.
    $view_nid = Views::getView('test_node_revision_nid');
    $this->executeView($view_nid, [$node->id()]);
    if (Database::getConnection()->driver() == 'mongodb') {
      $column_map = [
        'vid' => 'vid',
        'nid_1' => 'nid_1',
        'node_node_all_revisions_langcode' => 'node_field_revision_langcode',
      ];

      $resultset_nid = [
        [
          'vid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
        [
          'vid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
        [
          'vid' => '2',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
        [
          'vid' => '2',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
      ];
    }
    else {
      $column_map = [
        'vid' => 'vid',
        'nid_1' => 'nid_1',
        'node_field_data_node_field_revision_nid' => 'node_node_revision_nid',
        'node_field_revision_langcode' => 'node_field_revision_langcode',
      ];

      $resultset_nid = [
        [
          'vid' => '1',
          'node_node_revision_nid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
        [
          'vid' => '1',
          'node_node_revision_nid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
        [
          'vid' => '2',
          'node_revision_nid' => '1',
          'node_node_revision_nid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
        [
          'vid' => '2',
          'node_revision_nid' => '1',
          'node_node_revision_nid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
      ];
    }
    $this->assertIdenticalResultset($view_nid, $resultset_nid, $column_map);

    // There should be one row with active revision 2 for each translation.
    $view_vid = Views::getView('test_node_revision_vid');
    $this->executeView($view_vid, [$node->id()]);
    if (Database::getConnection()->driver() == 'mongodb') {
      $resultset_vid = [
        [
          'vid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
        [
          'vid' => '2',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
        [
          'vid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
        [
          'vid' => '2',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
      ];
    }
    else {
      $resultset_vid = [
        [
          'vid' => '2',
          'node_node_all_revisions_vid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'en',
        ],
        [
          'vid' => '2',
          'node_node_all_revisions_vid' => '1',
          'nid_1' => '1',
          'node_field_revision_langcode' => 'fr',
        ],
      ];
    }
    $this->assertIdenticalResultset($view_vid, $resultset_vid, $column_map);
  }

}
