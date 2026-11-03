<?php

namespace Drupal\layout_builder\Normalizer;

use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\Section;
use Drupal\serialization\Normalizer\ConfigEntityNormalizer;

/**
 * Normalizes/denormalizes LayoutEntityDisplay objects into an array structure.
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutEntityDisplayNormalizer extends ConfigEntityNormalizer {

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    $data = parent::normalize($object, $format, $context);
    if (!empty($data['third_party_settings']['layout_builder']['sections'])) {
      $sections = &$data['third_party_settings']['layout_builder']['sections'];
      $sections = array_map(static function (Section $section) {
        return $section->toArray();
      }, $sections);
    }
    unset($data['third_party_settings']['layout_builder']['sections']);
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      LayoutEntityDisplayInterface::class => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []): mixed {
    if (!empty($data['third_party_settings']['layout_builder']['sections'])) {
      $sections = &$data['third_party_settings']['layout_builder']['sections'];
      $sections = array_map([Section::class, 'fromArray'], $sections);
    }
    return parent::denormalize($data, $class, $format, $context);
  }

}
