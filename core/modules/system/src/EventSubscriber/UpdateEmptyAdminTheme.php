<?php

namespace Drupal\system\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Updates system.theme:admin config when it is an empty string.
 *
 * @internal
 *   Tagged services are internal.
 */
final class UpdateEmptyAdminTheme implements EventSubscriberInterface {

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
   * Updates system.theme:admin config if it's still at the default.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The event to process.
   */
  public function onSave(ConfigCrudEvent $event): void {
    $saved_config = $event->getConfig();
    if ($saved_config->getName() === 'system.theme' && $saved_config->get('admin') === '') {
      $saved_config->set('admin', NULL)->save(TRUE);

      // Prevent duplicate deprecation notices per config for issue 3441503.
      $deprecations_triggered = &$this->triggeredDeprecations['3441503'][$saved_config->getName()];
      if ($this->deprecationsEnabled && !$deprecations_triggered) {
        $deprecations_triggered = TRUE;
        @trigger_error("Setting the admin theme to an empty string is deprecated in drupal:11.2.0 and will not be allowed in drupal:12.0.0. See https://www.drupal.org/node/3441503", E_USER_DEPRECATED);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onSave'];
    return $events;
  }

}
