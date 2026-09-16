<?php

namespace Drupal\Core\TypedData;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Validation\RecursiveValidator;
use Drupal\Core\Validation\ConstraintManager;
use Drupal\Core\Validation\ConstraintValidatorFactory;
use Drupal\Core\Validation\DrupalTranslator;
use Drupal\Core\Validation\ExecutionContextFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Manages data type plugins.
 */
class TypedDataManager extends DefaultPluginManager implements TypedDataManagerInterface {
  use DependencySerializationTrait {
    __sleep as private dependencySerializationSleep;
  }

  /**
   * The validator used for validating typed data.
   *
   * @var \Symfony\Component\Validator\Validator\ValidatorInterface
   */
  protected $validator;

  /**
   * The validation constraint manager to use for instantiating constraints.
   *
   * @var \Drupal\Core\Validation\ConstraintManager
   */
  protected $constraintManager;

  /**
   * Typed data property prototypes, keyed by their data definition object.
   *
   * The map is created lazily and is never serialized, so it is nullable and
   * must not be initialized in the constructor: a typed property that is
   * excluded from __sleep() would stay uninitialized after unserialization.
   *
   * @var \WeakMap<\Drupal\Core\TypedData\DataDefinitionInterface, \Drupal\Core\TypedData\TypedDataInterface>|null
   */
  protected ?\WeakMap $prototypes = NULL;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs a new TypedDataManager.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ClassResolverInterface $class_resolver) {
    $this->alterInfo('data_type_info');
    $this->setCacheBackend($cache_backend, 'typed_data_types_plugins');
    $this->classResolver = $class_resolver;

    parent::__construct(
      'Plugin/DataType',
      $namespaces,
      $module_handler,
      NULL,
      DataType::class,
      'Drupal\Core\TypedData\Annotation\DataType',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($data_type, array $configuration = []) {
    $data_definition = $configuration['data_definition'];
    $type_definition = $this->getDefinition($data_type);

    if (!isset($type_definition)) {
      throw new \InvalidArgumentException("Invalid data type '$data_type' has been given");
    }

    // Allow per-data definition overrides of the used classes, i.e. take over
    // classes specified in the type definition.
    $class = $data_definition->getClass();

    if (!isset($class)) {
      throw new PluginException(sprintf('The plugin (%s) did not specify an instance class.', $data_type));
    }
    $typed_data = $class::createInstance($data_definition, $configuration['name'], $configuration['parent']);
    $typed_data->setTypedDataManager($this);
    return $typed_data;
  }

  /**
   * {@inheritdoc}
   */
  public function create(DataDefinitionInterface $definition, $value = NULL, $name = NULL, $parent = NULL) {
    $typed_data = $this->createInstance($definition->getDataType(), [
      'data_definition' => $definition,
      'name' => $name,
      'parent' => $parent,
    ]);
    if (isset($value)) {
      $typed_data->setValue($value, FALSE);
    }
    return $typed_data;
  }

  /**
   * {@inheritdoc}
   */
  public function createDataDefinition($data_type) {
    $type_definition = $this->getDefinition($data_type);
    if (!isset($type_definition)) {
      throw new \InvalidArgumentException("Invalid data type '$data_type' has been given");
    }
    $class = $type_definition['definition_class'];
    $data_definition = $class::createFromDataType($data_type);

    if (method_exists($data_definition, 'setTypedDataManager')) {
      $data_definition->setTypedDataManager($this);
    }

    return $data_definition;
  }

  /**
   * {@inheritdoc}
   */
  public function createListDataDefinition($item_type) {
    $type_definition = $this->getDefinition($item_type);
    if (!isset($type_definition)) {
      throw new \InvalidArgumentException("Invalid data type '$item_type' has been given");
    }
    $class = $type_definition['list_definition_class'];
    return $class::createFromItemType($item_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    return $this->getPropertyInstance($options['object'], $options['property'], $options['value']);
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyInstance(TypedDataInterface $object, $property_name, $value = NULL) {
    // Fetch the data definition for the child object from the parent.
    if ($object instanceof ComplexDataInterface) {
      $definition = $object->getDataDefinition()->getPropertyDefinition($property_name);
    }
    elseif ($object instanceof ListInterface) {
      $definition = $object->getItemDefinition();
    }
    else {
      throw new \InvalidArgumentException("The passed object has to either implement the ComplexDataInterface or the ListInterface.");
    }
    if (!$definition) {
      throw new \InvalidArgumentException("Property $property_name is unknown.");
    }

    // For performance, try to reuse existing prototypes instead of
    // constructing new objects when possible. A prototype is derived from the
    // definition it was built from: create() takes the class from
    // $definition->getClass(), and the name and parent passed below are wiped
    // again by setContext(NULL, NULL). Prototypes are therefore keyed by the
    // definition object itself, so that two distinct definition objects never
    // share one.
    //
    // Any coarser key, such as the root data type plus the property path that
    // this cache used previously, collides whenever two separately built
    // definitions declare a same-named property of a different data type,
    // class, settings or constraint set. The colliding lookup then returns an
    // object of the wrong class, or one carrying constraints that belong to
    // the other definition. Keying on the definition object also keeps the
    // cache honest when a definition is rebuilt on purpose, for example by
    // \Drupal\field\Entity\FieldConfigBase::setPropertyConstraints() or by
    // ListDataDefinition::__clone().
    //
    // The trade-off is that a caller building a fresh definition object per
    // instantiation no longer hits the cache, while the previous key merged
    // such definitions onto a single prototype. Core builds property and item
    // definitions once and memoizes them, so entity field access is
    // unaffected.
    //
    // The prototypes are held in a \WeakMap so that each one is released
    // together with the data definition it was built from, instead of growing
    // an array that is only ever emptied by clearCachedDefinitions().
    $this->prototypes ??= new \WeakMap();
    $prototype = $this->prototypes[$definition] ?? NULL;

    // Create the prototype if needed.
    if ($prototype === NULL) {
      // Create the prototype without any value, but with initial parenting
      // so that constructors can set up the objects correctly.
      $prototype = $this->create($definition, NULL, $property_name, $object);
      // Unset the parent so that it is not statically cached within the
      // prototype. It will be set again later anyway.
      $prototype->setContext(NULL, NULL);
      $this->prototypes[$definition] = $prototype;
    }

    // Clone the prototype, update its parenting information, and assign the
    // value.
    $property = clone $prototype;
    $property->setContext($property_name, $object);
    if (isset($value)) {
      $property->setValue($value, FALSE);
    }
    return $property;
  }

  /**
   * Sets the validator for validating typed data.
   *
   * @param \Symfony\Component\Validator\Validator\ValidatorInterface $validator
   *   The validator object to set.
   */
  public function setValidator(ValidatorInterface $validator) {
    $this->validator = $validator;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidator() {
    if (!isset($this->validator)) {
      $this->validator = new RecursiveValidator(
        new ExecutionContextFactory(new DrupalTranslator()),
        new ConstraintValidatorFactory($this->classResolver),
        $this
      );
    }
    return $this->validator;
  }

  /**
   * {@inheritdoc}
   */
  public function setValidationConstraintManager(ConstraintManager $constraintManager) {
    $this->constraintManager = $constraintManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getValidationConstraintManager() {
    return $this->constraintManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultConstraints(DataDefinitionInterface $definition) {
    $constraints = [];
    $type_definition = $this->getDefinition($definition->getDataType());
    // Auto-generate a constraint for data types implementing a primitive
    // interface.
    if (is_subclass_of($type_definition['class'], '\Drupal\Core\TypedData\PrimitiveInterface')) {
      $constraints['PrimitiveType'] = [];
    }
    // Add in constraints specified by the data type.
    if (isset($type_definition['constraints'])) {
      $constraints += $type_definition['constraints'];
    }
    // Add the NotNull constraint for required data.
    if ($definition->isRequired()) {
      $constraints['NotNull'] = [];
    }
    // Check if the class provides allowed values.
    if (is_subclass_of($definition->getClass(), 'Drupal\Core\TypedData\OptionsProviderInterface')) {
      $constraints['AllowedValues'] = [];
    }
    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();
    $this->prototypes = new \WeakMap();
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    // \WeakMap objects cannot be serialized, not even when they are empty. The
    // prototypes are a cache, so they are dropped here and lazily rebuilt
    // after unserialization.
    return array_diff($this->dependencySerializationSleep(), ['prototypes']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCanonicalRepresentation(TypedDataInterface $data) {
    $data_definition = $data->getDataDefinition();
    // In case a list is passed, respect the 'wrapped' key of its data type.
    if ($data_definition instanceof ListDataDefinitionInterface) {
      $data_definition = $data_definition->getItemDefinition();
    }
    // Get the plugin definition of the used data type.
    $type_definition = $this->getDefinition($data_definition->getDataType());
    if (!empty($type_definition['unwrap_for_canonical_representation'])) {
      return $data->getValue();
    }
    return $data;
  }

}
