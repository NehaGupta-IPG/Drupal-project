<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\jsonapi\ResourceType\ResourceType;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Converts the Drupal config entity object to a JSON:API array structure.
 *
 * @internal JSON:API maintains no PHP API since its API is the HTTP API. This
 *   class may change at any time and this will break any dependencies on it.
 *
 * @see https://www.drupal.org/project/drupal/issues/3032787
 * @see jsonapi.api.php
 */
final class ConfigEntityDenormalizer extends EntityDenormalizerBase {

  /**
   * {@inheritdoc}
   */
  protected function prepareInput(array $data, ResourceType $resource_type, $format, array $context) {
    $prepared = [];
    foreach ($data as $key => $value) {
      $internal_name = $resource_type->getInternalName($key);
      if (!$resource_type->hasField($internal_name)) {
        throw new UnprocessableEntityHttpException(sprintf(
          'The attribute %s does not exist on the %s resource type.',
          $internal_name,
          $resource_type->getTypeName()
        ));
      }
      $prepared[$internal_name] = $value;
    }
    return $prepared;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [
      ConfigEntityInterface::class => TRUE,
    ];
  }

}
