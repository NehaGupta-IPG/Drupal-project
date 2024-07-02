<?php

namespace Drupal\system\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Updates system.theme:admin config if it's still at the default empty string.
 *
 * @internal
 *   Tagged services are internal.
 */
class UpdateEmptyAdminTheme implements EventSubscriberInterface {

  public function __construct(private readonly RequestStack $requestStack) {
  }

  /**
   * Updates system.theme:admin config if it's still at the default.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function onSave(ConfigCrudEvent $event): void {
    $saved_config = $event->getConfig();
    if ($saved_config->getName() === 'system.theme' && $saved_config->get('admin') === '') {
      $saved_config->set('admin', NULL)->save(TRUE);
      if (!str_contains($this->requestStack->getMainRequest()->getBaseUrl(), 'update.php')) {
        @trigger_error("Setting empty 'system.theme admin' key is deprecated in drupal:11.0.0-alpha1 and will not be allowed in drupal:11.0.0-alpha2. See https://www.drupal.org/node/3441503", E_USER_DEPRECATED);
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
