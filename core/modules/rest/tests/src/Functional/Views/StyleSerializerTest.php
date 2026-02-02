<?php

declare(strict_types=1);

namespace Drupal\Tests\rest\Functional\Views;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\Tests\views\Functional\ViewTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the serializer style plugin.
 *
 * @see \Drupal\rest\Plugin\views\display\RestExport
 * @see \Drupal\rest\Plugin\views\style\Serializer
 * @see \Drupal\rest\Plugin\views\row\DataEntityRow
 * @see \Drupal\rest\Plugin\views\row\DataFieldRow
 */
#[Group('rest')]
#[RunTestsInSeparateProcesses]
class StyleSerializerTest extends ViewTestBase {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'views_ui',
    'entity_test',
    'rest_test_views',
    'node',
    'text',
    'field',
    'language',
    'basic_auth',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = [
    'test_serializer_display_field',
    'test_serializer_display_entity',
    'test_serializer_display_entity_translated',
    'test_serializer_node_display_field',
    'test_serializer_node_exposed_filter',
    'test_serializer_shared_path',
  ];

  /**
   * A user with permissions to look at test entity and configure views.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $adminUser;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['rest_test_views']): void {
    parent::setUp($import_test_views, $modules);

    $this->adminUser = $this->drupalCreateUser([
      'administer views',
      'administer entity_test content',
      'access user profiles',
      'view test entity',
    ]);

    $this->enableViewsTestModule();
    $this->renderer = \Drupal::service('renderer');
  }

  /**
   * Checks that the auth options restricts access to a REST views display.
   */
  public function testRestViewsAuthentication(): void {
    // Assume the view is hidden behind a permission.
    $this->drupalGet('test/serialize/auth_with_perm', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(401);

    // Not even logging in would make it possible to see the view, because then
    // we are denied based on authentication method (cookie).
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('test/serialize/auth_with_perm', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalLogout();

    // But if we use the basic auth authentication strategy, we should be able
    // to see the page.
    $url = $this->buildUrl('test/serialize/auth_with_perm');
    $response = \Drupal::httpClient()->get($url, [
      'auth' => [$this->adminUser->getAccountName(), $this->adminUser->pass_raw],
      'query' => [
        '_format' => 'json',
      ],
    ]);

    // Ensure that any changes to variables in the other thread are picked up.
    $this->refreshVariables();

    $this->assertEquals(200, $response->getStatusCode());
  }

  /**
   * Verifies REST export views work on the same path as a page display.
   */
  public function testSharedPagePath(): void {
    // Test with no format as well as html explicitly.
    $this->drupalGet('test/serialize/shared');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('content-type', 'text/html; charset=utf-8');

    $this->drupalGet('test/serialize/shared', ['query' => ['_format' => 'html']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('content-type', 'text/html; charset=utf-8');

    $this->drupalGet('test/serialize/shared', ['query' => ['_format' => 'json']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('content-type', 'application/json');

    $this->drupalGet('test/serialize/shared', ['query' => ['_format' => 'xml']]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseHeaderEquals('content-type', 'text/xml; charset=utf-8');
  }

  /**
   * Verifies site maintenance mode functionality.
   */
  public function testSiteMaintenance(): void {
    $view = Views::getView('test_serializer_display_field');
    $view->initDisplay();
    $this->executeView($view);

    // Set the site to maintenance mode.
    $this->container->get('state')->set('system.maintenance_mode', TRUE);

    $this->drupalGet('test/serialize/entity', ['query' => ['_format' => 'json']]);
    // Verify that the endpoint is unavailable for anonymous users.
    $this->assertSession()->statusCodeEquals(503);
  }

  /**
   * Sets up a request on the request stack with a specified format.
   *
   * @param string $format
   *   The new request format.
   */
  protected function addRequestWithFormat($format): void {
    $request = \Drupal::request();
    $request = clone $request;
    $request->setRequestFormat($format);

    \Drupal::requestStack()->push($request);
  }

  /**
   * Tests the "Grouped rows" functionality.
   */
  public function testGroupRows(): void {
    $this->drupalCreateContentType(['type' => 'page']);
    // Create a text field with cardinality set to unlimited.
    $field_name = 'field_group_rows';
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();
    // Create an instance of the text field on the content type.
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'page',
    ]);
    $field->save();
    $grouped_field_values = ['a', 'b', 'c'];
    $edit = [
      'title' => $this->randomMachineName(),
      $field_name => $grouped_field_values,
    ];
    $this->drupalCreateNode($edit);
    $view = Views::getView('test_serializer_node_display_field');
    $view->setDisplay('rest_export_1');
    // Override the view's fields to include the field_group_rows field, set the
    // group_rows setting to true.
    $fields = [
      $field_name => [
        'id' => $field_name,
        'table' => 'node__' . $field_name,
        'field' => $field_name,
        'type' => 'string',
        'group_rows' => TRUE,
      ],
    ];
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $build = $view->preview();
    // Get the serializer service.
    $serializer = $this->container->get('serializer');
    // Check if the field_group_rows field is grouped.
    $expected = [];
    $expected[] = [$field_name => implode(', ', $grouped_field_values)];
    $this->assertEquals($serializer->serialize($expected, 'json'), (string) $this->renderer->renderRoot($build));
    // Set the group rows setting to false.
    $view = Views::getView('test_serializer_node_display_field');
    $view->setDisplay('rest_export_1');
    $fields[$field_name]['group_rows'] = FALSE;
    $view->displayHandlers->get('default')->overrideOption('fields', $fields);
    $build = $view->preview();
    // Check if the field_group_rows field is ungrouped and displayed per row.
    $expected = [];
    foreach ($grouped_field_values as $grouped_field_value) {
      $expected[] = [$field_name => $grouped_field_value];
    }
    $this->assertEquals($serializer->serialize($expected, 'json'), (string) $this->renderer->renderRoot($build));
  }

  /**
   * Tests the exposed filter works.
   *
   * There is an exposed filter on the title field which takes a title query
   * parameter. This is set to filter nodes by those whose title starts with
   * the value provided.
   */
  public function testRestViewExposedFilter(): void {
    $this->drupalCreateContentType(['type' => 'page']);
    $node0 = $this->drupalCreateNode(['title' => 'Node 1']);
    $node1 = $this->drupalCreateNode(['title' => 'Node 11']);
    $node2 = $this->drupalCreateNode(['title' => 'Node 111']);

    // Test that no filter brings back all three nodes.
    $result = Json::decode($this->drupalGet('test/serialize/node-exposed-filter', ['query' => ['_format' => 'json']]));

    $expected = [
      0 => [
        'nid' => $node0->id(),
        'body' => (string) $node0->body->processed,
      ],
      1 => [
        'nid' => $node1->id(),
        'body' => (string) $node1->body->processed,
      ],
      2 => [
        'nid' => $node2->id(),
        'body' => (string) $node2->body->processed,
      ],
    ];

    $this->assertSame($expected, $result, 'Querying a view with no exposed filter returns all nodes.');

    // Test that title starts with 'Node 11' query finds 2 of the 3 nodes.
    $result = Json::decode(
      $this->drupalGet('test/serialize/node-exposed-filter', [
        'query' => [
          '_format' => 'json',
          'title' => 'Node 11',
        ],
      ])
    );

    $expected = [
      0 => [
        'nid' => $node1->id(),
        'body' => (string) $node1->body->processed,
      ],
      1 => [
        'nid' => $node2->id(),
        'body' => (string) $node2->body->processed,
      ],
    ];

    $cache_contexts = [
      'languages:language_content',
      'languages:language_interface',
      'theme',
      'request_format',
      'user.node_grants:view',
      'url',
    ];

    $this->assertSame($expected, $result, 'Querying a view with a starts with exposed filter on the title returns nodes whose title starts with value provided.');
    $this->assertCacheContexts($cache_contexts);
  }

  /**
   * Tests multilingual entity rows.
   */
  public function testMulEntityRows(): void {
    // Create some languages.
    ConfigurableLanguage::createFromLangcode('l1')->save();
    ConfigurableLanguage::createFromLangcode('l2')->save();

    // Create an entity with no translations.
    $storage = \Drupal::entityTypeManager()->getStorage('entity_test_mul');
    $storage->create(['langcode' => 'l1', 'name' => 'mul-none'])->save();

    // Create some entities with translations.
    $entity = $storage->create(['langcode' => 'l1', 'name' => 'mul-l1-orig']);
    $entity->save();
    $entity->addTranslation('l2', ['name' => 'mul-l1-l2'])->save();
    $entity = $storage->create(['langcode' => 'l2', 'name' => 'mul-l2-orig']);
    $entity->save();
    $entity->addTranslation('l1', ['name' => 'mul-l2-l1'])->save();

    // Get the names of the output.
    $json = $this->drupalGet('test/serialize/translated_entity', ['query' => ['_format' => 'json']]);
    $decoded = $this->container->get('serializer')->decode($json, 'json');
    $names = [];
    foreach ($decoded as $item) {
      $names[] = $item['name'][0]['value'];
    }
    sort($names);

    // Check that the names are correct.
    $expected = ['mul-l1-l2', 'mul-l1-orig', 'mul-l2-l1', 'mul-l2-orig', 'mul-none'];
    $this->assertSame($expected, $names, 'The translated content was found in the JSON.');
  }

  /**
   * Tests the pager option in REST export.
   */
  public function testRestViewsPager(): void {
    $this->drupalCreateContentType(['type' => 'page']);

    // Create multiple nodes for pagination testing.
    $nodes = [];
    for ($i = 0; $i < 10; $i++) {
      $nodes[] = $this->drupalCreateNode(['title' => 'Node ' . $i]);
    }

    // Load the view and configure it with a pager.
    $view = Views::getView('test_serializer_node_display_field');
    $view->setDisplay('rest_export_1');

    // Set pager to show 5 items per page.
    $view->displayHandlers->get('rest_export_1')->overrideOption('pager', [
      'type' => 'full',
      'options' => ['items_per_page' => 5],
    ]);

    // Enable the pager option in the style plugin.
    $view->displayHandlers->get('rest_export_1')->overrideOption('style', [
      'type' => 'serializer',
      'options' => [
        'formats' => ['json'],
        'pager' => TRUE,
      ],
    ]);
    $view->save();

    // Request the first page.
    $result = Json::decode($this->drupalGet('test/serialize/node-field', ['query' => ['_format' => 'json']]));

    // Verify pager structure exists in the response.
    $this->assertArrayHasKey('rows', $result, 'Response contains rows key.');
    $this->assertArrayHasKey('pager', $result, 'Response contains pager key.');
    $this->assertCount(5, $result['rows'], 'First page contains 5 items.');

    // Verify pager information is correct.
    $this->assertEquals(0, $result['pager']['current_page'], 'Current page is 0.');
    $this->assertEquals(10, $result['pager']['total_items'], 'Total items is 10.');
    $this->assertEquals(2, $result['pager']['total_pages'], 'Total pages is 2.');
    $this->assertEquals(5, $result['pager']['items_per_page'], 'Items per page is 5.');

    // Request the second page.
    $result = Json::decode($this->drupalGet('test/serialize/node-field', ['query' => ['_format' => 'json', 'page' => 1]]));

    // Verify second page pager info.
    $this->assertArrayHasKey('pager', $result, 'Second page response contains pager key.');
    $this->assertEquals(1, $result['pager']['current_page'], 'Current page is 1.');
    $this->assertCount(5, $result['rows'], 'Second page contains 5 items.');
    $this->assertEquals(10, $result['pager']['total_items'], 'Total items is consistent on second page.');
    $this->assertEquals(2, $result['pager']['total_pages'], 'Total pages is consistent on second page.');
    $this->assertEquals(5, $result['pager']['items_per_page'], 'Items per page is consistent on second page.');
  }

  /**
   * Data provider for testRestViewsPagerTotalItemsConsistency.
   */
  public static function pagerTotalItemsProvider(): array {
    return [
      'full pager, 7 nodes, 3 per page' => [
        'pager_type' => 'full',
        'node_count' => 7,
        'items_per_page' => 3,
      ],
      'full pager, 10 nodes, 5 per page' => [
        'pager_type' => 'full',
        'node_count' => 10,
        'items_per_page' => 5,
      ],
      'some pager, 7 nodes, 3 per page' => [
        'pager_type' => 'some',
        'node_count' => 7,
        'items_per_page' => 3,
      ],
      'some pager, 10 nodes, 5 per page' => [
        'pager_type' => 'some',
        'node_count' => 10,
        'items_per_page' => 5,
      ],
    ];
  }

  /**
   * Tests that pager metadata is consistent across paginated requests.
   *
   * Verifies that total_items, total_pages, and current_page are correct
   * regardless of which page is requested and whether the page parameter is
   * present. Covers both pagers with a count query ("full") and without
   * ("some").
   *
   * @dataProvider pagerTotalItemsProvider
   */
  public function testRestViewsPagerTotalItemsConsistency(string $pager_type, int $node_count, int $items_per_page): void {
    $this->drupalCreateContentType(['type' => 'page']);

    for ($i = 0; $i < $node_count; $i++) {
      $this->drupalCreateNode(['title' => 'Node ' . $i]);
    }

    $view = Views::getView('test_serializer_node_display_field');
    $view->setDisplay('rest_export_1');

    $view->displayHandlers->get('rest_export_1')->overrideOption('pager', [
      'type' => $pager_type,
      'options' => ['items_per_page' => $items_per_page],
    ]);
    $view->displayHandlers->get('rest_export_1')->overrideOption('style', [
      'type' => 'serializer',
      'options' => [
        'formats' => ['json'],
        'pager' => TRUE,
      ],
    ]);
    $view->save();

    $expected_pages = (int) ceil($node_count / $items_per_page);

    // Request without page parameter (implicit page 0).
    $result_without_page = Json::decode($this->drupalGet('test/serialize/node-field', ['query' => ['_format' => 'json']]));

    // Request with explicit page=1.
    $result_with_page = Json::decode($this->drupalGet('test/serialize/node-field', ['query' => ['_format' => 'json', 'page' => 1]]));

    // total_items and total_pages must be identical regardless of the page
    // parameter. This catches bugs where non-count-query pagers report the
    // current page's row count instead of the real total.
    $this->assertSame($result_without_page['pager']['total_items'], $result_with_page['pager']['total_items'], 'total_items must be the same with and without page parameter.');
    $this->assertSame($result_without_page['pager']['total_pages'], $result_with_page['pager']['total_pages'], 'total_pages must be the same with and without page parameter.');

    // total_items must equal the actual node count, not items_per_page.
    $this->assertNotEquals($items_per_page, $result_without_page['pager']['total_items'], 'total_items must not equal items_per_page.');
    $this->assertEquals($node_count, $result_without_page['pager']['total_items']);
    $this->assertEquals($node_count, $result_with_page['pager']['total_items']);
    $this->assertEquals($expected_pages, $result_without_page['pager']['total_pages']);

    // current_page must reflect the requested page.
    $this->assertEquals(0, $result_without_page['pager']['current_page']);
    $this->assertEquals(1, $result_with_page['pager']['current_page']);

    // Cross-check: the sum of rows across all pages must equal total_items.
    $total_rows = 0;
    for ($page = 0; $page < $expected_pages; $page++) {
      $result = Json::decode($this->drupalGet('test/serialize/node-field', ['query' => ['_format' => 'json', 'page' => $page]]));
      $total_rows += count($result['rows']);
    }
    $this->assertEquals($node_count, $total_rows, 'Sum of rows across all pages equals total_items.');
  }

  /**
   * Tests that pager option is disabled by default.
   */
  public function testRestViewsPagerDisabledByDefault(): void {
    $this->drupalCreateContentType(['type' => 'page']);

    // Create a few nodes.
    for ($i = 0; $i < 3; $i++) {
      $this->drupalCreateNode(['title' => 'Node ' . $i]);
    }

    // Load the view without enabling the pager option.
    $view = Views::getView('test_serializer_node_display_field');
    $view->setDisplay('rest_export_1');

    // Set a pager but don't enable the pager style option.
    $view->displayHandlers->get('rest_export_1')->overrideOption('pager', [
      'type' => 'full',
      'options' => ['items_per_page' => 10],
    ]);
    $view->save();

    // Request the view.
    $result = Json::decode($this->drupalGet('test/serialize/node-field', ['query' => ['_format' => 'json']]));

    // Verify that the response is a plain array without pager wrapper.
    $this->assertArrayNotHasKey('rows', $result, 'Response does not contain rows key when pager option is disabled.');
    $this->assertArrayNotHasKey('pager', $result, 'Response does not contain pager key when pager option is disabled.');
    $this->assertIsArray($result, 'Response is an array.');
    $this->assertCount(3, $result, 'Response contains all 3 items.');
  }

}
