<?php

namespace Drupal\language;

/**
 * Interface for negotiation methods that provide an administration language.
 *
 * Language negotiation methods can implement this interface to provide the
 * language the current user administers the site in, independent of the path
 * of the current request. This is used to render administrative interface
 * elements, such as the navigation, in the administration language even on
 * non-administrative pages.
 *
 * @see \Drupal\language\ConfigurableLanguageManagerInterface::getAdminLangcode()
 */
interface AdminLanguageNegotiationInterface {

  /**
   * Gets the current user's administration language.
   *
   * @return string
   *   The administration langcode, or an empty string when this method does
   *   not provide an administration language for the current user.
   */
  public function getAdminLangcode(): string;

}
