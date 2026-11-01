<?php

namespace Drupal\Core\Routing\Enhancer;

use Drupal\Core\Routing\EnhancerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * @todo.
 */
class JsonSchemaFormRouteEnhancer implements EnhancerInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route) {
    return ($route->hasDefault('_form') || $route->hasDefault('_entity_form')) && !$route->hasDefault('_controller') && $route->getRequirement('_format') === 'json_schema';
  }

  /**
   * {@inheritdoc}
   */
  public function enhance(array $defaults, Request $request) {
    if (isset($defaults['_entity_form'])) {
      $defaults['_controller'] = 'controller.entity_form.json_schema:getContentResult';
    }
    else {
      $defaults['_controller'] = 'controller.form.json_schema:getContentResult';
    }
    return $defaults;
  }

}
