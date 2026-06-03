<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\system\MenuInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests MenuTreeController::columnUpdate() payload validation and delegation.
 *
 * Uses direct controller instantiation rather than real HTTP so we stay in the
 * kernel layer without a full browser stack. The controller is wired from the
 * DI container so all real dependencies are exercised.
 *
 * @group menu_tree_ui
 */
#[RunTestsInSeparateProcesses]
final class MenuTreeControllerColumnUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'system',
    'user',
  ];

  /**
   * @var \Drupal\menu_tree_ui\Controller\MenuTreeController
   */
  private MenuTreeController $controller;

  /**
   * The menu the controller operates on.
   *
   * @var \Drupal\system\MenuInterface
   */
  private MenuInterface $menu;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    // installConfig(['system']) already creates the 'main' menu — just load it.
    $this->menu = $this->container->get('entity_type.manager')
      ->getStorage('menu')
      ->load('main');

    $this->controller = MenuTreeController::create($this->container);
  }

  /**
   * A non-JSON body must return 400 with code 'bad_request'.
   */
  public function testMissingBodyReturns400(): void {
    $request = Request::create('/column-update', 'POST', [], [], [], [], '');
    $response = $this->controller->columnUpdate($this->menu, $request);
    $data = $this->decodeJson($response);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertFalse($data['ok']);
    $this->assertSame('bad_request', $data['code']);
  }

  /**
   * A valid JSON body with missing column_id/link_id must return 400.
   */
  public function testMissingFieldsReturns400(): void {
    $request = $this->jsonRequest(['column_id' => '', 'link_id' => '', 'value' => TRUE]);
    $response = $this->controller->columnUpdate($this->menu, $request);
    $data = $this->decodeJson($response);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('bad_request', $data['code']);
  }

  /**
   * An unknown column ID must return 400 with code 'unknown_column'.
   */
  public function testUnknownColumnIdReturns400(): void {
    $request = $this->jsonRequest([
      'column_id' => 'nonexistent_column_xyz',
      'link_id' => 'menu_link_content:some-uuid',
      'value' => TRUE,
    ]);
    $response = $this->controller->columnUpdate($this->menu, $request);
    $data = $this->decodeJson($response);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('unknown_column', $data['code']);
  }

  /**
   * A static/yml link ID (no menu_link_content: prefix) must return 400.
   */
  public function testNonContentLinkReturns400(): void {
    $request = $this->jsonRequest([
      'column_id' => 'expanded',
      'link_id' => 'system.admin',
      'value' => TRUE,
    ]);
    $response = $this->controller->columnUpdate($this->menu, $request);
    $data = $this->decodeJson($response);

    $this->assertSame(400, $response->getStatusCode());
    $this->assertSame('not_updateable', $data['code']);
  }

  /**
   * A valid payload with a real entity must return 200 and persist the update.
   */
  public function testValidToggleReturns200AndPersists(): void {
    $entity = MenuLinkContent::create([
      'title' => 'Test link',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'main',
      'expanded' => FALSE,
    ]);
    $entity->save();

    $plugin_id = 'menu_link_content:' . $entity->uuid();
    $request = $this->jsonRequest([
      'column_id' => 'expanded',
      'link_id' => $plugin_id,
      'value' => TRUE,
    ]);
    $response = $this->controller->columnUpdate($this->menu, $request);
    $data = $this->decodeJson($response);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($data['ok']);

    // Verify the entity was actually updated.
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('menu_link_content');
    $storage->resetCache([$entity->id()]);
    $reloaded = $storage->load($entity->id());
    $this->assertTrue((bool) $reloaded->get('expanded')->value);
  }

  /**
   * A UUID that doesn't exist in the given menu must return 404.
   */
  public function testUnknownUuidReturns404(): void {
    $request = $this->jsonRequest([
      'column_id' => 'expanded',
      'link_id' => 'menu_link_content:00000000-0000-0000-0000-000000000000',
      'value' => TRUE,
    ]);
    $response = $this->controller->columnUpdate($this->menu, $request);
    $data = $this->decodeJson($response);

    $this->assertSame(404, $response->getStatusCode());
    $this->assertSame('not_found', $data['code']);
  }

  // Helpers.

  /**
   * Builds a POST Request with a JSON body.
   */
  private function jsonRequest(array $payload): Request {
    return Request::create(
      '/column-update',
      'POST',
      [],
      [],
      [],
      ['CONTENT_TYPE' => 'application/json'],
      json_encode($payload),
    );
  }

  /**
   * Decodes a JSON response and asserts the body is a valid array.
   */
  private function decodeJson(JsonResponse $response): array {
    $data = json_decode($response->getContent(), TRUE);
    $this->assertIsArray($data);
    return $data;
  }

}
