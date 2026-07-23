<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;

/**
 * Config updater service to update configuration entities.
 */
class EntityViewDisplayConfigUpdater {

  /**
   * Flag determining whether deprecations should be triggered.
   *
   * @var bool
   */
  protected bool $deprecationsEnabled = TRUE;

  /**
   * Sets the deprecations enabling status.
   *
   * @param bool $enabled
   *   Whether deprecations should be enabled.
   */
  public function setDeprecationsEnabled(bool $enabled): void {
    $this->deprecationsEnabled = $enabled;
  }

  /**
   * Performs the required update.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entityViewDisplay
   *   The entity_view_display entity to update.
   *
   * @return bool
   *   Whether the entity_view_display was updated.
   */
  public function updateEntityViewDisplay(EntityViewDisplayInterface $entityViewDisplay): bool {
    if ($entityViewDisplay->getTargetEntityTypeId() !== 'node') {
      return FALSE;
    }

    $langcode = $entityViewDisplay->getComponent('langcode');
    if ($langcode === NULL) {
      return FALSE;
    }

    $langcode['label'] = 'above';
    // Set to show it as plain text.
    $langcode['settings'] = [
      'link_to_entity' => FALSE,
      'native_language' => FALSE,
    ];

    $entityViewDisplay->setComponent('langcode', $langcode);
    return TRUE;
  }

  /**
   * Checks if entity_view_display updates needs to be performed.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entityViewDisplay
   *   The entity_view_display entity to update.
   *
   * @return bool
   *   TRUE if we need to update entity, FALSE otherwise.
   */
  public function needsEntityViewDisplayUpdate(EntityViewDisplayInterface $entityViewDisplay): bool {
    return $this->updateEntityViewDisplay($entityViewDisplay);
  }

}
