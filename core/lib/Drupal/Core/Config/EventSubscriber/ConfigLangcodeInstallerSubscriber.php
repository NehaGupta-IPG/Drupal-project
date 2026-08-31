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
 * Only registered while a site installation is under way.
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

    // Vouch for it regardless of whether anything below resolves to it:
    // shipped configuration can perfectly validly already carry an explicit
    // langcode that happens to match the language being installed, and
    // system.site is not reliably readable this early in install for
    // ConfigLangcodeSiteDefaultSubscriber to vouch for it instead.
    $event->allowLangcode($installer_langcode);

    $current = $event->getCurrentLangcode();
    if ($current === NULL || $current === 'en') {
      // Authoritative: since this runs first, nothing later can second-guess
      // it - ConfigLangcodeResolveEvent::setLangcode() only ever lets the
      // first call win.
      $event->setLangcode($installer_langcode);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      ConfigEvents::RESOLVE_LANGCODE => ['onResolveLangcode', 10000],
    ];
  }

}
