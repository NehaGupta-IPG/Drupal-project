<?php

namespace Drupal\Core\Controller;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Wrapping controller for forms that serve as the main page body.
 */
class HtmlFormController extends FormController {

  /**
   * The class resolver.
   *
   * @var ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs a new \Drupal\Core\Controller\HtmlFormController object.
   *
   * @param ArgumentResolverInterface $argument_resolver
   *   The argument resolver.
   * @param FormBuilderInterface $form_builder
   *   The form builder.
   * @param ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(ArgumentResolverInterface $argument_resolver, FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver) {
    parent::__construct($argument_resolver, $form_builder);
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormArgument(RouteMatchInterface $route_match) {
    return $route_match->getRouteObject()->getDefault('_form');
  }

  /**
   * {@inheritdoc}
   */
  protected function getFormObject(RouteMatchInterface $route_match, $form_arg) {
    return $this->classResolver->getInstanceFromDefinition($form_arg);
  }

}
