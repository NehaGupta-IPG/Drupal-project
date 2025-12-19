<?php

namespace Drupal\link;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\TypedData\TypedData;

/**
 * A computed property for a link field's target entity UUID (if any).
 */
class LinkTargetEntityUuid extends TypedData implements CacheableDependencyInterface {

  /**
   * Computed value: the target entity, if any.
   */
  protected ?EntityInterface $value = NULL;

  /**
   * Whether the property's value has already been computed or not.
   */
  protected bool $valueComputed = FALSE;

  /**
   * Ensures that values are only computed once.
   */
  protected function ensureComputedValue(): void {
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
  protected function computeValue(): void {
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
  protected static function uriUsesEntityScheme(LinkItemInterface $link_item): bool {
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
  protected function getTargetEntity(LinkItemInterface $link_item): ?EntityInterface {
    $entity_parts = self::getEntityPathParts($link_item->uri);
    if (count($entity_parts) < 2) {
      return NULL;
    }
    [$target_entity_type_id, $target_entity_id] = $entity_parts;
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
  protected static function getEntityPathParts(string $uri): array {
    $path = parse_url($uri, PHP_URL_PATH);
    // If $uri is an external path we should return an empty array.
    if ($path === NULL) {
      return [];
    }
    return explode('/', $path, 2);
  }

  /**
   * Retrieves the cache tags associated with the current value.
   *
   * @return array
   *   An array of cache tags. Returns an empty array if the value is FALSE.
   */
  public function getCacheTags(): array {
    $this->ensureComputedValue();
    return $this->value === FALSE ? [] : $this->value->getCacheTags();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
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
  protected function getEntityTypeManager(): EntityTypeManagerInterface {
    return \Drupal::service(EntityTypeManagerInterface::class);
  }

}
