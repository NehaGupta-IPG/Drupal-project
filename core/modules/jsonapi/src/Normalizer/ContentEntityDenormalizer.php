<?php

namespace Drupal\jsonapi\Normalizer;

@trigger_error(__NAMESPACE__ . '\ContentEntityDenormalizer is deprecated in drupal:10.4.0 and will be removed before drupal:11.0.0. Instead, use \Drupal\jsonapi\Normalizer\FieldableEntityDenormalizer. See https://www.drupal.org/node/3343351', E_USER_DEPRECATED);

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\serialization\Normalizer\CacheableNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Converts a JSON:API array structure into a Drupal entity object.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
final class ContentEntityDenormalizer implements SerializerAwareInterface, CacheableNormalizerInterface, CacheableSupportsMethodInterface, DenormalizerInterface {

  /**
   * The replacement service.
   *
   * @var \Drupal\jsonapi\Normalizer\FieldableEntityDenormalizer
   */
  private FieldableEntityDenormalizer $replacement;

  /**
   * Constructs an ContentEntityDenormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $plugin_manager
   *   The plugin manager for fields.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $field_manager, FieldTypePluginManagerInterface $plugin_manager) {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:10.4.0 and is removed from drupal:11.0.0. Use \Drupal\jsonapi\JsonApiResource\ResourceObject::extractFieldableEntityFields() method instead. See https://www.drupal.org/node/3343351', E_USER_DEPRECATED);
    $this->replacement = new FieldableEntityDenormalizer($entity_type_manager, $field_manager, $plugin_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $type, $format = NULL, array $context = []): mixed {
    return $this->replacement->denormalize($data, $type, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, string $type, ?string $format = NULL, array $context = []): bool {
    return $this->replacement->supportsDenormalization($data, $type, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    return $this->replacement->normalize($object, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, ?string $format = NULL, array $context = []): bool {
    @trigger_error(__METHOD__ . '() is deprecated in drupal:10.4.0 and is removed from drupal:11.0.0. Use getSupportedTypes() instead. See https://www.drupal.org/node/3359695', E_USER_DEPRECATED);

    return $this->replacement->supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function setSerializer(SerializerInterface $serializer): void {
    $this->replacement->setSerializer($serializer);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return $this->replacement->getSupportedTypes($format);
  }

}
