<?php

namespace Drupal\views;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\views\Plugin\ViewsPluginManager;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines the cache backend factory.
 */
class ViewExecutableFactory {

  /**
   * Stores the current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The views data.
   *
   * @var \Drupal\views\ViewsData
   */
  protected $viewsData;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  public function __construct(AccountInterface $user, RequestStack $request_stack, ViewsData $views_data, RouteProviderInterface $route_provider, protected ?ViewsPluginManager $displayPluginManager = NULL, protected ?ViewsPluginManager $cachePluginManager = NULL) {
    $this->user = $user;
    $this->requestStack = $request_stack;
    $this->viewsData = $views_data;
    $this->routeProvider = $route_provider;
    if ($this->displayPluginManager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $displayPluginManager argument is deprecated in drupal:10.3.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3410349', E_USER_DEPRECATED);
      $this->displayPluginManager = \Drupal::service('plugin.manager.views.display');
    }
    if ($this->cachePluginManager === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $cachePluginManager argument is deprecated in drupal:11.4.0 and it will be required in drupal:12.0.0. See https://www.drupal.org/node/3421323', E_USER_DEPRECATED);
      $this->cachePluginManager = \Drupal::service('plugin.manager.views.cache');
    }
  }

  /**
   * Instantiates a ViewExecutable class.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   A view entity instance.
   *
   * @return \Drupal\views\ViewExecutable
   *   A ViewExecutable instance.
   */
  public function get(ViewEntityInterface $view) {
    $view_executable = new ViewExecutable($view, $this->user, $this->viewsData, $this->routeProvider, $this->displayPluginManager, $this->cachePluginManager);
    $view_executable->setRequest($this->requestStack->getCurrentRequest());
    return $view_executable;
  }

}
