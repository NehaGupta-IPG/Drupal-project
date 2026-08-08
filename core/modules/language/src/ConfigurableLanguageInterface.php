<?php

namespace Drupal\language;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Language\LanguageInterface;

/**
 * Provides an interface defining a language entity.
 */
interface ConfigurableLanguageInterface extends ConfigEntityInterface, LanguageInterface {

  /**
   * Sets the name of the language.
   *
   * @param string $name
   *   The human-readable English name of the language.
   *
   * @return $this
   */
  public function setName($name);

  /**
   * Gets the local (Autonym) name of the language.
   *
   * @return string
   *   The local name of the language, in the language itself.
   */
  public function getLocal(): string;

  /**
   * Sets the local (Autonym) name of the language.
   *
   * @param string $name
   *   The local name of the language, in the language itself.
   *
   * @return $this
   */
  public function setLocal(string $name): static;

  /**
   * Sets the weight of the language.
   *
   * @param int $weight
   *   The weight, used to order languages with larger positive weights sinking
   *   items toward the bottom of lists.
   *
   * @return $this
   */
  public function setWeight($weight);

}
