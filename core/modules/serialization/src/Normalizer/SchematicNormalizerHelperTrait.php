<?php

declare(strict_types=1);

namespace Drupal\serialization\Normalizer;

use Drupal\Core\TypedData\TypedDataInterface;

/**
 * Methods for normalizing JSON schema.
 */
trait SchematicNormalizerHelperTrait {

  use JsonSchemaReflectionTrait;

  /**
   * Retrieve JSON Schema for the normalization.
   *
   * @param mixed $object
   *   Supported object or class/interface name being normalized.
   * @param array $context
   *   Context options. Well-defined keys include:
   *   - dialect: Used to specify a dialect for the desired schema being
   *     generated. The dialect meta-schema MUST extend JSON Schema draft
   *     2020-12 or later. Normalizers MAY choose to return a schema with
   *     keywords supported by a dialect it supports, but only when they
   *     are supported by the dialect specified in this key. For instance,
   *     normalizers may return a schema with a 'discriminator' as supported
   *     by OpenAPI if that dialect is passed, but return a more permissive but
   *     less specific schema when it is not.
   *
   * @return array
   *   JSON Schema for the normalization, conforming to version draft 2020-12.
   *
   * @see https://json-schema.org/specification#specification-documents
   */
  protected function getNormalizationSchema(mixed $object, array $context = []): array {
    return $this->getJsonSchemaForMethod($this, 'normalize', ['$comment' => 'No schema available.']);
  }

  /**
   * Adds JSON Schema annotations derived from the data definition.
   *
   * JSON Schema annotation keywords such as "default" are derived from the
   * data definition of the object being normalized. The default value is
   * descriptive metadata only: it documents the value an instance is expected
   * to take when none is supplied, and never constrains which values validate
   * against the schema. Defaults which cannot be represented in JSON, such as
   * objects which are neither scalar, array nor \Stringable, are skipped, at
   * any depth of an array default.
   *
   * A data definition declares its default value in the format accepted by
   * \Drupal\Core\TypedData\TypedDataInterface::setValue(), while the schema
   * describes the normalization of that value. Only normalizers which leave
   * that format unchanged may annotate the default value, otherwise the
   * annotation would contradict the type the schema declares.
   *
   * @param mixed $object
   *   Supported object or class/interface name being normalized.
   * @param array $schema
   *   JSON Schema for the normalization, to be annotated.
   *
   * @return array
   *   JSON Schema for the normalization, with annotation keywords added for
   *   each annotation the data definition declares.
   *
   * @see https://json-schema.org/understanding-json-schema/reference/annotations
   */
  protected function addDataDefinitionAnnotations(mixed $object, array $schema): array {
    if (!$object instanceof TypedDataInterface) {
      return $schema;
    }
    $default = $object->getDataDefinition()->getDefault();
    // A declared NULL default value is indistinguishable from no declared
    // default value, so there is nothing to annotate.
    if ($default === NULL) {
      return $schema;
    }
    try {
      $schema['default'] = $this->castDefaultToJsonValue($default);
    }
    catch (\UnexpectedValueException) {
      // Skip defaults which cannot be represented in JSON.
    }
    return $schema;
  }

  /**
   * Casts a declared default value to a value which can be encoded as JSON.
   *
   * @param mixed $value
   *   A declared default value, or a value nested within one.
   *
   * @return array|string|int|float|bool|null
   *   The value, with \Stringable objects cast to strings.
   *
   * @throws \UnexpectedValueException
   *   Thrown when the value cannot be encoded as JSON.
   */
  protected function castDefaultToJsonValue(mixed $value): array|string|int|float|bool|NULL {
    if (is_array($value)) {
      return array_map($this->castDefaultToJsonValue(...), $value);
    }
    if ($value instanceof \Stringable) {
      $value = (string) $value;
    }
    if ($value !== NULL && !is_scalar($value)) {
      throw new \UnexpectedValueException(sprintf('Default values of type %s cannot be encoded as JSON.', get_debug_type($value)));
    }
    if (is_float($value) && !is_finite($value)) {
      throw new \UnexpectedValueException('Non-finite float default values cannot be encoded as JSON.');
    }
    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
      throw new \UnexpectedValueException('Default values which are not valid UTF-8 cannot be encoded as JSON.');
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkFormat($format = NULL) {
    if ($format === 'json_schema') {
      return TRUE;
    }
    return parent::checkFormat($format);
  }

}
