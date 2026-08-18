<?php

declare(strict_types=1);

namespace Drupal\help_topics_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\Attribute\Route;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Returns the response for help_topics_test routes.
 */
class HelpTopicsTestController extends ControllerBase {

  /**
   * Displays a dummy page for testing.
   *
   * @param int $int_param
   *   Required parameter (ignored).
   *
   * @return array
   *   Render array for the dummy page.
   */
  #[Route(
    path: '/help_topics_test/{int_param<\\d+>}',
    name: 'help_topics_test.test_route',
    title: new TranslatableMarkup('Page for testing URLs in topics'),
    requirements: ['_permission' => 'access test help'],
    options: ['parameters' => ['required_param' => ['type' => 'int']]],
  )]
  public function testPage(int $int_param) {
    $build = [
      '#markup' => 'You have reached the help topics test routing page.',
    ];
    return $build;
  }

}
