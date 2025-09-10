<?php

namespace Drupal\serialization\Normalizer;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Exception\AmbiguousEntityClassException;
use Drupal\Core\Entity\Exception\NoCorrespondingEntityClassException;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * A trait for providing fieldable entity normalization/denormalization methods.
 *
 * @todo Move this into a FieldableEntityNormalizer in Drupal 9. This is a trait
 *   used in \Drupal\serialization\Normalizer\EntityNormalizer to maintain BC.
 *   @see https://www.drupal.org/node/2834734
 */
trait FieldableEntityNormalizerTrait {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type repository.
   *
   * @var \Drupal\Core\Entity\EntityTypeRepositoryInterface
   */
  protected $entityTypeRepository;

  /**
   * Determine entity type ID while letting context override the $class param.
   *
   * This method:
   *  - uses $context['entity_type'] if available,
   *  - attempts to infer entity type from
   *    $context['request_data'] payload (bundle field),
   *  - falls back to class lookup via entity type repository,
   *  - and if everything fails throws UnexpectedValueException.
   *
   * @param string $class
   *   The fully-qualified class name.
   * @param array $context
   *   The serializer context.
   *
   * @return string
   *   The entity type id.
   *
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   *   If no entity type can be determined.
   */
  protected function determineEntityTypeId($class, $context) {
    // 1) Context override (existing behavior).
    if (!empty($context['entity_type'])) {
      return $context['entity_type'];
    }

    // 2) If request payload is available try to infer entity type from a bundle
    // field in the payload. We expect request data to be set in the context by
    // EntityNormalizer::denormalize() as 'request_data'.
    if (!empty($context['request_data']) && is_array($context['request_data'])) {
      $request_data = $context['request_data'];

      // Look for fields that look like bundle properties (an array whose first
      // item contains 'target_id' or 'name').
      foreach ($request_data as $payload_key => $value) {
        if (!is_array($value) || empty($value[0]) || !is_array($value[0])) {
          continue;
        }

        $candidate_bundle = NULL;
        if (isset($value[0]['target_id'])) {
          $candidate_bundle = $value[0]['target_id'];
        }
        elseif (isset($value[0]['name'])) {
          $candidate_bundle = $value[0]['name'];
        }

        if ($candidate_bundle === NULL) {
          continue;
        }

        // Now loop over entity type definitions and look for an entity type
        // that uses this payload_key as its bundle key name.
        try {
          $definitions = $this->getEntityTypeManager()->getDefinitions();
        }
        catch (\Throwable $e) {
          // If we cannot retrieve definitions, skip inference and fall back.
          $definitions = [];
        }

        foreach ($definitions as $entity_type_id => $definition) {
          if (!$definition->hasKey('bundle')) {
            continue;
          }

          // If the entity type's bundle key matches the payload key, verify the
          // bundle exists by checking bundle storage query results.
          $bundle_key = $definition->getKey('bundle');
          if ($bundle_key !== $payload_key) {
            continue;
          }

          // Get the bundle entity type and ask its storage
          // for existing bundles.
          $bundle_entity_type = $definition->getBundleEntityType();
          if (empty($bundle_entity_type)) {
            continue;
          }

          try {
            $bundle_storage = $this->getEntityTypeManager()->getStorage($bundle_entity_type);
          }
          catch (\Throwable $e) {
            // If we cannot load storage for the bundle entity type, skip.
            continue;
          }

          // Use the query as production code would:
          // accessCheck(TRUE)->execute().
          try {
            $query = $bundle_storage->getQuery()->accessCheck(TRUE);
            $existent_bundles = $query->execute();
          }
          catch (\Throwable $e) {
            // If query fails for some reason, skip.
            $existent_bundles = [];
          }

          if (!empty($existent_bundles) && isset($existent_bundles[$candidate_bundle])) {
            return $entity_type_id;
          }
        }
      }
    }

    // 3) Fallback: class-based lookup.
    // If this fails or is ambiguous, convert the
    // result into an UnexpectedValueException to match existing tests.
    try {
      $entity_type_id = $this->getEntityTypeRepository()->getEntityTypeFromClass($class);
      if (!empty($entity_type_id)) {
        return $entity_type_id;
      }
    }
    catch (AmbiguousEntityClassException | NoCorrespondingEntityClassException $e) {
      // Convert core exceptions to the UnexpectedValueException
      // expected by tests.
      throw new UnexpectedValueException('The entity type ID could not be determined.', 0, $e);
    }
    catch (\Throwable $e) {
      // Any other unexpected failure — wrap it for the serializer tests.
      throw new UnexpectedValueException('The entity type ID could not be determined.', 0, $e);
    }

    // If we reach here, we couldn't determine the entity type.
    throw new UnexpectedValueException('The entity type ID could not be determined.');
  }

  /**
   * Gets the entity type definition.
   *
   * @param string $entity_type_id
   *   The entity type ID to load the definition for.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The loaded entity type definition.
   *
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   */
  protected function getEntityTypeDefinition($entity_type_id) {
    /** @var \Drupal\Core\Entity\EntityTypeInterface $entity_type_definition */
    // Get the entity type definition.
    $entity_type_definition = $this->getEntityTypeManager()->getDefinition($entity_type_id, FALSE);

    // Don't try to create an entity without an entity type id.
    if (!$entity_type_definition) {
      throw new UnexpectedValueException(sprintf('The specified entity type "%s" does not exist. A valid entity type is required for denormalization', $entity_type_id));
    }

    return $entity_type_definition;
  }

