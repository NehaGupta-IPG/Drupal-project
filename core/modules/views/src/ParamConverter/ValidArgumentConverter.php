<?php

namespace Drupal\views\ParamConverter;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\Core\ParamConverter\ParamNotConvertedException;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\views\Plugin\views\argument\ArgumentPluginBase;
use Drupal\views\ValidArgumentBase;
use Symfony\Component\Routing\Route;

/**
 * Checks view routes based on argument configuration.
 *
 * This is primarily used to avoid displaying links to views that will return a
 * 404 response.
 */
class ValidArgumentConverter extends ValidArgumentBase implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public static function requiresValue(array $options): bool {
    return ($options['default_action'] ?? '') === 'not found';
  }

  /**
   * {@inheritdoc}
   */
  public static function requiresValidation(array $options): bool {
    if (!isset($options['validate']['type']) || $options['validate']['type'] === 'none') {
      return FALSE;
    }
    // Default behavior is "not found".
    return !isset($options['validate']['fail']) || $options['validate']['fail'] === 'not found';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    $argument_id = $definition['argument_id'] ?? FALSE;
    if (!is_string($argument_id)) {
      return $value;
    }

    $route = $defaults[RouteObjectInterface::ROUTE_OBJECT];
    assert($route instanceof Route);

    $view_id = $route->getDefault('view_id');
    $display_id = $route->getDefault('display_id');
    if (!is_string($view_id) || !is_string($display_id)) {
      return $value;
    }

    $view = $this->loadViewExecutable($view_id, $display_id);
    $argument = $view->getDisplay()->getHandler('argument', $argument_id);
    if (!$argument instanceof ArgumentPluginBase) {
      return $value;
    }

    $cacheability = CacheableMetadata::createFromObject($view);
    if (!$this->argumentValueIsValid($argument, $value, $cacheability)) {
      throw new ParamNotConvertedException(cacheability: $cacheability);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    // Should never be called directly as this converter is set on the route by
    // \Drupal\views\Plugin\views\display\PathPluginBase::getRoute().
    return FALSE;
  }

}
