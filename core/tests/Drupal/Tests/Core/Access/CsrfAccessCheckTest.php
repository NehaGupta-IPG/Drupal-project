<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\CsrfAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests Drupal\Core\Access\CsrfAccessCheck.
 */
#[CoversClass(CsrfAccessCheck::class)]
#[Group('Access')]
class CsrfAccessCheckTest extends UnitTestCase {

  /**
   * The mock CSRF token generator.
   */
  protected CsrfTokenGenerator $csrfToken;

  /**
   * The access checker.
   */
  protected CsrfAccessCheck $accessCheck;

  /**
   * The mock route match.
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * The stub parameter bag.
   */
  protected ParameterBagInterface $parameterBag;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $sessionConfiguration;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->csrfToken = $this->getMockBuilder(CsrfTokenGenerator::class)
      ->disableOriginalConstructor()
      ->getMock();

    $this->parameterBag = $this->createStub(ParameterBagInterface::class);

    $this->routeMatch = $this->createMock(RouteMatchInterface::class);

    $this->sessionConfiguration = $this->createMock(SessionConfigurationInterface::class);
    $this->accessCheck = new CsrfAccessCheck($this->csrfToken, $this->sessionConfiguration);
  }

  /**
   * Tests the access() method with a valid token.
   */
  public function testAccessTokenPass(): void {
    $this->sessionConfiguration->expects($this->once())
      ->method('hasSession')
      ->willReturn(TRUE);

    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with('test_query', 'test-path/42')
      ->willReturn(TRUE);

    $this->parameterBag
      ->method('all')
      ->willReturn(['node' => 42]);

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->willReturn($this->parameterBag);

    $route = new Route('/test-path/{node}', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path/42?token=test_query');

    $this->assertEquals(AccessResult::allowed()->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

  /**
   * Tests csrf token invalid.
   *
   * @legacy-covers ::access
   * Tests the access() method for anonymous users.
   */
  public function testAccessTokenAnonymousPass() {
    $this->sessionConfiguration->expects($this->once())
      ->method('hasSession')
      ->willReturn(FALSE);

    $this->parameterBag
      ->method('all')
      ->willReturn(['node' => 42]);

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->willReturn($this->parameterBag);

    $route = new Route('/test-path/{node}', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path/42?token=test_query');
    $this->csrfToken->expects($this->never())
      ->method('validate');
    $this->assertEquals(AccessResult::allowed()->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

  /**
   * @legacy-covers  ::access
   */
  public function testCsrfTokenInvalid(): void {
    $this->sessionConfiguration->expects($this->once())
      ->method('hasSession')
      ->willReturn(TRUE);

    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with('test_query', 'test-path')
      ->willReturn(FALSE);

    $this->parameterBag
      ->method('all')
      ->willReturn([]);

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->willReturn($this->parameterBag);

    $route = new Route('/test-path', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path?token=test_query');

    $this->assertEquals(AccessResult::forbidden("'csrf_token' URL query argument is invalid.")->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

  /**
   * Tests csrf token missing.
   *
   * @legacy-covers ::access
   */
  public function testCsrfTokenMissing(): void {
    $this->sessionConfiguration->expects($this->once())
      ->method('hasSession')
      ->willReturn(TRUE);

    $this->csrfToken->expects($this->once())
      ->method('validate')
      ->with('', 'test-path')
      ->willReturn(FALSE);

    $this->parameterBag
      ->method('all')
      ->willReturn([]);

    $this->routeMatch->expects($this->once())
      ->method('getRawParameters')
      ->willReturn($this->parameterBag);

    $route = new Route('/test-path', [], ['_csrf_token' => 'TRUE']);
    $request = Request::create('/test-path');
    $this->assertEquals(AccessResult::forbidden("'csrf_token' URL query argument is missing.")->setCacheMaxAge(0), $this->accessCheck->access($route, $request, $this->routeMatch));
  }

}
