<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Controller;

use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Controller\DbUpdateController;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Tests for DbUpdateController class.
 *
 * @coversDefaultClass \Drupal\system\Controller\DbUpdateController
 * @group legacy
 *
 * @package Drupal\Tests\system\Kernel\Controller
 */
class DbUpdateControllerTest extends KernelTestBase {

  /**
   * The keyvalue expirable factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirableFactory;

  /**
   * A cache backend interface.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The bare HTML page renderer.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy
   */
  protected $bareHtmlPageRenderer;

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The post update registry.
   *
   * @var \Drupal\Core\Update\UpdateRegistry
   */
  protected $postUpdateRegistry;

  /**
   * The asset query string.
   *
   * @var \Drupal\Core\Asset\AssetQueryStringInterface
   */
  protected $assetQueryStringInterface;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setup();

    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);

    $this->root = $this->container->getParameter('app.root');
    $this->keyValueExpirableFactory = $this->container->get('keyvalue.expirable');
    $this->cache = $this->container->get('cache.default');
    $this->state = $this->container->get('state');
    $this->moduleHandler = $this->container->get('module_handler');
    $this->account = $this->container->get('current_user');
    $this->postUpdateRegistry = $this->container->get('update.post_update_registry');
    $this->assetQueryStringInterface = $this->container->get('asset.query_string');

    $html_response = $this->prophesize(HtmlResponse::class);
    $this->bareHtmlPageRenderer = $this->prophesize(BareHtmlPageRendererInterface::class);
    $this->bareHtmlPageRenderer
      ->renderBarePage(Argument::any(), Argument::any(), Argument::any(), Argument::any())
      ->willReturn($html_response->reveal());
  }

  /**
   * Tests the handle method.
   *
   * @covers ::handle
   */
  public function testHandle(): void {
    $session = new Session();
    $session->start();
    $request = Request::createFromGlobals();
    $request->setSession($session);
    $request->getSession()->set('update_ignore_warnings', TRUE);

    $db_update_controller = new DbUpdateController($this->root, $this->keyValueExpirableFactory,
    $this->cache, $this->state, $this->moduleHandler, $this->account,
    $this->bareHtmlPageRenderer->reveal(), $this->postUpdateRegistry, $this->assetQueryStringInterface);

    $db_update_controller->handle('op', $request);
    $this->expectDeprecation('op is deprecated in drupal:11.1.0 and is removed from drupal:12.0.0. Rename $op to $operation arguments with BC usage in update.php. See https://www.drupal.org/node/1025928');
  }

}
