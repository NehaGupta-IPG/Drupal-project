<?php

namespace Drupal\layout_builder\Normalizer;

use Drupal\layout_builder\Plugin\DataType\SectionData;
use Drupal\layout_builder\Section;
use Drupal\serialization\Normalizer\TypedDataNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Normalizes section data.
 */
class SectionDataNormalizer extends TypedDataNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = SectionData::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array {
    $this->addCacheableDependency($context, $object);
    $value = $object->getValue();
    // Don't call parent method because of return type definition
    return $value->toArray();
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): Section {
    return Section::fromArray((array) $data);
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      SectionData::class => TRUE,
    ];
  }

}
