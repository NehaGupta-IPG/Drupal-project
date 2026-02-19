<?php

namespace Drupal\Core\Routing;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Http\Exception\CacheableAccessDeniedHttpException;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RequestContext as SymfonyRequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * A router class for Drupal with access check and upcasting.
 */
class AccessAwareRouter implements AccessAwareRouterInterface {

  /**
   * The router doing the actual routing.
   *
   * @var RouterInterface
   */
  protected $router;

  /**
   * The access manager.
   *
   * @var AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The account to use in access checks.
   *
   * @var AccountInterface
   */
  protected $account;

  /**
   * Constructs a router for Drupal with access check and upcasting.
   *
   * @param RouterInterface $router
   *   The router doing the actual routing.
   * @param AccessManagerInterface $access_manager
   *   The access manager.
   * @param AccountInterface $account
   *   The account to use in access checks.
   */
  public function __construct(RouterInterface $router, AccessManagerInterface $access_manager, AccountInterface $account) {
    $this->router = $router;
    $this->accessManager = $access_manager;
    $this->account = $account;
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, $arguments) {
    // Ensure to call every other function to the router.
    return call_user_func_array([$this->router, $name], $arguments);
  }

  /**
   * {@inheritdoc}
   */
  public function setContext(SymfonyRequestContext $context): void {
    $this->router->setContext($context);
  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): SymfonyRequestContext {
    return $this->router->getContext();
  }

  /**
   * {@inheritdoc}
   *
   * @throws AccessDeniedHttpException
   *   Thrown when access checking failed.
   */
  public function matchRequest(Request $request): array {
    $parameters = $this->router->matchRequest($request);
    $request->attributes->add($parameters);
    $this->checkAccess($request);
    // We can not return $parameters because the access check can change the
    // request attributes.
    return $request->attributes->all();
  }

  /**
   * Apply access check service to the route and parameters in the request.
   *
   * @param Request $request
   *   The request to access check.
   */
  protected function checkAccess(Request $request) {
    // The cacheability (if any) of this request's access check result must be
    // applied to the response.
    $access_result = $this->accessManager->checkRequest($request, $this->account, TRUE);
    // Allow a master request to set the access result for a subrequest: if an
    // access result attribute is already set, don't overwrite it.
    if (!$request->attributes->has(AccessAwareRouterInterface::ACCESS_RESULT)) {
      $request->attributes->set(AccessAwareRouterInterface::ACCESS_RESULT, $access_result);
    }
    if (!$access_result->isAllowed()) {
      if ($access_result instanceof CacheableDependencyInterface && $request->isMethodCacheable()) {
        throw new CacheableAccessDeniedHttpException($access_result, $access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : '');
      }
      else {
        throw new AccessDeniedHttpException($access_result instanceof AccessResultReasonInterface ? $access_result->getReason() : '');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getRouteCollection(): RouteCollection {
    return $this->router->getRouteCollection();
  }

  /**
   * {@inheritdoc}
   */
  public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH): string {
    return $this->router->generate($name, $parameters, $referenceType);
  }

  /**
   * {@inheritdoc}
   *
   * @throws AccessDeniedHttpException
   *   Thrown when access checking failed.
   */
  public function match($pathinfo): array {
    try {
      $request = Request::create($pathinfo);
    }
    catch (BadRequestException $e) {
      throw new ResourceNotFoundException($e->getMessage(), $e->getCode(), $e);
    }
    return $this->matchRequest($request);
  }

}
