<?php

namespace Drupal\Core\Config;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\Schema\ArrayElement;
use Drupal\Core\Config\Schema\ConfigSchemaAlterException;
use Drupal\Core\Config\Schema\ConfigSchemaDiscovery;
use Drupal\Core\Config\Schema\Ignore;
use Drupal\Core\Config\Schema\TypeResolver;
use Drupal\Core\Config\Schema\SequenceDataDefinition;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Config\Schema\Undefined;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\TypedData\MapDataDefinition;
use Drupal\Core\TypedData\TraversableTypedDataInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Drupal\Core\Validation\Plugin\Validation\Constraint\FullyValidatableConstraint;

/**
 * Manages config schema type plugins.
 */
class TypedConfigManager extends TypedDataManager implements TypedConfigManagerInterface {

  /**
   * A storage instance for reading configuration data.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $configStorage;

  /**
   * A storage instance for reading configuration schema data.
   *
   * @var \Drupal\Core\Config\StorageInterface
   */
  protected $schemaStorage;

  /**
   * The array of plugin definitions, keyed by plugin id.
   *
   * @var array
   */
  protected $definitions;

  /**
   * Creates a new typed configuration manager.
   *
   * @param \Drupal\Core\Config\StorageInterface $configStorage
   *   The storage object to use for reading schema data.
   * @param \Drupal\Core\Config\StorageInterface $schemaStorage
   *   The storage object to use for reading schema data.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend to use for caching the definitions.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   (optional) The class resolver.
   */
  public function __construct(StorageInterface $configStorage, StorageInterface $schemaStorage, CacheBackendInterface $cache, ModuleHandlerInterface $module_handler, ?ClassResolverInterface $class_resolver = NULL) {
    $this->configStorage = $configStorage;
    $this->schemaStorage = $schemaStorage;
    $this->setCacheBackend($cache, 'typed_config_definitions');
    $this->alterInfo('config_schema_info');
    $this->moduleHandler = $module_handler;
    $this->classResolver = $class_resolver ?: \Drupal::service('class_resolver');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery() {
    if (!isset($this->discovery)) {
      $this->discovery = new ConfigSchemaDiscovery($this->schemaStorage);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function get($name) {
    $data = $this->configStorage->read($name);
    if ($data === FALSE) {
      // For a typed config the data MUST exist.
      throw new \InvalidArgumentException("Missing required data for typed configuration: $name");
    }
    return $this->createFromNameAndData($name, $data);
  }

  /**
   * {@inheritdoc}
   */
  public function buildDataDefinition(array $definition, $value, $name = NULL, $parent = NULL) {
    // Add default values for data type and replace variables.
    $definition += ['type' => 'undefined'];

    $replace = [];
    $type = $definition['type'];
    if (strpos($type, ']')) {
      // Replace variable names in definition.
      $replace = is_array($value) ? $value : [];
      if (isset($parent)) {
        $replace['%parent'] = $parent;
      }
      if (isset($name)) {
        $replace['%key'] = $name;
      }
      $type = TypeResolver::resolveDynamicTypeName($type, $replace);
      // Remove the type from the definition so that it is replaced with the
      // concrete type from schema definitions.
      unset($definition['type']);
    }
    // Add default values from type definition.
    $definition += $this->getDefinitionWithReplacements($type, $replace);

    $data_definition = $this->createDataDefinition($definition['type']);

    // Pass remaining values from definition array to data definition.
    foreach ($definition as $key => $value) {
      if (!isset($data_definition[$key])) {
        $data_definition[$key] = $value;
      }
    }

    // All values are optional by default (meaning they can be NULL), except for
    // mappings and sequences. A sequence can only be NULL when `nullable: true`
    // is set on the config schema type definition. This is unintuitive and
    // contradicts Drupal core's documentation.
    // @see https://www.drupal.org/node/2264179
    // @see https://www.drupal.org/node/1978714
    // To gradually evolve configuration schemas in the Drupal ecosystem to be
    // validatable, this needs to be clarified in a non-disruptive way. Any
    // config schema type definition — that is, a top-level entry in a
    // *.schema.yml file — can opt into stricter behavior, whereby a property
    // cannot be NULL unless it specifies `nullable: true`, by adding
    // `FullyValidatable` as a top-level validation constraint.
    // @see https://www.drupal.org/node/3364108
    // @see https://www.drupal.org/node/3364109
    // @see \Drupal\Core\TypedData\TypedDataManager::getDefaultConstraints()
    if ($parent) {
      $root_type = $parent->getRoot()->getDataDefinition()->getDataType();
      $root_type_has_opted_in = FALSE;
      foreach ($parent->getRoot()->getConstraints() as $constraint) {
        if ($constraint instanceof FullyValidatableConstraint) {
          $root_type_has_opted_in = TRUE;
          break;
        }
      }
      // If this is a dynamically typed property path, then not only must the
      // (absolute) root type be considered, but also the (relative) static root
      // type: the resolved type.
      // For example, `block.block.*:settings` has a dynamic type defined:
      // `block.settings.[%parent.plugin]`, but `block.block.*:plugin` does not.
      // Consequently, the value at the `plugin` property path depends only on
      // the `block.block.*` config schema type and hence only that config
      // schema type must have the `FullyValidatable` constraint, because it
      // defines which value are required.
      // In contrast, the `block.block.*:settings` property path depends on
      // whichever dynamic type `block.settings.[%parent.plugin]` resolved to,
      // to be able to know which values are required. Therefore that resolved
      // type determines which values are required and whether it is fully
      // validatable.
      // So for example the `block.settings.system_branding_block` config schema
      // type would also need to have the `FullyValidatable` constraint to
      // consider its schema-defined keys to require values:
      // - use_site_logo
      // - use_site_name
      // - use_site_slogan
      $static_type_root = TypedConfigManager::getStaticTypeRoot($parent);
      $static_type_root_type = $static_type_root->getDataDefinition()->getDataType();
      if ($root_type !== $static_type_root_type) {
        $root_type_has_opted_in = FALSE;
        foreach ($static_type_root->getConstraints() as $c) {
          if ($c instanceof FullyValidatableConstraint) {
            $root_type_has_opted_in = TRUE;
            break;
          }
        }
      }
      if ($root_type_has_opted_in) {
        $data_definition->setRequired(!isset($data_definition['nullable']) || $data_definition['nullable'] === FALSE);
      }
    }

    return $data_definition;
  }

  /**
   * Gets the static type root for a config schema object.
   *
   * @param \Drupal\Core\TypedData\TraversableTypedDataInterface $object
   *   A config schema object to get the static type root for.
   *
   * @return \Drupal\Core\TypedData\TraversableTypedDataInterface
   *   The ancestral config schema object at which the static type root lies:
   *   either the first ancestor with a dynamic type (for example:
   *   `block.block.*:settings`, which has the `block.settings.[%parent.plugin]`
   *   type) or the (absolute) root of the config object (in this example:
   *   `block.block.*`).
   */
  public static function getStaticTypeRoot(TraversableTypedDataInterface $object): TraversableTypedDataInterface {
    $root = $object->getRoot();
    $static_type_root = NULL;

    while ($static_type_root === NULL && $object !== $root) {
      // Use the parent data definition to determine the type of this mapping
      // (including the dynamic placeholders). For example:
      // - `editor.settings.[%parent.editor]`
      // - `editor.image_upload_settings.[status]`.
      $parent_data_def = $object->getParent()->getDataDefinition();
      $original_mapping_type = match (TRUE) {
        $parent_data_def instanceof MapDataDefinition => $parent_data_def->toArray()['mapping'][$object->getName()]['type'],
        $parent_data_def instanceof SequenceDataDefinition => $parent_data_def->toArray()['sequence']['type'],
        default => throw new \LogicException('Invalid config schema detected.'),
      };

      // If this mapping's type was dynamically defined, then this is the static
      // type root inside which all types are statically defined.
      if (str_contains($original_mapping_type, ']')) {
        $static_type_root = $object;
        break;
      }

      $object = $object->getParent();
    }

    // Either the discovered static type root is not the actual root, or no
    // static type root was found and it is the root config object.
    assert(($static_type_root !== NULL && $static_type_root !== $root) || ($static_type_root === NULL && $object->getParent() === NULL));

    return $static_type_root ?? $root;
  }

  /**
   * Determines the typed config type for a plugin ID.
   *
   * @param string $base_plugin_id
   *   The plugin ID.
   * @param array $definitions
   *   An array of typed config definitions.
   *
   * @return string
   *   The typed config type for the given plugin ID.
   */
  protected function determineType($base_plugin_id, array $definitions) {
    if (isset($definitions[$base_plugin_id])) {
      $type = $base_plugin_id;
    }
    elseif (strpos($base_plugin_id, '.') && $name = $this->getFallbackName($base_plugin_id)) {
      // Found a generic name, replacing the last element by '*'.
      $type = $name;
    }
    else {
      // If we don't have definition, return the 'undefined' element.
      $type = 'undefined';
    }
    return $type;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);
    static::validateType($definition, $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function setCachedDefinitions($definitions): void {
    assert($this->definitions === NULL);
    parent::setCachedDefinitions($definitions);
    assert($this->definitions !== NULL);

    // Before this method, not all config schema types were known. After calling
    // the parent method, all config schema types are known and stored in
    // $this->definitions.
    // Because caching this happens only once, it is appropriate to ensure the
    // combination of these config schema types together makes sense.
    foreach ($this->definitions as $plugin_id => &$definition) {
      // TRICKY: Validating the absence of circular type references requires
      // knowing all config schema type definitions. Hence this cannot happen in
      // ::processDefinition().
      $this->validateNoCircularTypeReference($definition, $plugin_id);
    }
  }

  /**
   * Validates the "type" key of a config schema definition.
   *
   * @param array $definition
   *   A config schema type definition.
   * @param string $id
   *   A config schema type ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the config schema type definition is:
   *   - Incomplete (missing 'class' when defining a new type).
   *   - Its `class` does not subclass PrimitiveInterface for scalar types.
   *   - Its `class` does not extend ArrayElement for list or complex types.
   *   - Its `definition_class` does not implement DataDefinitionInterface.
   *   - It contains a circular type reference.
   */
  protected function validateType(array $definition, string $id): void {
    // If a config schema does not define a new type, but uses an existing one,
    // there's nothing left to validate.
    if (isset($definition['type'])) {
      return;
    }

    // This type does not narrow an existing type (for example: `type: string` +
    // validation constraints to only allow license plates), and is hence a new
    // "low level type". So its `class` and `definition_class` must meet basic
    // expectations of the configuration (schema) system.
    if (!isset($definition['class'])) {
      throw new InvalidPluginDefinitionException($id, sprintf('"%s" claims to be a new config schema type, but it does not provide a class.', $id));
    }
    switch (static::getShape($definition)) {
      case 'arbitrary':
        // When arbitrary data can be stored, there is nothing to validate.
        break;

      // All scalar types' classes must implement PrimitiveInterface.
      case 'scalar':
        if (!is_subclass_of($definition['class'], PrimitiveInterface::class)) {
          throw new InvalidPluginDefinitionException($id, sprintf('"%s" appears to be a primitive config schema type (for representing scalar values), but its class %s does not implement %s. Its class must either implement that interface or specify a definition class.', $id, $definition['class'], PrimitiveInterface::class));
        }
        break;

      // All other types must be arrays and can be either:
      // - list (`type: sequence`)
      // - complex (`type: mapping`).
      // They must all extend ArrayElement.
      case 'list':
      case 'complex':
        if (!is_subclass_of($definition['class'], ArrayElement::class)) {
          throw new InvalidPluginDefinitionException($id, sprintf('"%s" claims to be a complex config schema type (for representing array-like values), but its class %s does not extend %s.', $id, $definition['class'], ArrayElement::class));
        }
        break;

      case 'no-data-definition':
        throw new InvalidPluginDefinitionException($id, sprintf('The definition class for "%s" must implement %s.', $id, DataDefinitionInterface::class));
    }
  }

  /**
   * Gets the shape of a config schema type definition.
   *
   * @param array $definition
   *   A config schema type definition.
   *
   * @return string
   *   One of:
   *   - "arbitrary": for the "ignore" and "undefined" types that allow
   *     arbitrary values.
   *   - "list": for any list type (in core, only "sequence")
   *   - "complex": for any complex type (in core, only "mapping")
   *   - "scalar": for any other type (in core, primitives like "string", etc.)
   *   - "no-data-definition": for an invalid definition class.
   */
  private static function getShape(array $definition): string {
    return match (TRUE) {
      // Two cases that allow arbitrary values: "ignore" and "undefined".
      in_array($definition['class'], [Undefined::class, Ignore::class], TRUE) => 'arbitrary',
      // Optional: default is set later, in ::getDefinitionWithReplacements().
      !isset($definition['definition_class']) => 'scalar',
      // The three normal shapes:
      // - (for ALL data, a data definition must exist describing its structure)
      // - for data containing more than a single value, the Typed Data objects
      //   must implement TraversableTypedDataInterface, and two kinds of
      //   traversable data are supported:
      //   1. data shaped like "a list of values": ListDataDefinitionInterface
      //   must be used — in core this is only SequenceDataDefinition
      //   2. data shaped like "a bunch of key-value pairs",
      //   ComplexDataDefinitionInterface must be used — in core this is only
      //   MapDataDefinition
      // - hence everything else must contain a single scalar value.
      is_subclass_of($definition['definition_class'], ListDataDefinitionInterface::class) => 'list',
      is_subclass_of($definition['definition_class'], ComplexDataDefinitionInterface::class) => 'complex',
      is_subclass_of($definition['definition_class'], DataDefinitionInterface::class) => 'scalar',
      // A default case only to provide precise guidance.
      default => 'no-data-definition',
    };
  }

  /**
   * Validates the absence of circular config schema type references.
   *
   * @param array $definition
   *   A config schema type definition.
   * @param string $plugin_id
   *   A config schema type ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the config schema type definition contains a circular type
   *   reference.
   */
  private function validateNoCircularTypeReference(array $definition, string $plugin_id): void {
    // This validation requires all config schema types to be known.
    assert($this->definitions !== NULL);
    $referenced_types = static::getImplicitlyReferencedTypes($definition, $this->definitions);
    // Resolve types with dynamic names to all possible types they may match.
    // @see \Drupal\Core\Config\Schema\TypeResolver::resolveExpression()
    // @see \Drupal\Core\Config\TypedConfigManager::getPossibleTypes()
    // If this type's name appears anywhere in its definition tree, it has a
    // circular reference.
    foreach ($referenced_types as $referenced_type) {
      $possible_types = $this->getPossibleTypes($referenced_type);
      if (in_array($plugin_id, $possible_types, TRUE)) {
        throw new InvalidPluginDefinitionException($plugin_id, sprintf('Config schema type "%s" has a circular type reference, where it uses the type "%s".', $plugin_id, $referenced_type));
      }
    }
  }

  /**
   * Gets explicitly referenced types for a config schema type definition.
   *
   * @param array $definition
   *   A config schema type definition.
   *
   * @return string[]
   *   All config schema types that are explicitly referenced by this config
   *   schema type definition. In other words: this collects all `type: …`
   *   strings in the given $definition.
   *   For example, for `type: config_object` this would return:
   *   - `mapping`
   *   - `_core_config_info`
   *   - `langcode`
   */
  private static function getExplicitlyReferencedTypes(array $definition): array {
    // Primitive types do not specify the "type" key. They cannot reference
    // other types, so return early.
    if (!isset($definition['type'])) {
      return [];
    }

    // TRICKY: checking if `$definition['type']` is `sequence` or `mapping` is
    // insufficient here, because it may be a subtype of a sequence or mapping.
    // For example:
    // - `type: views.filter_value.in_operator` is a specialized subtype of
    //   `type: sequence`
    // - `type: config_entity` is a specialized subtype of `type: config_object`
    //   which itself is a specialized subtype of `type: mapping`, and both
    //   specializations define additional keys in `mapping: …`.
    $used_types = [$definition['type']];

    // Add the type that all values in this sequence use.
    if (isset($definition['sequence'])) {
      $used_types = array_merge($used_types, static::getExplicitlyReferencedTypes($definition['sequence']));
    }
    // Add the types that the various keys in this mapping use.
    elseif (isset($definition['mapping'])) {
      foreach (array_keys($definition['mapping']) as $key) {
        $used_types = array_merge($used_types, static::getExplicitlyReferencedTypes($definition['mapping'][$key]));
      }
    }

    return array_unique($used_types);
  }

  /**
   * Gets implicitly referenced types for a config schema type definition.
   *
   * @param array $definition
   *   A config schema type definition.
   * @param array $all_definitions
   *   All config schema type definitions.
   *
   * @return string[]
   *   All config schema types that are implicitly referenced by this config
   *   schema type definition, by looking up the types used by the explicitly
   *   referenced types in their respective type definitions. In other words:
   *   this looks at the config schema type plugin definitions for all the
   *   explicit `type: …` strings (plugin IDs) found in $definition.
   *   For example, for `type: config_object` this would return:
   *   - `mapping` (explicit)
   *   - `_core_config_info` (explicit)
   *   - `langcode` (explicit)
   *   - `string` (implicit, via both `_core_config_info` and `langcode`)
   */
  private static function getImplicitlyReferencedTypes(array $definition, array $all_definitions): array {
    // The implicit ones include the explicit ones too.
    $explicit = static::getExplicitlyReferencedTypes($definition);
    // For each of the implicitly used types, figure out recursively which types
    // they use. If $explicit contains only primitive types, a single iteration
    // of the loop below will be sufficient.
    $result = $explicit;
    do {
      // For all types seen so far, find the next ones.
      $next_level = array_map(
        static::getExplicitlyReferencedTypes(...),
        // Use $new from the previous iteration, to not repeat the same work.
        array_intersect_key($all_definitions, array_flip($new ?? $explicit))
      );
      // Determine the newly discovered types.
      $new = array_merge(...array_values($next_level));
      // Remember them.
      $result = array_merge($result, $new);
    } while (!empty($new));
    return array_unique($result);
  }

  /**
   * Gets a schema definition with replacements for dynamic type names.
   *
   * @param string $base_plugin_id
   *   A plugin ID.
   * @param array $replacements
   *   An array of replacements for dynamic type names.
   * @param bool $exception_on_invalid
   *   (optional) This parameter is passed along to self::getDefinition().
   *   However, self::getDefinition() does not respect this parameter, so it is
   *   effectively useless in this context.
   *
   * @return array
   *   A schema definition array.
   */
  protected function getDefinitionWithReplacements($base_plugin_id, array $replacements, $exception_on_invalid = TRUE) {
    $definitions = $this->getDefinitions();
    $type = $this->determineType($base_plugin_id, $definitions);
    $definition = $definitions[$type];
    // Check whether this type is an extension of another one and compile it.
    if (isset($definition['type'])) {
      $merge = $this->getDefinition($definition['type'], $exception_on_invalid);
      // Preserve integer keys on merge, so sequence item types can override
      // parent settings as opposed to adding unused second, third, etc. items.
      $definition = NestedArray::mergeDeepArray([$merge, $definition], TRUE);

      // Replace dynamic portions of the definition type.
      if (!empty($replacements) && strpos($definition['type'], ']')) {
        $sub_type = $this->determineType(TypeResolver::resolveDynamicTypeName($definition['type'], $replacements), $definitions);
        $sub_definition = $definitions[$sub_type];
        if (isset($definitions[$sub_type]['type'])) {
          $sub_merge = $this->getDefinition($definitions[$sub_type]['type'], $exception_on_invalid);
          $sub_definition = NestedArray::mergeDeepArray([$sub_merge, $definitions[$sub_type]], TRUE);
        }
        // Merge the newly determined subtype definition with the original
        // definition.
        $definition = NestedArray::mergeDeepArray([$sub_definition, $definition], TRUE);
        $type = "$type||$sub_type";
      }
      // Unset type so we try the merge only once per type.
      unset($definition['type']);
      $this->definitions[$type] = $definition;
    }
    // Add type and default definition class.
    $definition += [
      'definition_class' => '\Drupal\Core\TypedData\DataDefinition',
      'type' => $type,
      'unwrap_for_canonical_representation' => TRUE,
    ];
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($base_plugin_id, $exception_on_invalid = TRUE) {
    return $this->getDefinitionWithReplacements($base_plugin_id, [], $exception_on_invalid);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    $this->schemaStorage->reset();
    parent::clearCachedDefinitions();
  }

  /**
   * Finds fallback configuration schema name.
   *
   * @param string $name
   *   Configuration name or key.
   *
   * @return null|string
   *   The resolved schema name for the given configuration name or key. Returns
   *   null if there is no schema name to fallback to. For example,
   *   breakpoint.breakpoint.module.toolbar.narrow will check for definitions in
   *   the following order:
   *     breakpoint.breakpoint.module.toolbar.*
   *     breakpoint.breakpoint.module.*.*
   *     breakpoint.breakpoint.module.*
   *     breakpoint.breakpoint.*.*.*
   *     breakpoint.breakpoint.*
   *     breakpoint.*.*.*.*
   *     breakpoint.*
   *   Colons are also used, for example,
   *   block.settings.system_menu_block:footer will check for definitions in the
   *   following order:
   *     block.settings.system_menu_block:*
   *     block.settings.*:*
   *     block.settings.*
   *     block.*.*:*
   *     block.*
   */
  public function findFallback(string $name): ?string {
    $fallback = $this->getFallbackName($name);
    assert($fallback === NULL || str_ends_with($fallback, '.*'));
    return $fallback;
  }

  /**
   * Gets fallback configuration schema name.
   *
   * @param string $name
   *   Configuration name or key.
   *
   * @return null|string
   *   The resolved schema name for the given configuration name or key.
   */
  protected function getFallbackName($name) {
    // Check for definition of $name with filesystem marker.
    $replaced = preg_replace('/([^\.:]+)([\.:\*]*)$/', '*\2', $name);
    if ($replaced != $name) {
      if (isset($this->definitions[$replaced])) {
        return $replaced;
      }
      else {
        // No definition for this level. Collapse multiple wildcards to a single
        // wildcard to see if there is a greedy match. For example,
        // breakpoint.breakpoint.*.* becomes
        // breakpoint.breakpoint.*
        $one_star = preg_replace('/\.([:\.\*]*)$/', '.*', $replaced);
        if ($one_star != $replaced && isset($this->definitions[$one_star])) {
          return $one_star;
        }
        // Check for next level. For example, if breakpoint.breakpoint.* has
        // been checked and no match found then check breakpoint.*.*
        return $this->getFallbackName($replaced);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function hasConfigSchema($name) {
    // The schema system falls back on the Undefined class for unknown types.
    $definition = $this->getDefinition($name);
    return is_array($definition) && ($definition['class'] != Undefined::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    $discovered_schema = array_keys($definitions);
    parent::alterDefinitions($definitions);
    $altered_schema = array_keys($definitions);
    if ($discovered_schema != $altered_schema) {
      $added_keys = implode(',', array_diff($altered_schema, $discovered_schema));
      $removed_keys = implode(',', array_diff($discovered_schema, $altered_schema));
      if (!empty($added_keys) && !empty($removed_keys)) {
        $message = "Invoking hook_config_schema_info_alter() has added ($added_keys) and removed ($removed_keys) schema definitions";
      }
      elseif (!empty($added_keys)) {
        $message = "Invoking hook_config_schema_info_alter() has added ($added_keys) schema definitions";
      }
      else {
        $message = "Invoking hook_config_schema_info_alter() has removed ($removed_keys) schema definitions";
      }
      throw new ConfigSchemaAlterException($message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createFromNameAndData($config_name, array $config_data) {
    $definition = $this->getDefinition($config_name);
    $data_definition = $this->buildDataDefinition($definition, $config_data);
    return $this->create($data_definition, $config_data, $config_name);
  }

  /**
   * Returns all possible types for the type with the given name.
   *
   * @param string $name
   *   Configuration name or key.
   *
   * @return string[]
   *   All possible types for a given type. For example,
   *   `core_date_format_pattern.[%parent.locked]` will return:
   *   - `core_date_format_pattern.0`
   *   - `core_date_format_pattern.1`
   *   If a fallback name is available, that will be returned too. In this
   *   example, that would be `core_date_format_pattern.*`.
   */
  public function getPossibleTypes(string $name): array {
    // If this name isn't dynamic, there's nothing to do.
    // @see \Drupal\Core\Config\Schema\TypeResolver::resolveDynamicTypeName()
    if (!str_contains($name, ']')) {
      return [$name];
    }

    // First, parse from e.g.
    // `module.something.foo_[%parent.locked]`
    // this:
    // `[%parent.locked]`
    // or from
    // `[%parent.%parent.%type].third_party.[%key]`
    // this:
    // `[%parent.%parent.%type]` and `[%key]`.
    // And collapse all these to just `[]`.
    // @see \Drupal\Core\Config\Schema\TypeResolver::resolveExpression()
    $matches = [];
    if (preg_match_all('/(\[[^\]]+\])/', $name, $matches) >= 1) {
      $name = str_replace($matches[0], '[]', $name);
    }
    // Then, replace all `[]` occurrences with `.*` and escape all periods for
    // use in a regex. So:
    // `module\.something\.foo_.*`
    // or
    // `.*\.third_party\..*`
    $regex = str_replace(['.', '[]'], ['\.', '.*'], $name);
    // Now find all possible types:
    // 1. `module.something.foo_foo`, `module.something.foo_bar`, etc.
    $possible_types = array_filter(
      array_keys($this->definitions),
      fn (string $type) => preg_match("/^$regex$/", $type) === 1
    );
    // 2. The fallback: `module.something.*` — if no concrete definition for it
    // exists.
    $fallback_type = $this->findFallback($name);
    if ($fallback_type && !in_array($fallback_type, $possible_types, TRUE)) {
      $possible_types[] = $fallback_type;
    }
    return $possible_types;
  }

}
