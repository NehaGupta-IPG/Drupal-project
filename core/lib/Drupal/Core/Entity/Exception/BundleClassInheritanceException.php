<?php

namespace Drupal\Core\Entity\Exception;

/**
 * Exception thrown if a bundle class does not extend the main entity class.
 *
 * @see \Drupal\Core\Entity\ContentEntityStorageBase::getEntityClass()
 */
class BundleClassInheritanceException extends \Exception {

  public function __construct(string $bundle_class, string $entity_class) {
    $message = sprintf('Bundle class %s does not extend entity class %s.', $bundle_class, $entity_class);
    parent::__construct($message);
  }

}
