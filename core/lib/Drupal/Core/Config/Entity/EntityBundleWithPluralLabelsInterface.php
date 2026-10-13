<?php

namespace Drupal\Core\Config\Entity;

/**
 * Allows bundle configuration entities to support label plural variants.
 */
interface EntityBundleWithPluralLabelsInterface extends ConfigEntityInterface {

  /**
   * Sets the singular label of the bundle.
   *
   * @param string $singular_label
   *   The singular label.
   *
   * @return $this
   */
  public function setSingularLabel(string $singular_label): self;

  /**
   * Returns the singular label of the bundle.
   *
   * @return string|null
   *   The singular label or NULL if it was not set.
   */
  public function getSingularLabel(): ?string;

  /**
   * Sets the plural label of the bundle.
   *
   * @param string $plural_label
   *   The plural label.
   *
   * @return $this
   */
  public function setPluralLabel(string $plural_label): self;

  /**
   * Returns the plural label of the bundle.
   *
   * @return string|null
   *   The plural label or NULL if it was not set.
   */
  public function getPluralLabel(): ?string;

  /**
   * Sets the count label.
   *
   * @param string[] $count_label
   *   The list of count label variants.
   *
   * @return $this
   */
  public function setCountLabel(array $count_label): self;

  /**
   * Returns the count label of the bundle.
   *
   * @return array|null
   *   The count label or NULL if it was not set.
   */
  public function getCountLabel(): ?array;

}
