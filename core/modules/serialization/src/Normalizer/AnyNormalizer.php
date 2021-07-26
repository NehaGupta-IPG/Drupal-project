<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\TypedData\Plugin\DataType\Any;

/**
 * Converts 'any' typed data objects to arrays.
 */
class AnyNormalizer extends NormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected $supportedInterfaceOrClass = Any::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $this->addCacheableDependency($context, $object);
    $value = $object->getValue();
    // If the value is object or array, continue normalize it, object must be
    // normalizable. Traversable object is one of the objects which can be
    // normalized, field type with typed data 'any' property definition which
    // stored the traversable object is advised to convert posted data to object
    // in setValue(), so that data can be denormalized automatically without
    // writing custom code to convert data to object when using machine readable
    // APIs.
    if (isset($value) && (is_object($value) || is_array($value))) {
      $value = $this->serializer->normalize($value, $format, $context);
    }
    return $value;
  }

}
