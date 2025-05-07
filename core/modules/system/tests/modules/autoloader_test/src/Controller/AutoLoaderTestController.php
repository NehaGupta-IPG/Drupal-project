<?php

declare(strict_types=1);

namespace Drupal\autoloader_test\Controller;

use Drupal\autoloader_test\AutoloaderTestClass;
use Drupal\autoloader_test\AutoloaderTestEnum;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A controller for testing autoloader test container parameters.
 */
final class AutoLoaderTestController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs a new AutoLoaderTestController.
   */
  private function __construct(
    private AutoloaderTestEnum $enumParameter,
    private AutoloaderTestClass $classParameter,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->getParameter('autoloader_test.enum')[0],
      $container->getParameter('autoloader_test.class')[0],
    );
  }

  /**
   * Outputs the values of test parameters.
   */
  public function __invoke(): array {
    $build = [];
    $build[]['#plain_text'] = 'Enum:' . get_class($this->enumParameter);
    $build[]['#plain_text'] = 'Class:' . get_class($this->classParameter);
    return $build;
  }

}
