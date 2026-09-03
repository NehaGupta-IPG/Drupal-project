<?php

declare(strict_types=1);

namespace Drupal\Core\Config\EventSubscriber;

use Drupal\Core\Config\ConfigLangcodeResolveEvent;
use Drupal\Core\Installer\InstallerKernel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves configuration langcode to the language chosen during site install.
 *
 * @see \Drupal\Core\Installer\NormalInstallerServiceProvider
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
    if ($installer_langcode === NULL) {
      return;
    }

    $event->setLangcode($installer_langcode);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // During the installer this event must set the default configuration
      // language so the priority is high.
      ConfigLangcodeResolveEvent::class => ['onResolveLangcode', 10000],
    ];
  }

}
