<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme\Icon;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconSearch;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Controller\IconAutocompleteController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the IconAutocompleteController.
 */
#[CoversClass(IconAutocompleteController::class)]
#[Group('icon')]
#[RunTestsInSeparateProcesses]
class IconAutocompleteControllerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The controller under test.
   *
   * @var \Drupal\system\Controller\IconAutocompleteController
   */
  protected IconAutocompleteController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->controller = IconAutocompleteController::create($this->container);
  }

  /**
   * Tests the handleSearchIcons method with empty query.
   */
  public function testHandleSearchIconsEmptyQuery(): void {
    $request = new Request();
    $request->query->set('q', '');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEmpty($data);
  }

  /**
   * Rebuilds the controller with a mocked icon pack manager.
   *
   * Replaces both plugin.manager.icon_pack and icon.search in the container so
   * that the controller's IconSearch dependency uses the mock. Also replaces the
   * renderer to avoid requiring real icon templates.
   */
  private function rebuildControllerWithMock(IconPackManagerInterface $icon_pack_manager): void {
    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);
    $cache = $this->container->get('cache.default');
    $this->container->set('icon.search', new IconSearch($icon_pack_manager, $cache));
    // Use a concrete implementation instead of a PHPUnit mock to avoid notices
    // from mocking methods that take by-reference parameters.
    $renderer = new class() implements RendererInterface {

      public function renderRoot(&$elements) {
        return Markup::create('');
      }

      public function renderInIsolation(&$elements) {
        return Markup::create('');
      }

      public function renderPlaceholder($placeholder, array $elements) {
        return Markup::create('');
      }

      public function render(array &$elements) {
        return Markup::create('');
      }

      public function hasRenderContext() {
        return FALSE;
      }

      public function executeInRenderContext(RenderContext $context, callable $callable) {
        return $callable();
      }

      public function mergeBubbleableMetadata(array $a, array $b) {
        return BubbleableMetadata::mergeAttachments($a, $b);
      }

      public function addCacheableDependency(array &$elements, $dependency) {}

    };
    $this->container->set('renderer', $renderer);
    $this->controller = IconAutocompleteController::create($this->container);
  }

  /**
   * Tests the handleSearchIcons method with query.
   */
  public function testHandleSearchIconsWithQuery(): void {
    $test_icons = [
      'core:add' => ['source' => 'add.svg'],
      'core:add-new' => ['source' => 'add-new.svg'],
    ];

    $icon_pack_manager = $this->createStub(IconPackManagerInterface::class);
    $icon = $this->createStub('Drupal\Core\Theme\Icon\IconDefinitionInterface');
    $icon_pack_manager->method('getIcons')->willReturn($test_icons);
    $icon_pack_manager->method('getIcon')->willReturn($icon);

    $this->rebuildControllerWithMock($icon_pack_manager);

    $request = new Request();
    $request->query->set('q', 'add');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);

    $this->assertNotEmpty($data);
    $this->assertIsArray($data);

    // Check that results have required keys.
    foreach ($data as $item) {
      $this->assertArrayHasKey('value', $item);
      $this->assertArrayHasKey('label', $item);
    }
  }

  /**
   * Tests the handleSearchIcons method with allowed_icon_pack parameter.
   */
  public function testHandleSearchIconsWithAllowedPacks(): void {
    $icon_pack_manager = $this->getMockBuilder(IconPackManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();

    $test_icons = [
      'core:add' => ['source' => 'add.svg'],
    ];

    $icon = $this->createStub('Drupal\Core\Theme\Icon\IconDefinitionInterface');

    // Expect getIcons to be called with the allowed packs.
    $icon_pack_manager->expects($this->once())
      ->method('getIcons')
      ->with(['core', 'test'])
      ->willReturn($test_icons);
    $icon_pack_manager->method('getIcon')->willReturn($icon);

    $this->rebuildControllerWithMock($icon_pack_manager);

    $request = new Request();
    $request->query->set('q', 'add');
    $request->query->set('allowed_icon_pack', 'core+test');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertSame(200, $response->getStatusCode());
  }

  /**
   * Tests the handleSearchIcons method with max_result parameter.
   */
  public function testHandleSearchIconsWithMaxResult(): void {
    $test_icons = [];
    for ($i = 0; $i < 100; $i++) {
      $test_icons["core:icon-$i"] = ['source' => "icon-$i.svg"];
    }

    $icon_pack_manager = $this->createStub(IconPackManagerInterface::class);
    $icon = $this->createStub('Drupal\Core\Theme\Icon\IconDefinitionInterface');
    $icon_pack_manager->method('getIcons')->willReturn($test_icons);
    $icon_pack_manager->method('getIcon')->willReturn($icon);

    $this->rebuildControllerWithMock($icon_pack_manager);

    $request = new Request();
    $request->query->set('q', 'icon');
    $request->query->set('max_result', '10');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);

    // Should be limited to 10 results.
    $this->assertCount(10, $data);
  }

  /**
   * Tests the handleSearchIcons method with whitespace in query.
   */
  public function testHandleSearchIconsTrimsWhitespace(): void {
    $test_icons = [
      'core:add' => ['source' => 'add.svg'],
    ];

    $icon_pack_manager = $this->createStub(IconPackManagerInterface::class);
    $icon = $this->createStub('Drupal\Core\Theme\Icon\IconDefinitionInterface');
    $icon_pack_manager->method('getIcons')->willReturn($test_icons);
    $icon_pack_manager->method('getIcon')->willReturn($icon);

    $this->rebuildControllerWithMock($icon_pack_manager);

    $request = new Request();
    $request->query->set('q', '  add  ');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);

    $this->assertNotEmpty($data);
  }

  /**
   * Tests the handleSearchIcons method with short query.
   */
  public function testHandleSearchIconsShortQuery(): void {
    $request = new Request();
    // Query less than minimum length (2 characters).
    $request->query->set('q', 'a');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertSame(200, $response->getStatusCode());
    $data = json_decode($response->getContent(), TRUE);

    // Should return empty array for short queries.
    $this->assertEmpty($data);
  }

  /**
   * Tests the controller can be instantiated from the container.
   */
  public function testControllerInstantiationFromContainer(): void {
    $controller = IconAutocompleteController::create($this->container);
    $response = $controller->handleSearchIcons(new Request());
    $this->assertSame(200, $response->getStatusCode());
  }

}
