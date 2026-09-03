<?php

declare(strict_types=1);

namespace Drupal\deprecated_twig_template\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;

/**
 * Controller for calling a deprecated theme.
 */
class DeprecatedTwigTemplateController extends ControllerBase {

  /**
   * Display the deprecated template with a message.
   *
   * @return array
   *   Render array containing the deprecated template.
   */
  #[Route(
    path: '/deprecated-twig-template',
    name: 'deprecated_twig_template',
    requirements: ['_access' => 'TRUE'],
  )]
  public function deprecatedTwigTemplate() {
    return [
      '#theme' => 'deprecated_template',
      '#message' => 'This is a deprecated template. Use an alternative template.',
    ];
  }

}
