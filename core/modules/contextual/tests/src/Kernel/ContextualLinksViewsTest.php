<?php

declare(strict_types=1);

namespace Drupal\Tests\contextual\Kernel;

use Drupal\contextual\ContextualLinksSerializer;
use Drupal\Core\Render\RenderContext;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the contextual links views field and area handlers.
 *
 * @see \Drupal\contextual\Plugin\views\field\ContextualLinks
 * @see \Drupal\contextual\Plugin\views\area\ContextualLinks
 */
#[Group('contextual')]
#[RunTestsInSeparateProcesses]
class ContextualLinksViewsTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['contextual'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_view'];

  /**
   * Tests the contextual links field handler.
   */
  public function testContextualLinksFieldHandler(): void {
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a field with a link that will be used as contextual link source.
    $view->displayHandlers->get('default')->overrideOption('fields', [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
        'plugin_id' => 'standard',
        'alter' => [
          'path' => '/node/1/edit',
        ],
      ],
      'contextual_links' => [
        'id' => 'contextual_links',
        'table' => 'views',
        'field' => 'contextual_links',
        'relationship' => 'none',
        'plugin_id' => 'contextual_links',
        'fields' => [
          'name' => 'name',
        ],
      ],
    ]);

    $this->executeView($view);

    $contextual_field = $view->field['contextual_links'];
    $this->assertNotNull($contextual_field);

    $row = $view->result[0];
    $view->row_index = $row->index;

    // Use executeInRenderContext() to provide proper render context.
    $output = $renderer->executeInRenderContext(new RenderContext(), function () use ($view, $row) {
      // First render the name field so last_render is populated.
      $view->field['name']->advancedRender($row);
      // Then render the contextual links field.
      return $view->field['contextual_links']->render($row);
    });

    // The output should contain a contextual links placeholder.
    $this->assertStringContainsString('data-contextual-id', (string) $output);
  }

  /**
   * Tests the contextual links field handler with no links.
   */
  public function testContextualLinksFieldHandlerEmpty(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a field without a link path.
    $view->displayHandlers->get('default')->overrideOption('fields', [
      'name' => [
        'id' => 'name',
        'table' => 'views_test_data',
        'field' => 'name',
        'relationship' => 'none',
        'plugin_id' => 'standard',
        // No alter path configured.
      ],
      'contextual_links' => [
        'id' => 'contextual_links',
        'table' => 'views',
        'field' => 'contextual_links',
        'relationship' => 'none',
        'plugin_id' => 'contextual_links',
        'fields' => [
          'name' => 'name',
        ],
      ],
    ]);

    $this->executeView($view);

    $contextual_field = $view->field['contextual_links'];

    $row = $view->result[0];
    $view->row_index = $row->index;
    // First render the name field so last_render is populated.
    $view->field['name']->advancedRender($row);
    $output = $contextual_field->render($row);

    // The output should be empty when there are no valid links.
    $this->assertEmpty($output);
  }

  /**
   * Tests the contextual links area handler.
   */
  public function testContextualLinksAreaHandler(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a contextual links area handler with HTML links.
    $view->displayHandlers->get('default')->overrideOption('header', [
      'contextual_links' => [
        'id' => 'contextual_links',
        'table' => 'views',
        'field' => 'contextual_links',
        'content' => '<a href="/node/1/edit">Edit</a><a href="/node/1/delete">Delete</a>',
      ],
    ]);

    $this->executeView($view);

    $area = $view->display_handler->handlers['header']['contextual_links'];
    $this->assertNotNull($area);

    $build = $area->render();

    // The output should be a contextual links placeholder.
    $this->assertIsArray($build);
    $this->assertEquals('contextual_links_placeholder', $build['#type']);
    $this->assertNotEmpty($build['#id']);
  }

  /**
   * Tests the contextual links area handler with empty content.
   */
  public function testContextualLinksAreaHandlerEmpty(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a contextual links area handler with no links.
    $view->displayHandlers->get('default')->overrideOption('header', [
      'contextual_links' => [
        'id' => 'contextual_links',
        'table' => 'views',
        'field' => 'contextual_links',
        'content' => 'Just some text without links',
      ],
    ]);

    $this->executeView($view);

    $area = $view->display_handler->handlers['header']['contextual_links'];

    $build = $area->render();

    // The output should be an empty array when there are no links.
    $this->assertEquals([], $build);
  }

  /**
   * Tests the contextual links area handler with empty results.
   */
  public function testContextualLinksAreaHandlerEmptyResults(): void {
    $view = Views::getView('test_view');
    $view->setDisplay();

    // Add a contextual links area handler.
    $view->displayHandlers->get('default')->overrideOption('header', [
      'contextual_links' => [
        'id' => 'contextual_links',
        'table' => 'views',
        'field' => 'contextual_links',
        'content' => '<a href="/node/1/edit">Edit</a>',
        'empty' => FALSE,
      ],
    ]);

    $this->executeView($view);

    $area = $view->display_handler->handlers['header']['contextual_links'];

    // Render the area with the empty flag (simulating no results).
    $build = $area->render(TRUE);

    // The output should be empty when there are no results and the empty
    // option is not set.
    $this->assertEquals([], $build);
  }

  /**
   * Tests that literal links round-trip through the serializer and element.
   */
  public function testContextualLinksElementLiteralLinks(): void {
    /** @var \Drupal\contextual\ContextualLinksSerializer $serializer */
    $serializer = $this->container->get(ContextualLinksSerializer::class);
    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = $this->container->get('renderer');

    $contextual_links = [
      'contextual' => [
        'route_parameters' => [
          'test_view__default__name' => [
            'title' => 'Edit this item',
            'path' => '/node/1/edit',
            'options' => ['query' => ['destination' => '/some-view']],
          ],
        ],
      ],
    ];

    // Round-trip the links through the serializer, as happens between the
    // views handlers and \Drupal\contextual\ContextualController::render().
    $id = $serializer->linksToId($contextual_links);
    $element = [
      '#type' => 'contextual_links',
      '#contextual_links' => $serializer->idToLinks($id),
    ];
    $output = (string) $renderer->renderInIsolation($element);

    $this->assertStringContainsString('Edit this item', $output);
    $this->assertStringContainsString('/node/1/edit', $output);
    $this->assertStringContainsString('destination=/some-view', $output);
  }

}
