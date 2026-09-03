<?php

namespace Drupal\language\EventSubscriber;

use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\language\DefaultLangcodeConfigUpdater;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Triggers the reconciliation of configuration langcode with a new default.
 *
 * @see \Drupal\Core\Config\ConfigLangcodeResolveEvent
 */
class DefaultConfigLangcodeSubscriber implements EventSubscriberInterface {

  /**
   * The configuration object name that holds the default langcode.
   */
  protected const string CONFIG_NAME = 'system.site';

  /**
   * The configuration key that holds the default langcode.
   */
  protected const string CONFIG_KEY = 'default_langcode';

  /**
   * Constructs a new class object.
   *
   * @param \Drupal\language\DefaultLangcodeConfigUpdater $defaultLangcodeConfigUpdater
   *   The default langcode configuration updater.
   * @param \Drupal\Core\Config\ConfigInstallerInterface $configInstaller
   *   The config installer, used to check whether configuration is syncing.
   */
  public function __construct(protected readonly DefaultLangcodeConfigUpdater $defaultLangcodeConfigUpdater, protected readonly ConfigInstallerInterface $configInstaller) {
  }

  /**
   * Triggers the reconciliation of configuration langcode with a new default.
   *
   * This event subscriber assumes that the new default langcode and old default
   * langcode are valid langcodes. If the source of truth for the default
   * langcode is altered by a contributed or custom module, then it must replace
   * this event with its own implementation.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The configuration event.
   */
  public function onConfigSave(ConfigCrudEvent $event): void {
    if ($this->configInstaller->isSyncing()) {
      // An import's source storage is a complete, self-consistent snapshot: it
      // is responsible for providing correct langcodes for anything it changes
      // itself, the same reasoning that keeps ConfigInstaller and
      // Config::save() from touching langcode mid-sync.
      return;
    }
    $saved_config = $event->getConfig();
    if ($saved_config->getName() == static::CONFIG_NAME && $event->isChanged(static::CONFIG_KEY)) {
      $old_default_langcode = $saved_config->getOriginal(static::CONFIG_KEY);
      if ($old_default_langcode === NULL) {
        // During site installation the default langcode is not set yet, so the
        // source of truth is not changing.
        return;
      }
      $new_default_langcode = $saved_config->get(static::CONFIG_KEY);

      $batch = $this->defaultLangcodeConfigUpdater->buildBatch($old_default_langcode, $new_default_langcode);
      // This only registers the batch; nothing here drives it forward.
      // A form submission's own \Drupal\Core\Form\FormSubmitter does
      // that automatically once its submit handlers finish. Something
      // else saving this configuration with no form behind it - a
      // drush command, a plain API call - is responsible for calling
      // batch_process() itself if it needs the reconciliation to have
      // completed by the time this method returns.
      // @todo Create follow-up - we have this problem today with the locale
      //   batch set in the modules installed hook.
      batch_set($batch);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[ConfigEvents::SAVE][] = ['onConfigSave', -1];
    return $events;
  }

}
