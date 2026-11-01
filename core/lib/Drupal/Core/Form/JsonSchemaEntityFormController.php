<?php

namespace Drupal\Core\Form;

use Drupal\Core\Entity\HtmlEntityFormController;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a wrapping controller for JSON Schema entity forms.
 */
class JsonSchemaEntityFormController extends HtmlEntityFormController {

  /**
   * {@inheritdoc}
   */
  public function getContentResult(Request $request, RouteMatchInterface $route_match) {
    $form = parent::getContentResult($request, $route_match);
    $json_schema_form_builder = new JsonSchemaFormBuilder();
    return new JsonResponse($json_schema_form_builder->build($form));
  }

}
