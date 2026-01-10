<?php

namespace Drupal\Core\Entity\Exception;

/**
 * Exception thrown if a bundle class does not exist.
 *
 * @see \Drupal\Core\Entity\ContentEntityStorageBase::getEntityClass()
 */
class MissingBundleClassException extends \Exception {

  public function __construct(string $bundle_class) {
    $message = sprintf('Bundle class %s does not exist.', $bundle_class);
    parent::__construct($message);
  }

}
