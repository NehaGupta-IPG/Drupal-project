<?php

namespace Drupal\taxonomy;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionLogInterface;

/**
 * Provides an interface defining a taxonomy term entity.
 */
interface TermInterface extends ContentEntityInterface, EntityChangedInterface, EntityPublishedInterface, RevisionLogInterface {

  /**
   * Gets the term name.
   *
   * @return string
   *   The term name.
   */
  public function getName();

  /**
   * Sets the term name.
   *
   * @param string $name
   *   The term name.
   *
   * @return $this
   */
  public function setName($name);

  /**
   * Gets the term weight.
   *
   * @return int
   *   The term weight.
   */
  public function getWeight();

  /**
   * Sets the term weight.
   *
   * @param int $weight
   *   The term weight.
   *
   * @return $this
   */
  public function setWeight($weight);

}
