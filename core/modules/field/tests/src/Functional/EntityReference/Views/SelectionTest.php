<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Functional\EntityReference\Views;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Site\Settings;
use Drupal\entity_test\EntityTestHelper;
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

    $web_user = $this->drupalCreateUser([
      'view test entity',
      'administer entity_test content',
    ]);
    $this->drupalLogin($web_user);

    // Create a variety of content types for testing.
    for ($i = 0; $i <= 2; ++$i) {
      $content_types[] = $this->drupalCreateContentType()->id();
    }

    // Create nodes having title characters that should be escaped but not
    // double-escaped.
    $nodes = [
      $this->drupalCreateNode(['type' => $content_types[0], 'title' => 'Test first node &<>']),
      $this->drupalCreateNode(['type' => $content_types[0], 'title' => 'Test second node &&&']),
      $this->drupalCreateNode(['type' => $content_types[1], 'title' => 'Test third node <span />']),
      $this->drupalCreateNode(['type' => $content_types[1], 'title' => "I'm in your demo, making you smile"]),
    ];

    foreach ($nodes as $node) {
      $this->nodes[$node->id()] = $node;
    }

    // Ensure the bundle to which the field is attached actually exists, or we
    // will get config validation errors.
    EntityTestHelper::createBundle('test_bundle');

    // Create an entity reference field.
    $handler_settings = [
      'view' => [
        'view_name' => 'test_entity_reference',
        'display_name' => 'entity_reference_1',
      ],
    ];
    $this->handlerSettings = $handler_settings;
    $this->createEntityReferenceField('entity_test', 'entity_test', 'test_field', 'Reference', 'node', 'views', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');
    $display_repository->getFormDisplay('entity_test', 'entity_test')
      ->setComponent('test_field', [
        'type' => 'entity_reference_autocomplete_tags',
        'weight' => -4,
      ])
      ->save();
  }

  /**
   * Tests that the Views selection handles the views output properly.
   */
  public function testAutocompleteOutput(): void {
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

    $autocomplete_response =
      $this->drupalGet(
        sprintf("entity_reference_autocomplete/%s/%s/%s", $target_type, $selection_handler, $selection_settings_key),
        ['query' => ['q' => 't']]);

    $result = Json::decode($autocomplete_response);

    $expected = [
      0 => [
        'value' => sprintf("%s: Test first node &<> (%s)", $this->nodes[1]->bundle(), $this->nodes[1]->id()),
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[1]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">Test first node &amp;&lt;&gt;</span></span>',
      ],
      1 => [
        'value' => sprintf("%s: Test second node &&& (%s)", $this->nodes[2]->bundle(), $this->nodes[2]->id()),
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[2]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">Test second node &amp;&amp;&amp;</span></span>',
      ],
      2 => [
        'value' => sprintf("%s: Test third node <span /> (%s)", $this->nodes[3]->bundle(), $this->nodes[3]->id()),
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[3]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">Test third node &lt;span /&gt;</span></span>',
      ],
    ];
    $this->assertEquals($expected, $result, 'The autocomplete result of the Views entity reference selection handler contains the proper output.');

    $autocomplete_response =
      $this->drupalGet(
        sprintf("entity_reference_autocomplete/%s/%s/%s", $target_type, $selection_handler, $selection_settings_key),
        ['query' => ['q' => "I'm"]]);

    $result = Json::decode($autocomplete_response);

    $expected = [
      0 => [
        'value' => sprintf('"%s: I\'m in your demo, making you smile (%s)"', $this->nodes[4]->bundle(), $this->nodes[4]->id()),
        'label' => '<span class="views-field views-field-type"><span class="field-content">' . $this->nodes[4]->bundle() . '</span></span>: <span class="views-field views-field-title"><span class="field-content">I&#039;m in your demo, making you smile</span></span>',
      ],
    ];
    $this->assertEquals($expected, $result, 'The autocomplete result of the Views entity reference selection handler contains the proper output.');

    // Test that the Views output is used for the default value when
    // re-rendering the form.
    $expected_label = sprintf(
      "%s: %s (%s)",
      $this->nodes[1]->bundle(),
      $this->nodes[1]->label(),
      $this->nodes[1]->id());

    $this->drupalGet('entity_test/add');
    $edit = [
      'test_field[target_id]' => sprintf('node (%d)', $this->nodes[1]->id()),
    ];
    $this->submitForm($edit, 'Save');
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertSession()->pageTextContains('entity_test ' . $id . ' has been created.');
    $this->assertEquals($expected_label, $this->getSession()->getPage()->findField('test_field[target_id]')->getValue());

    // Test that the Views output is used for the default value when entity is
    // edited through a fresh copy of the edit form.
    $this->drupalGet('entity_test/manage/' . $id . '/edit');
    $this->assertEquals($expected_label, $this->getSession()->getPage()->findField('test_field[target_id]')->getValue());

    // Test that the Views output is used for all values when re-rendering the
    // form after multiple entities have been selected.
    $expected_label = sprintf(
      '%s: %s (%d), %s: %s (%d), "%s: %s (%d)"',
      $this->nodes[1]->bundle(),
      $this->nodes[1]->label(),
      $this->nodes[1]->id(),
      $this->nodes[2]->bundle(),
      $this->nodes[2]->label(),
      $this->nodes[2]->id(),
      $this->nodes[4]->bundle(),
      $this->nodes[4]->label(),
      $this->nodes[4]->id());

    $this->drupalGet('entity_test/add');
    $edit = [
      'test_field[target_id]' => sprintf(
        'node (%d), node (%d), "I\'m in your demo, making you smile (%d)"',
        $this->nodes[1]->id(),
        $this->nodes[2]->id(),
        $this->nodes[4]->id()),
    ];
    $this->submitForm($edit, 'Save');
    preg_match('|entity_test/manage/(\d+)|', $this->getUrl(), $match);
    $id = $match[1];
    $this->assertSession()->pageTextContains('entity_test ' . $id . ' has been created.');
    $this->assertEquals($expected_label, $this->getSession()->getPage()->findField('test_field[target_id]')->getValue());

    // Test that the Views output is used for the default value when entity is
    // edited through a fresh copy of the edit form.
    $this->drupalGet('entity_test/manage/' . $id . '/edit');
    $this->assertEquals($expected_label, $this->getSession()->getPage()->findField('test_field[target_id]')->getValue());
  }

}
