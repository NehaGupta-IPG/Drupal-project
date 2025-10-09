<?php

namespace Drupal\link;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;

/**
 * Defines an interface for the link field item.
 */
interface LinkItemInterface extends FieldItemInterface {

  /**
   * Specifies whether the field supports only internal URLs.
   */
  const LINK_INTERNAL = 0x01;

  /**
   * Specifies whether the field supports only external URLs.
   */
  const LINK_EXTERNAL = 0x10;

  /**
   * Specifies whether the field supports both internal and external URLs.
   */
  const LINK_GENERIC = 0x11;

  /**
   * Determines if a link is external.
   *
   * @return bool
   *   TRUE if the link is external, FALSE otherwise.
   */
  public function isExternal();

  /**
   * Gets the URL object.
   *
   * @return \Drupal\Core\Url
   *   Returns a Url object.
   *
   * @throws \InvalidArgumentException
   *   Thrown when there is a problem with field data.
   */
  public function getUrl();

  /**
   * Gets the link title.
   *
   * @return string|null
   *   Returns the link title.
   */
  public function getTitle(): ?string;

  /**
   * Checks if the link's URI references a Drupal entity.
   *
   * @return bool
   *   TRUE if the URI references an entity, FALSE otherwise.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the URI is malformed.
   */
  public function isEntityLink(): bool;

  /**
   * Gets the entity referenced by the link, if any.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The referenced entity.
   *
   * @throws \InvalidArgumentException
   *   Thrown if the URI does not reference a Drupal entity.
   */
  public function getLinkedEntity(): EntityInterface;

}
