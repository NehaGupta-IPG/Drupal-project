<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\CsrfAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Access\RoutePathGenerationTrait;
use Drupal\Core\Access\RouteProcessorCsrf;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;

/**
 * Tests Route Path Generation Trait.
 */
#[Group('Access')]
#[CoversTrait(RoutePathGenerationTrait::class)]
class RoutePathGenerationTraitTest extends UnitTestCase {

  /**
   * The route processor.
   */
  protected RouteProcessorCsrf $processor;

  /**
   * The CSRF access checker.
   */
  protected CsrfAccessCheck $accessCheck;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $csrfToken = $this->createStub(CsrfTokenGenerator::class);
    // Make CsrfTokenGenerator mock use a simple hash of the value passed as
    // parameter, as it is enough for the sake of our tests.
    $csrfToken->method('get')->willReturnCallback(function ($value): string {
      return hash('sha256', $value);
    });
    $csrfToken->method('validate')->willReturnCallback(function ($token, $value): bool {
      return $token === hash('sha256', $value);
    });
    $this->processor = new RouteProcessorCsrf($csrfToken, $this->createStub(RequestStack::class));
    $this->accessCheck = new CsrfAccessCheck($csrfToken);
  }

  /**
   * Tests that CSRF token creation and validation is consistent.
   *
   * This checks that CsrfAccessCheck() and RouteProcessorCsrf() produce the
   * same results.
   *
   * Multiple cases are provided for an optional parameter (non-empty, empty,
   * null, undefined).
   */
  #[DataProvider('providerTestCsrfTokenCompleteLifeCycle')]
  public function testCsrfTokenCompleteLifeCycle(array $params): void {

    // Mock a route.
    $route = $this->createMock(Route::class);
    $route->expects($this->atLeastOnce())
      ->method('getPath')
      ->willReturn('test/example/{param}');
    $route->expects($this->atLeastOnce())
      ->method('hasRequirement')
      ->with('_csrf_token')
      ->willReturn(TRUE);

    // Process the route so the "token" param is generated.
    $routeParams = $params;
    $this->processor->processOutbound('test.example', $route, $routeParams);

    $requestParams = $params + ['token' => $routeParams['token']];

    // Mock Parameter bag.
    $parameterBag = $this->createStub(ParameterBagInterface::class);
    $parameterBag->method('get')->willReturnCallback(function ($key, $default = NULL) use ($requestParams) {
      return $requestParams[$key] ?? $default;
    });
    $parameterBag->method('all')->willReturn($requestParams);

    $request = new Request($requestParams);

    // Mock RouteMatch.
    $routeMatch = $this->createStub(RouteMatchInterface::class);
    $routeMatch->method('getRawParameters')->willReturn($parameterBag);

    // Check for allowed access.
    $this->assertInstanceOf(AccessResultAllowed::class, $this->accessCheck->access($route, $request, $routeMatch));
  }

  /**
   * Tests that CSRF token creation and validation is consistent.
   *
   * This checks that CsrfAccessCheck() and RouteProcessorCsrf() produce the
   * same results when our js parameter is excluded from the calculation.
   *
   * Multiple cases are provided for testing all possible (usual) combinations
   * of the excluded parameter.
   */
  #[DataProvider('providerTestCsrfTokenCompleteLifeCycleWithExcludedParams')]
  public function testCsrfTokenCompleteLifeCycleWithExcludedParams(array $params, string $js_route_param, string $js_request_param): void {
    // Mock a route.
    $route = $this->createMock(Route::class);
    $route->expects($this->atLeastOnce())
      ->method('getPath')
      ->willReturn('test/example/{param}/{js}');
    $route->expects($this->atLeastOnce())
      ->method('hasRequirement')
      ->with('_csrf_token')
      ->willReturn(TRUE);
    $route->expects($this->atLeastOnce())
      ->method('hasOption')
      ->with('_csrf_exclude_parameters')
      ->willReturn(TRUE);
    $route->expects($this->atLeastOnce())
      ->method('getOption')
      ->with('_csrf_exclude_parameters')
      ->willReturn(['js']);

    // Add our specified 'js' route param value to the common params.
    $routeParams = $params + ['js' => $js_route_param];
    // Process the route so the "token" param is generated.
    $this->processor->processOutbound('test.example', $route, $routeParams);

    // Add our specified 'js' request param value to the common params.
    $requestParams = $params + ['token' => $routeParams['token'], 'js' => $js_request_param];

    // Mock Parameter bag.
    $parameterBag = $this->createStub(ParameterBagInterface::class);
    $parameterBag->method('get')->willReturnCallback(function ($key, $default = NULL) use ($requestParams) {
      return $requestParams[$key] ?? $default;
    });
    $parameterBag->method('all')->willReturn($requestParams);

    // Get a real InputBag because it is a final class.
    $inputBag = new InputBag($requestParams);

    // Mock Request.
    $request = $this->createStub(Request::class);
    $request->query = $inputBag;

    // Mock RouteMatch.
    $routeMatch = $this->createStub(RouteMatchInterface::class);
    $routeMatch->method('getRawParameters')->willReturn($parameterBag);

    // Check for allowed access.
    $this->assertInstanceOf(AccessResultAllowed::class, $this->accessCheck->access($route, $request, $routeMatch));
  }

  /**
   * Data provider for testCsrfTokenCompleteLifeCycle().
   *
   * @return array
   *   An array of route parameters.
   */
  public static function providerTestCsrfTokenCompleteLifeCycle(): array {
    return [
      [['param' => 'value']],
      [['param' => '']],
      [['param' => NULL]],
      [[]],
    ];
  }

  /**
   * Data provider for testCsrfTokenCompleteLifeCycleWithExcludedParams().
   *
   * @return array
   *   An array of route parameters, and values for the route and request 'js'
   *   param.
   */
  public static function providerTestCsrfTokenCompleteLifeCycleWithExcludedParams(): array {
    return [
      [['param' => 'value'], 'nojs', 'nojs'],
      [['param' => 'value'], 'nojs', 'ajax'],
      [['param' => 'value'], 'ajax', 'nojs'],
      [['param' => 'value'], 'ajax', 'ajax'],
    ];
  }

}
