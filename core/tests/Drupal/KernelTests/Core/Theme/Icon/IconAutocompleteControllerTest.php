<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Theme\Icon;

use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Controller\IconAutocompleteController;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the IconAutocompleteController.
 *
 * @group Theme
 * @group Icon
 *
 * @coversDefaultClass \Drupal\system\Controller\IconAutocompleteController
 */
#[Group('Theme')]
#[Group('Icon')]
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
  protected $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $icon_search = $this->container->get('icon.search');
    $this->controller = new IconAutocompleteController($icon_search);
  }

  /**
   * Tests the handleSearchIcons method with empty query.
   *
   * @covers ::handleSearchIcons
   */
  public function testHandleSearchIconsEmptyQuery(): void {
    $request = new Request();
    $request->query->set('q', '');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);
    $this->assertEmpty($data);
  }

  /**
   * Tests the handleSearchIcons method with query.
   *
   * @covers ::handleSearchIcons
   */
  public function testHandleSearchIconsWithQuery(): void {
    // Mock the icon pack manager to return test icons.
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $test_icons = [
      'core:add' => ['source' => 'add.svg'],
      'core:add-new' => ['source' => 'add-new.svg'],
    ];

    $icon_pack_manager->method('getIcons')
      ->willReturn($test_icons);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    // Rebuild the controller with the new service.
    $icon_search = $this->container->get('icon.search');
    $this->controller = new IconAutocompleteController($icon_search);

    $request = new Request();
    $request->query->set('q', 'add');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
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
   *
   * @covers ::handleSearchIcons
   */
  public function testHandleSearchIconsWithAllowedPacks(): void {
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $test_icons = [
      'core:add' => ['source' => 'add.svg'],
    ];

    // Expect getIcons to be called with the allowed packs.
    $icon_pack_manager->expects($this->once())
      ->method('getIcons')
      ->with(['core', 'test'])
      ->willReturn($test_icons);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $icon_search = $this->container->get('icon.search');
    $this->controller = new IconAutocompleteController($icon_search);

    $request = new Request();
    $request->query->set('q', 'add');
    $request->query->set('allowed_icon_pack', 'core+test');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
  }

  /**
   * Tests the handleSearchIcons method with max_result parameter.
   *
   * @covers ::handleSearchIcons
   */
  public function testHandleSearchIconsWithMaxResult(): void {
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $test_icons = [];
    for ($i = 0; $i < 100; $i++) {
      $test_icons["core:icon-$i"] = ['source' => "icon-$i.svg"];
    }

    $icon_pack_manager->method('getIcons')
      ->willReturn($test_icons);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $icon_search = $this->container->get('icon.search');
    $this->controller = new IconAutocompleteController($icon_search);

    $request = new Request();
    $request->query->set('q', 'icon');
    $request->query->set('max_result', '10');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);

    // Should be limited to 10 results.
    $this->assertCount(10, $data);
  }

  /**
   * Tests the handleSearchIcons method with whitespace in query.
   *
   * @covers ::handleSearchIcons
   */
  public function testHandleSearchIconsTrimsWhitespace(): void {
    $icon_pack_manager = $this->getMockBuilder('Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface')
      ->disableOriginalConstructor()
      ->getMock();

    $test_icons = [
      'core:add' => ['source' => 'add.svg'],
    ];

    $icon_pack_manager->method('getIcons')
      ->willReturn($test_icons);

    $this->container->set('plugin.manager.icon_pack', $icon_pack_manager);

    $icon_search = $this->container->get('icon.search');
    $this->controller = new IconAutocompleteController($icon_search);

    $request = new Request();
    $request->query->set('q', '  add  ');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);

    $this->assertNotEmpty($data);
  }

  /**
   * Tests the handleSearchIcons method with short query.
   *
   * @covers ::handleSearchIcons
   */
  public function testHandleSearchIconsShortQuery(): void {
    $request = new Request();
    // Query less than minimum length (2 characters).
    $request->query->set('q', 'a');

    $response = $this->controller->handleSearchIcons($request);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $data = json_decode($response->getContent(), TRUE);

    // Should return empty array for short queries.
    $this->assertEmpty($data);
  }

  /**
   * Tests the controller can be instantiated from the container.
   */
  public function testControllerInstantiationFromContainer(): void {
    $controller = IconAutocompleteController::create($this->container);
    $this->assertInstanceOf(IconAutocompleteController::class, $controller);
  }

}
