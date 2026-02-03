<?php

declare(strict_types=1);

namespace Drupal\rest;

use Drupal\views\ViewEntityInterface;

/**
 * Provides a BC layer for modules providing old configurations.
 *
 * @internal
 */
class RestConfigUpdater {

  /**
   * Checks if a serializer display needs the pager option added.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if the view has any serializer displays missing the pager option.
   */
  public function needsSerializerPagerUpdate(ViewEntityInterface $view): bool {
    foreach ($view->get('display') as $display) {
      if (
        isset($display['display_options']['style']) &&
        $display['display_options']['style']['type'] === 'serializer' &&
        !isset($display['display_options']['style']['options']['pager'])
      ) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Adds the pager option to serializer style displays that are missing it.
   *
   * @param \Drupal\views\ViewEntityInterface $view
   *   The view entity.
   *
   * @return bool
   *   TRUE if the view was updated.
   */
  public function processSerializerPagerUpdate(ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display');

    foreach ($displays as &$display) {
      if (
        isset($display['display_options']['style']) &&
        $display['display_options']['style']['type'] === 'serializer' &&
        !isset($display['display_options']['style']['options']['pager'])
      ) {
        $display['display_options']['style']['options']['pager'] = FALSE;
        $changed = TRUE;
      }
    }

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;
  }

}
