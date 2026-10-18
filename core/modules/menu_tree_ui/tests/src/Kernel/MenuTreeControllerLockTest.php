<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Lock\DatabaseLockBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_tree_ui\Controller\MenuTreeController;
use Drupal\system\Entity\Menu;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that record, Save and Cancel are serialized by the menu's lock.
 */
#[Group('menu_tree_ui')]
#[RunTestsInSeparateProcesses]
final class MenuTreeControllerLockTest extends KernelTestBase {

  use UserCreationTrait;

  private const string LOCK_NAME = 'menu_tree_ui.working_copy:test';

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
   * The working copy of the test menu.
   */
  private TreegridWorkingCopy $workingCopy;

  /**
   * Plugin IDs of the two links in the test menu.
   *
   * @var string[]
   */
  private array $ids = [];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // KernelTestBase registers a lock backend that always acquires.
    $container->register('lock', DatabaseLockBackend::class)
      ->addArgument(new Reference('database'));
  }

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

    foreach (['Lapwing', 'Redshank'] as $weight => $title) {
      $entity = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => 'internal:/'],
        'menu_name' => 'test',
        'weight' => $weight,
      ]);
      $entity->save();
      $this->ids[] = $entity->getPluginId();
    }

    $this->workingCopy = $this->container->get('menu_tree_ui.working_copy');
  }

  /**
   * Record, Save and Cancel are refused while another process holds the lock.
   */
  public function testEndpointsRefuseWhileLockHeld(): void {
    [$a, $b] = $this->ids;
    $this->assertTrue($this->workingCopy->addOp('test', [
      'type' => 'move',
      'id' => $a,
      'new_parent' => $b,
      'before_id' => NULL,
    ]));

    $other_process = new DatabaseLockBackend($this->container->get('database'));
    $this->assertTrue($other_process->acquire(self::LOCK_NAME));
    $controller = MenuTreeController::create($this->container);
    $menu = Menu::load('test');

    $move_request = Request::create('/', 'POST', [], [], [], [], json_encode([
      'id' => $b,
      'new_parent' => '',
      'before_id' => $a,
    ]));
    foreach ([
      'move' => $controller->move($menu, $move_request),
      'save' => $controller->save($menu),
      'cancel' => $controller->cancel($menu),
    ] as $action => $response) {
      $this->assertSame(409, $response->getStatusCode(), $action);
      $result = json_decode((string) $response->getContent(), TRUE);
      $this->assertFalse($result['ok'], $action);
      $this->assertSame('busy', $result['code'], $action);
    }

    $plugin_manager = $this->container->get('plugin.manager.menu.link');
    $this->assertSame('', $plugin_manager->getDefinition($a)['parent']);
    $ops = $this->workingCopy->getOps('test');
    $this->assertCount(1, $ops);
    $this->assertSame($a, $ops[0]['id']);

    // Once the other process lets go, Save works and releases the lock again.
    $other_process->release(self::LOCK_NAME);
    $result = json_decode((string) $controller->save($menu)->getContent(), TRUE);
    $this->assertTrue($result['ok'], $result['message'] ?? '');
    $this->assertSame($b, $plugin_manager->getDefinition($a)['parent']);
    $this->assertTrue($other_process->acquire(self::LOCK_NAME));
  }

}
