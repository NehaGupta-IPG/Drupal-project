<?php

namespace Drupal\entity_test\Entity;

use Drupal\Core\Entity\EntityBase;
use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Defines a _just_ fieldable entity class.
 *
 * This entity by design not translatable- or revisionable.
 *
 * @EntityType(
 *   id = "fieldable_entity",
 *   label = @Translation("Fieldable entity"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\KeyValueStore\KeyValueEntityStorage",
 *     "list_builder" = "Drupal\entity_test\EntityTestListBuilder",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\entity_test\FieldableEntityForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "fieldable_entity",
 *   admin_permission = "administer fieldable_entity content",
 *   persistent_cache = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "collection" = "/fieldable_entity",
 *     "add-form" = "/fieldable_entity/add",
 *     "edit-form" = "/fieldable_entity/manage/{fieldable_entity}/edit",
 *   }
 * )
 *
 * @property int|string $id
 * @property string $uuid
 *
 * @todo Finalize and improve implementation as part of https://www.drupal.org/project/drupal/issues/3344705.
 */
final class FieldableEntity extends EntityBase implements FieldableEntityInterface {

  /**
   * Whether entity validation is required before saving the entity.
   *
   * @var bool
   */
  protected $validationRequired = FALSE;

  /**
   * Whether entity validation was performed.
   *
   * @var bool
   */
  protected $validated = FALSE;

  /**
   * The array of fields, each being an instance of FieldItemListInterface.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * Local cache for field definitions.
   *
   * @see ContentEntityBase::getFieldDefinitions()
   *
   * @var array
   */
  protected $fieldDefinitions;

  /**
   * The plain data values of the contained fields.
   *
   * @var array
   */
  protected $values = [];

  /**
   * Constructs an FieldableEntity object.
   *
   * @param array $values
   *   An array of values to set, keyed by property name. If the entity type
   *   has bundles, the bundle key has to be specified.
   * @param string $entity_type
   *   The type of the entity to create.
   */
  public function __construct(array $values, $entity_type) {
    $this->entityTypeId = $entity_type;
    if (array_key_exists('id', $values)) {
      $this->id = $values['id'];
      unset($values['id']);
    }
    if (array_key_exists('uuid', $values)) {
      $this->uuid = $values['uuid'];
      unset($values['uuid']);
    }
    $this->values = $values;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = [];
    if ($entity_type->hasKey('id')) {
      $fields[$entity_type->getKey('id')] = BaseFieldDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('ID'))
        ->setReadOnly(TRUE)
        ->setSetting('unsigned', TRUE);
    }
    if ($entity_type->hasKey('uuid')) {
      $fields[$entity_type->getKey('uuid')] = BaseFieldDefinition::create('uuid')
        ->setLabel(new TranslatableMarkup('UUID'))
        ->setReadOnly(TRUE);
    }

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage, array &$values) {
    $values = [
      'uuid' => \Drupal::service('uuid')->generate(),
      'id' => \Drupal::database()->nextId(),
    ] + $values;
    parent::preCreate($storage, $values);
  }

  /**
   * {@inheritdoc}
   */
  public function hasField($field_name) {
    return (bool) $this->getFieldDefinition($field_name);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinition($name) {
    if (!isset($this->fieldDefinitions)) {
      $this->getFieldDefinitions();
    }
    if (isset($this->fieldDefinitions[$name])) {
      return $this->fieldDefinitions[$name];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    if (!isset($this->fieldDefinitions)) {
      $this->fieldDefinitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($this->entityTypeId, $this->bundle());
    }
    return $this->fieldDefinitions;
  }

  /**
   * {@inheritdoc}
   */
  public function get($field_name) {
    return $this->fields[$field_name];
  }

  /**
   * {@inheritdoc}
   */
  public function set($name, $value, $notify = TRUE) {
    // Assign the value on the child and overrule notify such that we get
    // notified to handle changes afterwards. We can ignore notify as there is
    // no parent to notify anyway.
    $this->get($name)->setValue($value, TRUE);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields($include_computed = TRUE) {
    $fields = [];
    foreach ($this->getFieldDefinitions() as $name => $definition) {
      if ($include_computed || !$definition->isComputed()) {
        $fields[$name] = $this->get($name);
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTranslatableFields($include_computed = TRUE) {
    $fields = [];
    foreach ($this->getFieldDefinitions() as $name => $definition) {
      if (($include_computed || !$definition->isComputed()) && $definition->isTranslatable()) {
        $fields[$name] = $this->get($name);
      }
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    // An entity requiring validation should not be saved if it has not been
    // actually validated.
    assert(!$this->validationRequired || $this->validated, 'Entity validation was skipped.');

    $this->validated = FALSE;

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function onChange($field_name) {}

  /**
   * {@inheritdoc}
   */
  public function validate() {
    $this->validated = TRUE;
    $violations = $this->getTypedData()->validate();
    return new EntityConstraintViolationList($this, iterator_to_array($violations));
  }

  /**
   * {@inheritdoc}
   */
  public function isValidationRequired() {
    return (bool) $this->validationRequired;
  }

  /**
   * {@inheritdoc}
   */
  public function setValidationRequired($required) {
    $this->validationRequired = $required;
    return $this;
  }

  /**
   * Implements the magic method for getting object properties.
   *
   * @todo: A lot of code still uses non-fields (e.g. $entity->content in view
   *   builders) by reference. Clean that up.
   */
  public function &__get($name) {
    // If this is an entity field, handle it accordingly. We first check whether
    // a field object has been already created. If not, we create one.
    if (isset($this->fields[$name])) {
      return $this->fields[$name];
    }
    // Inline getFieldDefinition() to speed things up.
    if (!isset($this->fieldDefinitions)) {
      $this->getFieldDefinitions();
    }
    if (isset($this->fieldDefinitions[$name])) {
      return $this->fields[$name];
    }
    // Else directly read/write plain values. That way, non-field entity
    // properties can always be accessed directly.
    if (!isset($this->values[$name])) {
      $this->values[$name] = NULL;
    }
    return $this->values[$name];
  }

  /**
   * Implements the magic method for setting object properties.
   *
   * Uses default language always.
   */
  public function __set($name, $value) {
    // Inline getFieldDefinition() to speed things up.
    if (!isset($this->fieldDefinitions)) {
      $this->getFieldDefinitions();
    }
    // Handle Field API fields.
    if (isset($this->fieldDefinitions[$name])) {
      // Support setting values via property objects.
      if ($value instanceof TypedDataInterface) {
        $value = $value->getValue();
      }
      // If a FieldItemList object already exists, set its value.
      if (isset($this->fields[$name])) {
        $this->fields[$name]->setValue($value);
      }
      // If not, create one.
      else {
        $field = \Drupal::service('plugin.manager.field.field_type')->createFieldItemList($this, $name, $value);
        $this->fields[$name] = $field;
      }
    }
    // Directly write non-field values.
    else {
      $this->values[$name] = $value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id->value;
  }

  /**
   * {@inheritdoc}
   */
  public function uuid() {
    return $this->uuid->value;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $values = [];
    foreach ($this->getFields() as $name => $property) {
      // Temporary fix for smoother entity query support with
      // \Drupal\Core\Entity\KeyValueStore\Query\Query.
      if (in_array($name, ['id', 'uuid'], TRUE)) {
        $values[$name] = $property->value;
      }
      else {
        $values[$name] = $property->getValue();
      }
    }
    return $values;
  }

}
