<?php

namespace Drupal\jsonapi\Exception;

/**
 * Trait for HTTP exception which specify a source.
 */
trait JsonApiErrorSourceTrait {

  /**
   * Error source value object.
   *
   * @var \Drupal\jsonapi\Exception\JsonApiErrorSource
   */
  protected ?JsonApiErrorSource $source = NULL;

  /**
   * Getter for the error source.
   *
   * @return \Drupal\jsonapi\Exception\JsonApiErrorSource
   */
  public function getSourceValue(): ?array {
    return $this->source?->toArray();
  }

}
