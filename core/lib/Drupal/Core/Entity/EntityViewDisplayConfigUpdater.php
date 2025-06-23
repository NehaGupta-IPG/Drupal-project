<?php

declare(strict_types=1);

namespace Drupal\Core\Entity;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionComponent;

/**
 * Provides a BC layer for modules providing old entity_view_display config.
 *
 * @internal
 */
final class EntityViewDisplayConfigUpdater {

  /**
   * Flag determining whether deprecations should be triggered.
   *
   * @var bool
   */
  protected bool $deprecationsEnabled = TRUE;

  /**
   * Stores which deprecations were triggered.
   *
   * @var array
   */
  protected array $triggeredDeprecations = [];

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
   *   The entity view display to update.
   *
   * @return bool
   *   Whether the block was updated.
   */
  public function updateEntity(EntityViewDisplayInterface $entityViewDisplay): bool {
    $changed = FALSE;
    if ($this->needsThirdPartySettingsUpdate($entityViewDisplay)) {
      $changed = TRUE;
    }
    return $changed;
  }

  /**
   * Checks if the block contains deprecated info and status settings.
   *
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $entityViewDisplay
   *   The entity view display to update.
   *
   * @return bool
   *   TRUE if the display was updated.
   */
  public function needsThirdPartySettingsUpdate(EntityViewDisplayInterface $entityViewDisplay): bool {
    $needs_update = FALSE;

    // Only update entity view displays where Layout Builder is enabled.
    if ($entityViewDisplay instanceof LayoutEntityDisplayInterface && $entityViewDisplay->isLayoutBuilderEnabled()) {
      foreach ($entityViewDisplay->getSections() as $section) {
        // Add a third_party_settings element to each section component.
        $components = $section->getComponents();
        foreach ($components as $delta => $component) {
          $components[$delta] = new SectionComponent(
            $component->getUuid(),
            $component->getRegion(),
            $component->getConfiguration(),
            $component->toArray()['additional']
          );
          // Depending on the state of the configuration of a site and when this
          // update is run, there might already be third party settings on a
          // section component. Retain them, if they exist.
          $tps_providers = $component->getThirdPartyProviders();
          foreach ($tps_providers as $provider) {
            foreach ($component->getThirdPartySettings($provider) as $key => $value) {
              $component->setThirdPartySetting($provider, $key, $value);
            }
          }
          // Flag this display as needing to be updated.
          $needs_update = TRUE;
        }
      }
    }

    $deprecations_triggered = &$this->triggeredDeprecations['3015152'][$entityViewDisplay->id()];
    if ($this->deprecationsEnabled && !$deprecations_triggered) {
      $deprecations_triggered = TRUE;
      @trigger_error('Entity view displays with Layout Builder enabled require a third_party_settings key on each section component, not having this is deprecated in drupal:11.3.0 and will be removed in drupal:12.0.0. Profile, module and theme provided configuration should be updated. See https://www.drupal.org/node/3100177', E_USER_DEPRECATED);
    }

    return $needs_update;

  }

}
