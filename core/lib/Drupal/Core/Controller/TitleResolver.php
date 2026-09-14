<?php

namespace Drupal\Core\Controller;

use Drupal\Core\Routing\Attribute\Route as RouteAttribute;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\Routing\Route;

/**
 * Provides the default implementation of the title resolver interface.
 */
class TitleResolver implements TitleResolverInterface, CacheableTitleResolverInterface {

  use StringTranslationTrait;

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

  /**
   * The argument resolver.
   *
   * @var \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
   */
  protected $argumentResolver;

  /**
   * Constructs a TitleResolver instance.
   *
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controller_resolver
   *   The controller resolver.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   * @param \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface $argument_resolver
   *   The argument resolver.
   */
  public function __construct(ControllerResolverInterface $controller_resolver, TranslationInterface $string_translation, ArgumentResolverInterface $argument_resolver) {
    $this->controllerResolver = $controller_resolver;
    $this->stringTranslation = $string_translation;
    $this->argumentResolver = $argument_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(Request $request, Route $route) {
    return $this->doGetTitle($request, $route);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableTitle(Request $request, Route $route): CacheableTitle {
    $cacheable_title = new CacheableTitle();
    $title = $this->doGetTitle($request, $route, $cacheable_title);
    $cacheable_title->setTitle($title);

    return $cacheable_title;
  }

  /**
   * Returns a static or dynamic title for the route.
   *
   * This supports both cacheable and non-cacheable titles.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object passed to the title callback.
   * @param \Symfony\Component\Routing\Route $route
   *   The route information of the route to fetch the title.
   * @param \Drupal\Core\Controller\CacheableTitle $cacheable_title
   *   The cacheable title to attach the cacheable metadata to.
   *
   * @return array|string|null
   *   The cacheable title for the route.
   *
   * @see \Drupal\Core\Controller\CacheableTitleResolverInterface::getCacheableTitle()
   * @see \Drupal\Core\Controller\TitleResolverInterface::getTitle()
   */
  protected function doGetTitle(Request $request, Route $route, ?CacheableTitle $cacheable_title = NULL) {
    $route_title = NULL;
    // A dynamic title takes priority. Route::getDefault() returns NULL if the
    // named default is not set.  By testing the value directly, we also avoid
    // trying to use empty values.
    if ($callback = $route->getDefault('_title_callback')) {
      $callable = $this->controllerResolver->getControllerFromDefinition($callback);
      if ($cacheable_title) {
        $request->attributes->set('cacheable_metadata', $cacheable_title);
      }

      $arguments = $this->argumentResolver->getArguments($request, $callable);
      $route_title = call_user_func_array($callable, $arguments);
    }
    elseif ($reference = $route->getDefault('_title_closure')) {
      $callable = RouteAttribute::getTitleClosure(...$reference);
      $arguments = $this->argumentResolver->getArguments($request, $callable);
      $route_title = call_user_func_array($callable, $arguments);
    }
    elseif ($route->hasDefault('_title') && $route->getDefault('_title') instanceof TranslatableMarkup) {
      $route_title = $route->getDefault('_title');
    }
    elseif ($route->hasDefault('_title') && strlen($route->getDefault('_title')) > 0) {
      $title = $route->getDefault('_title');
      $options = [];
      if ($route->hasDefault('_title_context')) {
        $options['context'] = $route->getDefault('_title_context');
      }
      $args = [];
      if ($route->hasDefault('_title_arguments')) {
        $args = (array) $route->getDefault('_title_arguments');
      }
      if (($raw_parameters = $request->attributes->get('_raw_variables'))) {
        foreach ($raw_parameters->all() as $key => $value) {
          if (is_scalar($value)) {
            $args['@' . $key] = $value;
            $args['%' . $key] = $value;
          }
        }
        if ($cacheable_title) {
          $cacheable_title->addCacheContexts(['url.path.parent']);
        }
      }

      // Fall back to a static string from the route.
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $route_title = $this->t($title, $args, $options);
    }

    // Empty titles should return a NULL value as this is same result as title
    // not being set.
    if ($route_title === '' || ($route_title instanceof TranslatableMarkup && $route_title->getUntranslatedString() === '')) {
      return NULL;
    }

    return $route_title;
  }

}
