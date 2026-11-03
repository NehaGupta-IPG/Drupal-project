<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that response messages are safe HTML, escaped exactly once.
 *
 * The client shows a response message without escaping it, so the server
 * escapes user-controlled parts (titles, user names) once.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeMessageEscapingTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'link',
    'menu_link_content',
    'menu_tree_ui',
    'treegrid',
    'treegrid_reorder',
    'system',
    'user',
  ];

  /**
   * Plugin IDs of the two links, keyed A and B.
   *
   * @var array<string, string>
   */
  private array $ids = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('menu_link_content');
    $this->installEntitySchema('user');
    $this->installConfig(['system']);

    $request = Request::create('/');
    $request->setSession(new Session(new MockArraySessionStorage()));
    $request->server->set('REQUEST_TIME', 1234567890);
    $this->container->get('request_stack')->push($request);
    $this->setUpCurrentUser(['uid' => 1]);

    Menu::create(['id' => 'test', 'label' => 'Test'])->save();
    $this->container->get('plugin.manager.menu.link')->rebuild();
  }

  /**
   * The lock owner's name is escaped in the locked message.
   */
  public function testLockOwnerNameIsEscaped(): void {
    $this->createLinks('A', 'B');
    $owner = User::load(1);
    $owner->setUsername('<b>x</b>')->save();
    $this->container->get('menu_tree_ui.working_copy')->addOp('test', [
      'type' => 'move',
      'id' => $this->ids['A'],
      'new_parent' => $this->ids['B'],
      'before_id' => NULL,
    ]);

    $this->setCurrentUser($this->createUser());
    $request = Request::create('/', 'POST', [], [], [], [], json_encode([
      'id' => $this->ids['B'],
      'new_parent' => '',
      'before_id' => $this->ids['A'],
    ]));
    $response = MenuTreeController::create($this->container)->move(Menu::load('test'), $request);

    $result = json_decode((string) $response->getContent(), TRUE);
    $this->assertSame('locked', $result['code']);
    $this->assertStringContainsString('&lt;b&gt;', $result['message']);
    $this->assertStringNotContainsString('<b>', $result['message']);
  }

  /**
   * A link title is escaped in the message of a move that fails validation.
   */
  public function testLinkTitleIsEscapedInFailedMoveMessage(): void {
    $this->createLinks('<b>x</b>', 'B');

    $result = $this->container->get('menu_tree_ui.mover')->commitWorkingCopy('test', [
      ['type' => 'move', 'id' => $this->ids['A'], 'new_parent' => $this->ids['A'], 'before_id' => NULL],
    ]);

    $this->assertFalse($result['ok']);
    $this->assertStringContainsString('&lt;b&gt;', (string) $result['message']);
    $this->assertStringNotContainsString('<b>', (string) $result['message']);
  }

  /**
   * Creates two links in the test menu and records their plugin IDs.
   */
  private function createLinks(string $title_a, string $title_b): void {
    foreach (['A' => $title_a, 'B' => $title_b] as $key => $title) {
      $entity = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => 'internal:/'],
        'menu_name' => 'test',
        'weight' => $key === 'A' ? 0 : 1,
      ]);
      $entity->save();
      $this->ids[$key] = $entity->getPluginId();
    }
  }

}