  /**
   * Denormalizes the bundle property so entity creation can use it.
   *
   * @param array $data
   *   The data being denormalized. The bundle information will be removed.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type_definition
   *   The entity type definition.
   *
   * @throws \Symfony\Component\Serializer\Exception\UnexpectedValueException
   *   If the bundle value is invalid or the bundle type is ineligible.
   *
   * @return array
   *   An array containing a single $bundle_key => $bundle_value pair.
   */
  protected function extractBundleData(array &$data, EntityTypeInterface $entity_type_definition) {
    $bundle_key = $entity_type_definition->getKey('bundle');
    // Get the base field definitions for this entity type.
    $base_field_definitions = $this->getEntityFieldManager()->getBaseFieldDefinitions($entity_type_definition->id());

    // Get the ID key from the base field definition for the bundle key or
    // default to 'value'.
    $key_id = isset($base_field_definitions[$bundle_key]) ? $base_field_definitions[$bundle_key]->getFieldStorageDefinition()->getMainPropertyName() : 'value';

    // Normalize the bundle if it is not explicitly set.
    $bundle_value = $data[$bundle_key][0][$key_id] ?? ($data[$bundle_key] ?? NULL);
    // Unset the bundle from the data.
    unset($data[$bundle_key]);

    // Get the bundle entity type from the entity type definition.
    $bundle_type_id = $entity_type_definition->getBundleEntityType();
    $bundle_types = $bundle_type_id ? $this->getEntityTypeManager()->getStorage($bundle_type_id)->getQuery()->accessCheck(TRUE)->execute() : [];

    // Make sure a bundle has been provided.
    if (!is_string($bundle_value)) {
      throw new UnexpectedValueException(sprintf('Could not determine entity type bundle: "%s" field is missing.', $bundle_key));
    }

    // Make sure the submitted bundle is a valid bundle for the entity type.
    if ($bundle_types && !in_array($bundle_value, $bundle_types)) {
      throw new UnexpectedValueException(sprintf('"%s" is not a valid bundle type for denormalization.', $bundle_value));
    }

    return [$bundle_key => $bundle_value];
  }

  /**
   * Denormalizes entity data by denormalizing each field individually.
   *
   * @param array $data
   *   The data to denormalize.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The fieldable entity to set field values for.
   * @param string $format
   *   The serialization format.
   * @param array $context
   *   The context data.
   */
  protected function denormalizeFieldData(array $data, FieldableEntityInterface $entity, $format, array $context) {
    foreach ($data as $field_name => $field_data) {
      $field_item_list = $entity->get($field_name);

      // Remove any values that were set as a part of entity creation (e.g
      // uuid). If the incoming field data is set to an empty array, this will
      // also have the effect of emptying the field in REST module.
      $field_item_list->setValue([]);
      $field_item_list_class = get_class($field_item_list);

      if ($field_data) {
        // The field instance must be passed in the context so that the field
        // denormalizer can update field values for the parent entity.
        $context['target_instance'] = $field_item_list;
        $this->serializer->denormalize($field_data, $field_item_list_class, $format, $context);
      }
    }
  }

  /**
   * Returns the entity type repository.
   *
   * @return \Drupal\Core\Entity\EntityTypeRepositoryInterface
   *   The entity type repository.
   */
  protected function getEntityTypeRepository() {
    return $this->entityTypeRepository;
  }

  /**
   * Returns the entity field manager.
   *
   * @return \Drupal\Core\Entity\EntityFieldManagerInterface
   *   The entity field manager.
   */
  protected function getEntityFieldManager() {
    return $this->entityFieldManager;
  }

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager;
  }

  /**
   * Build the field item value using the incoming data.
   *
   * Most normalizers that extend this class can simply use this method to
   * construct the denormalized value without having to override denormalize()
   * and re-implementing its validation logic or its call to set the field
   * value.
   *
   * It's recommended to not override this and instead provide a (de)normalizer
   * at the DataType level.
   *
   * @param mixed $data
   *   The incoming data for this field item.
   * @param array $context
   *   The context passed into the Normalizer.
   *
   * @return mixed
   *   The value to use in \Drupal\Core\Field\FieldItemBase::setValue() or a
   *   subclass.
   */
  protected function constructValue($data, $context) {
    $field_item = $context['target_instance'];

    // Get the property definitions.
    assert($field_item instanceof FieldItemInterface);
    $field_definition = $field_item->getFieldDefinition();
    $item_definition = $field_definition->getItemDefinition();
    assert($item_definition instanceof FieldItemDataDefinitionInterface);
    $property_definitions = $item_definition->getPropertyDefinitions();

    $serialized_property_names = $this->getCustomSerializedPropertyNames($field_item);
    $denormalize_property = function ($property_name, $property_value, $property_value_class, $context) use ($serialized_property_names) {
      if ($this->serializer->supportsDenormalization($property_value, $property_value_class, NULL, $context)) {
        return $this->serializer->denormalize($property_value, $property_value_class, NULL, $context);
      }
      else {
        if (in_array($property_name, $serialized_property_names, TRUE)) {
          $property_value = serialize($property_value);
        }
        return $property_value;
      }
    };

    if (!is_array($data)) {
      $property_value = $data;
      $property_name = $item_definition->getMainPropertyName();
      $property_value_class = $property_definitions[$property_name]->getClass();
      return $denormalize_property($property_name, $property_value, $property_value_class, $context);
    }

    $data_internal = [];
    if (!empty($property_definitions)) {
      foreach ($property_definitions as $property_name => $property_definition) {
        // Not every property is required to be sent.
        if (!array_key_exists($property_name, $data)) {
          continue;
        }
        $property_value = $data[$property_name];
        $property_value_class = $property_definition->getClass();
        $data_internal[$property_name] = $denormalize_property($property_name, $property_value, $property_value_class, $context);
      }
    }
    else {
      $data_internal = $data;
    }

    return $data_internal;
  }

}
