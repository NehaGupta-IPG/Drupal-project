<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\EntityReference\Views;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\Html;
use Drupal\Core\Site\Settings;
use Drupal\entity_test\EntityTestHelper;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests entity reference selection handler.
 */
#[Group('entity_reference')]
#[RunTestsInSeparateProcesses]
class SelectionTest extends BrowserTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'entity_reference_test',
    'entity_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * An array of node titles, keyed by content type and node ID.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes = [];

  /**
   * The handler settings for the entity reference field.
   *
   * @var array
   */
  protected $handlerSettings;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create content types and nodes.
    $type1 = $this->drupalCreateContentType()->id();
    $type2 = $this->drupalCreateContentType()->id();
    // Add some characters that should be escaped but not double escaped.
    $node1 = $this->drupalCreateNode(['type' => $type1, 'title' => 'Test first node &<>']);
    $node2 = $this->drupalCreateNode(['type' => $type1, 'title' => 'Test second node &&&']);
    $node3 = $this->drupalCreateNode(['type' => $type2, 'title' => 'Test third node <span />']);

    foreach ([$node1, $node2, $node3] as $node) {
      $this->nodes[$node->id()] = $node;
    }

    // Ensure the bundle to which the field is attached actually exists, or we
    // will get config validation errors.
    EntityTestHelper::createBundle('test_bundle');

    // Create an entity reference field.
    $this->handlerSettings = [
      'view' => [
        'view_name' => 'test_entity_reference',
        'display_name' => 'entity_reference_1',
      ],
    ];
  }

  /**
   * Tests that the Views selection handles the views output properly.
   */
  public function testAutocompleteOutput(): void {
    $this->createEntityReferenceField('entity_test', 'test_bundle', 'test_field', $this->randomString(), 'node', 'views', $this->handlerSettings);

    // Reset any internal static caching.
    \Drupal::service('entity_type.manager')->getStorage('node')->resetCache();

    $view = Views::getView('test_entity_reference');
    $view->setDisplay();

    // Enable the display of the 'type' field so we can test that the output
    // does not contain only the entity label.
    $fields = $view->displayHandlers->get('entity_reference_1')->getOption('fields');
    $fields['type']['exclude'] = FALSE;
    $view->displayHandlers->get('entity_reference_1')->setOption('fields', $fields);
    $view->save();

    // Prepare the selection settings key needed by the entity reference
    // autocomplete route.
    $target_type = 'node';
    $selection_handler = 'views';
    $selection_settings = $this->handlerSettings;
    $selection_settings_key = Crypt::hmacBase64(serialize($selection_settings) . $target_type . $selection_handler, Settings::getHashSalt());
    \Drupal::keyValue('entity_autocomplete')->set($selection_settings_key, $selection_settings);

    $result = Json::decode($this->drupalGet('entity_reference_autocomplete/' . $target_type . '/' . $selection_handler . '/' . $selection_settings_key, ['query' => ['q' => 't']]));

    $expected = [
      0 => [
        'value' => $this->nodes[1]->bundle() . ': ' . $this->nodes[1]->label() . ' (' . $this->nodes[1]->id() . ')',
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[1]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">' . Html::escape($this->nodes[1]->label()) . '</span></span>',
      ],
      1 => [
        'value' => $this->nodes[2]->bundle() . ': ' . $this->nodes[2]->label() . ' (' . $this->nodes[2]->id() . ')',
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[2]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">' . Html::escape($this->nodes[2]->label()) . '</span></span>',
      ],
      2 => [
        'value' => $this->nodes[3]->bundle() . ': ' . $this->nodes[3]->label() . ' (' . $this->nodes[3]->id() . ')',
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[3]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">' . Html::escape($this->nodes[3]->label()) . '</span></span>',
      ],
    ];
    $this->assertEquals($expected, $result, 'The autocomplete result of the Views entity reference selection handler contains the proper output.');
  }

  /**
   * Tests that the Views selection can auto create an entity.
   */
  public function testAutocompleteAutoCreate(): void {
    $type3 = $this->drupalCreateContentType()->id();

    $account = $this->drupalCreateUser([
      'access content',
      'administer entity_test content',
      "administer nodes",
    ]);
    $this->drupalLogin($account);

    $this->handlerSettings['auto_create'] = TRUE;
    $this->handlerSettings['auto_create_bundle'] = $type3;
    $this->createEntityReferenceField('entity_test', 'entity_test', 'test_field', $this->randomString(), 'node', 'views_auto_create', $this->handlerSettings);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository */
    $entity_display_repository = \Drupal::service('entity_display.repository');
    $entity_display_repository->getFormDisplay('entity_test', 'entity_test', 'default')
      ->setComponent('test_field', [
        'type' => 'entity_reference_autocomplete',
      ])
      ->save();

    $this->drupalGet('/entity_test/add');

    $this->assertSession()->elementExists('xpath', '//input[@id="edit-test-field-0-target-id" and contains(@class, "form-autocomplete")]');

    $new_title = $this->randomMachineName();

    // Assert referenced node does not exist.
    $this->assertEmpty($this->drupalGetNodeByTitle($new_title), 'Referenced node does not exist yet.');

    $edit = [
      'name[0][value]' => $this->randomMachineName(),
      'test_field[0][target_id]' => $new_title,
    ];
    $this->drupalGet("/entity_test/add");
    $this->submitForm($edit, 'Save');

    // Assert referenced node was created.
    $referenced_node = $this->drupalGetNodeByTitle($new_title);
    $this->assertNotEmpty($referenced_node, 'Referenced node was created.');
    $this->assertSame($type3, $referenced_node->getType());

    // Assert the referenced node is associated with referencing node.
    $result = \Drupal::entityQuery('entity_test')->execute();

    $referencing_nid = key($result);
    $referencing_node = EntityTest::load($referencing_nid);
    $this->assertEquals($referenced_node->id(), $referencing_node->test_field->target_id, 'Newly created node is referenced from the referencing node.');
  }

}
