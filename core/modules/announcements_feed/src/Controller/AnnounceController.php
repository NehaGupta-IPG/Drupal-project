<?php

declare(strict_types=1);

namespace Drupal\announcements_feed\Controller;

use Drupal\announcements_feed\AnnounceRenderer;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

/**
 * Controller for community announcements.
 *
 * @internal
 */
class AnnounceController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * Constructs an AnnounceController object.
   *
   * @param \Drupal\announcements_feed\AnnounceRenderer $announceRenderer
   *   The AnnounceRenderer service.
   */
  public function __construct(
    protected AnnounceRenderer $announceRenderer,
  ) {
  }

  /**
   * Returns the list of Announcements.
   *
   * @param string|null $wrapper_format
   *   The wrapper format query parameter.
   *
   * @return array
   *   A build array with announcements.
   */
  public function getAnnouncements(#[MapQueryParameter('_wrapper_format')] ?string $wrapper_format = NULL): array {
    $build = $this->announceRenderer->render();
    if ($wrapper_format != 'drupal_dialog.off_canvas') {
      $build['#theme'] = 'announcements_feed_admin';
      $build['#attached'] = [];
    }

    return $build;
  }

}
