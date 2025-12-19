<?php

namespace Drupal\link;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for a link field's target entity UUID (if any).
 */
class LinkTargetEntityUuid extends TypedData {

  /**
   * Computed value: the target entity, if any.
   *
   * @var \Drupal\Core\Entity\EntityInterface|false
   */
  protected $value = NULL;

  /**
   * Whether the property's value has already been computed or not.
   *
   * @var bool
   */
  protected $valueComputed = FALSE;

  /**
   * Ensures that values are only computed once.
   */
  protected function ensureComputedValue() {
    if ($this->valueComputed === FALSE) {
      $this->computeValue();
      $this->valueComputed = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureComputedValue();
    return $this->value === FALSE
      ? NULL
      : $this->value->uuid();
  }

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    /** @var \Drupal\link\LinkItemInterface $link_item */
    $link_item = $this->getParent();

    if (!static::uriUsesEntityScheme($link_item)) {
      $this->value = FALSE;
    }

    if ($target_entity = $this->getTargetEntity($link_item)) {
      $this->value = $target_entity;
    }
  }

  /**
   * Determines if the link is for an entity.
   *
   * @param \Drupal\link\LinkItemInterface $link_item
   *   A link item.
   *
   * @return bool
   *   True if the link is for an entity.
   */
  protected static function uriUsesEntityScheme(LinkItemInterface $link_item) {
    $uri = parse_url($link_item->uri, PHP_URL_SCHEME);
    return isset($uri['scheme']) && $uri['scheme'] === 'entity';
  }

  /**
   * Gets target entity, if available, from a link item.
   *
   * @param \Drupal\link\LinkItemInterface $link_item
   *   A link item.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The linked entity if any or NULL.
   */
  protected function getTargetEntity(LinkItemInterface $link_item) {
    $entity_parts = $this->getEntityPathParts($link_item->uri);
    if (count($entity_parts) < 2) {
      return NULL;
    }
    list($target_entity_type_id, $target_entity_id) = $entity_parts;
    if ($this->getEntityTypeManager()->hasDefinition($target_entity_type_id)) {
      return $this->getEntityTypeManager()->getStorage($target_entity_type_id)->load($target_entity_id);
    }
    return NULL;
  }

  /**
   * Gets the parts of the entity path.
   *
   * @param string $uri
   *   The URI for an entity.
   *
   * @return array
   *   First item is entity type. The second item is entity ID.
   */
  protected static function getEntityPathParts($uri) {
    $path = parse_url($uri, PHP_URL_PATH);
    // If $uri is an external path we should return an empty array.
    if ($path === NULL) {
      return [];
    }
    return explode('/', $path, 2);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $this->ensureComputedValue();
    return $this->value === FALSE ? [] : $this->value->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $this->ensureComputedValue();
    return $this->value === FALSE ? [] : $this->value->getCacheContexts();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    $this->ensureComputedValue();
    return $this->value === FALSE ? CacheBackendInterface::CACHE_PERMANENT : $this->value->getCacheMaxAge();
  }

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   Entity type manager.
   */
  protected function getEntityTypeManager() {
    return \Drupal::service('entity_type.manager');
  }

}
