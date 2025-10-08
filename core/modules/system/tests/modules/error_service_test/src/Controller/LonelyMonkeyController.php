<?php

declare(strict_types=1);

namespace Drupal\error_service_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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

  public function testBrokenClass(): array {
    return [
      '#markup' => $this->t('This should be broken.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('broken_class_with_missing_dependency'));
  }

}
