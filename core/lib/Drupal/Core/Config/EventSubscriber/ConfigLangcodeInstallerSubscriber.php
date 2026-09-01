<?php

declare(strict_types=1);

namespace Drupal\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode to the language chosen during site install.
 *
 * Always registered, but only acts while a site installation is under way.
 *
 * @see \Drupal\Core\Installer\ConfigOverride
 */
class ConfigLangcodeInstallerSubscriber implements EventSubscriberInterface {

  /**
   * Sets the langcode to the one chosen for this installation, if any.
   */
  public function onResolveLangcode(ConfigLangcodeResolveEvent $event): void {
    if (!InstallerKernel::installationAttempted()) {
      return;
    }
    global $install_state;
    $installer_langcode = $install_state['parameters']['langcode'] ?? NULL;
    if ($installer_langcode !== NULL) {
      $event->setLangcode($installer_langcode);
      // The language chosen for this installation is authoritative: nothing
      // downstream should second-guess it, especially not against a langcode
      // this event was seeded with (which language module's own subscriber
      // can only judge against the site's default_config_langcode - not yet
      // meaningful this early in the install).
      $event->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::RESOLVE_LANGCODE => ['onResolveLangcode', 100],
    ];
  }

}
