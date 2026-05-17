<?php

namespace Drupal\media_library\Ajax;

use Drupal\Component\Utility\IntegerLike;
use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for adding media items to the media library selection.
 *
 * This command instructs the client to add the given media item IDs to the
 * current selection of the media library stored in
 * Drupal.MediaLibrary.currentSelection.
 *
 * This command is implemented by
 * Drupal.AjaxCommands.prototype.updateMediaLibrarySelection() defined in
 * media_library.ui.js.
 *
 * @ingroup ajax
 *
 * @internal
 *   This is an internal part of Media Library and may be subject to change in
 *   minor releases. External code should not instantiate or extend this class.
 */
class UpdateSelectionCommand implements CommandInterface {

  /**
   * An array of media IDs to add to the current selection.
   *
   * @var int[]
   */
  protected $mediaIds;

  /**
   * Constructs an UpdateSelectionCommand object.
   *
   * @param int[] $media_ids
   *   An array of media IDs to add to the current selection.
   */
  public function __construct(array $media_ids) {
    $this->mediaIds = $media_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'updateMediaLibrarySelection',
      // For media_library.ui.js, the values need to be stringified.
      'mediaIds' => array_map(IntegerLike::toStringIfInteger(...), $this->mediaIds),
    ];
  }

}
