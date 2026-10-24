<?php

declare(strict_types=1);

namespace Drupal\media\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media\MediaInterface;
use Drupal\media\MediaMetadataRefresher;
use Drupal\media\MediaSourceStatus;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Refreshes a media item's metadata and thumbnail from its source.
 *
 * @internal
 *   Controllers are internal.
 */
final class UpdateMetadataController extends ControllerBase {

  public function __construct(
    protected readonly MediaMetadataRefresher $refresher,
  ) {}

  /**
   * Refreshes the media item, or asks for confirmation if that is unsafe.
   *
   * The route is protected by a CSRF token, so this cannot be triggered from
   * another site. When the source is missing or unavailable, nothing is
   * changed and the user is sent to a confirmation form instead.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media item to refresh.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the destination, or to the confirmation form.
   */
  public function updateMetadata(MediaInterface $media, Request $request): RedirectResponse {
    if ($this->refresher->check($media) !== MediaSourceStatus::Available) {
      // Carry the destination on to the confirmation form; left in place, it
      // would replace this redirect.
      $options = [];
      if ($request->query->has('destination')) {
        $options['query']['destination'] = $request->query->get('destination');
        $request->query->remove('destination');
      }
      return $this->redirect('media.update_metadata_confirm', ['media' => $media->id()], $options);
    }

    $this->refresher->refresh($media);
    $this->messenger()->addStatus($this->t('Updated metadata on media item %label.', [
      '%label' => $media->label(),
    ]));
    return $this->redirect('entity.media.collection');
  }

}
