<?php

namespace Drupal\language;

use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for language switcher classes.
 */
interface LanguageSwitcherInterface {

  /**
   * Returns language switch links.
   *
   * @param Request $request
   *   The current request.
   * @param string $type
   *   The language type.
   * @param Url $url
   *   The URL the switch links will be relative to.
   *
   * @return array
   *   An array of link arrays keyed by language code.
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url);

}
