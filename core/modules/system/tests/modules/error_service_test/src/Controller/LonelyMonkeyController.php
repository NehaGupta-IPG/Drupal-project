<?php

declare(strict_types=1);

namespace Drupal\error_service_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\error_service_test\LonelyMonkeyClass;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a controller which calls out to a service with missing dependencies.
 */
class LonelyMonkeyController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The lonely monkey class.
   */
  protected LonelyMonkeyClass $class;

  public function __construct(LonelyMonkeyClass $class) {
    $this->class = $class;
  }

  #[Route(
    path: 'broken-service-class',
    name: 'error_service_test.broken_class',
    requirements: ['_access' => 'TRUE'],
  )]

  public function testBrokenClass() {
    return [
      '#markup' => $this->t('This should be broken.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('broken_class_with_missing_dependency'));
  }

}
