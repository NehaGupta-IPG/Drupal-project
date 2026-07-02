<?php

declare(strict_types=1);

namespace Drupal\system;

/**
 * Lists extensions removed from Drupal core.
 */
final class RemovedCoreExtensionList {

  // cspell:ignore quickedit

  /**
   * Modules removed from Drupal core, keyed by machine name.
   *
   * @var array<string, string>
   */
  public const MODULES = [
    'action' => 'Action UI',
    'ban' => 'Ban',
    'book' => 'Book',
    'aggregator' => 'Aggregator',
    'ckeditor' => 'CKEditor',
    'color' => 'Color',
    'contact' => 'Contact',
    'field_layout' => 'Field Layout',
    'forum' => 'Forum',
    'hal' => 'HAL',
    'history' => 'History',
    'migrate_drupal_ui' => 'Migrate Drupal UI',
    'migrate_drupal' => 'Migrate Drupal',
    'quickedit' => 'Quick Edit',
    'rdf' => 'RDF',
    'statistics' => 'Statistics',
    'tour' => 'Tour',
    'tracker' => 'Tracker',
  ];

  /**
   * Themes removed from Drupal core, keyed by machine name.
   *
   * @var array<string, string>
   */
  public const THEMES = [
    'bartik' => 'Bartik',
    'classy' => 'Classy',
    'seven' => 'Seven',
    'stable' => 'Stable',
  ];

  /**
   * Determines if a given module was removed from Drupal core.
   *
   * @param string $name
   *   The module machine name.
   *
   * @return bool
   *   TRUE if the module was removed from core, FALSE otherwise.
   */
  public static function isRemovedModule(string $name): bool {
    return array_key_exists($name, self::MODULES);
  }

  /**
   * Determines if a given theme was removed from Drupal core.
   *
   * @param string $name
   *   The theme machine name.
   *
   * @return bool
   *   TRUE if the theme was removed from core, FALSE otherwise.
   */
  public static function isRemovedTheme(string $name): bool {
    return array_key_exists($name, self::THEMES);
  }

}
