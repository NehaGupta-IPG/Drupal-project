<?php

namespace Drupal\jsonapi\Normalizer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\TypedDataInterface;
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
    // Config entity constructors may require plugin selectors that are absent
    // from a partial PATCH document. Only explicitly supplied properties will
    // be copied back to the target entity by the controller.
    if (($context['existing_config_entity'] ?? NULL) instanceof ConfigEntityInterface) {
      $prepared += $context['existing_config_entity']->toArray();
    }
    // Check input types before entity constructors and plugin collections can
    // interpret them. For example, a numeric string may be treated as an enum
    // alias rather than the integer required by the resolved config schema.
    $entity_type = $this->entityTypeManager->getDefinition($resource_type->getEntityTypeId());
    $prefix = $entity_type->getConfigPrefix();
    $typed_config_manager = \Drupal::service('config.typed');
    foreach ($typed_config_manager->getDefinitions() as $name => $definition) {
      if (str_starts_with($name, $prefix . '.*') && str_replace('.*', '', $name) === $prefix) {
        $typed_config = $typed_config_manager->createFromNameAndData($name, $prepared);
        foreach ($prepared as $property_name => $value) {
          $this->validateInputType($typed_config->get($property_name), $resource_type->getPublicName($property_name));
        }
        break;
      }
    }
    return $prepared;
  }

  /**
   * Rejects malformed primitive input before constructing config entities.
   */
  private function validateInputType(TypedDataInterface $data, string $path): void {
    $value = $data->getValue();
    if ($data instanceof PrimitiveInterface && is_array($value)) {
      throw new UnprocessableEntityHttpException(sprintf('The attribute %s must be a scalar.', $path));
    }
    if ($data instanceof IntegerInterface && $value !== NULL && !is_int($value)) {
      throw new UnprocessableEntityHttpException(sprintf('The attribute %s must be an integer.', $path));
    }
    if ($data instanceof TraversableTypedDataInterface && is_array($value)) {
      foreach ($data as $name => $child) {
        $this->validateInputType($child, $path . '.' . $name);
      }
    }
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
