<?php

declare(strict_types=1);

namespace Drupal\language;

/**
 * Defines events for the language module.
 */
final class LanguageEvents {

  /**
   * Name of the event fired when the site's default language changes.
   *
   * Subscribers can use this to append their own batch operations to the
   * one that reconciles configuration langcode with the new default. The
   * event listener method receives a
   * \Drupal\language\Event\LanguageDefaultLangcodeChangeEvent instance.
   *
   * @see \Drupal\language\Event\LanguageDefaultLangcodeChangeEvent
   * @see \Drupal\language\DefaultLangcodeConfigUpdater
   */
  const DEFAULT_LANGCODE_CHANGE = 'language.default_langcode_change';

}
