<?php

namespace Drupal\jsonapi\Exception;

interface JsonApiErrorSourceInterface {

  public function getSourceValue(): ?array;

}
