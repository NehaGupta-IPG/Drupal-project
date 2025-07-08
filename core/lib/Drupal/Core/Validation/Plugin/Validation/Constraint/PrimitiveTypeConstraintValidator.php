<?php

namespace Drupal\Core\Validation\Plugin\Validation\Constraint;

use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Type\BinaryInterface;
use Drupal\Core\TypedData\Type\BooleanInterface;
use Drupal\Core\TypedData\Type\DateTimeInterface;
use Drupal\Core\TypedData\Type\DecimalInterface;
use Drupal\Core\TypedData\Type\DurationInterface;
use Drupal\Core\TypedData\Type\FloatInterface;
use Drupal\Core\TypedData\Type\IntegerInterface;
use Drupal\Core\TypedData\Type\StringInterface;
use Drupal\Core\TypedData\Type\UriInterface;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Drupal\Component\Render\MarkupInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PrimitiveType constraint.
 */
class PrimitiveTypeConstraintValidator extends ConstraintValidator {

  use TypedDataAwareValidatorTrait;

  /**
   * Checks if the given value is a valid Primitive Typed Data instance.
   *
   * @param \Drupal\Core\TypedData\PrimitiveInterface $typed_data
   *   A Primitive Typed Data instance.
   *
   * @return bool
   *   TRUE if it is valid, FALSE otherwise.
   *
   * @internal
   * @see \Drupal\Core\Config\TypedConfigManager::getCanonicalRepresentation()
   */
  public static function isValidPrimitiveTypedData(PrimitiveInterface $typed_data): bool {
    $value = $typed_data->getValue();

    $valid = TRUE;
    if ($typed_data instanceof BinaryInterface && !is_resource($value)) {
      $valid = FALSE;
    }
    if ($typed_data instanceof BooleanInterface && !(is_bool($value) || $value === 0 || $value === '0' || $value === 1 || $value == '1')) {
      $valid = FALSE;
    }
    // Special handling for booleans: any string is allowed, from "TRUE", to "",
    // to arbitrary strings such as "test".
    if ($typed_data instanceof BooleanInterface && is_string($value)) {
      $valid = TRUE;
    }
    if ($typed_data instanceof FloatInterface && filter_var($value, FILTER_VALIDATE_FLOAT) === FALSE) {
      $valid = FALSE;
    }
    if ($typed_data instanceof IntegerInterface && filter_var($value, FILTER_VALIDATE_INT) === FALSE) {
      $valid = FALSE;
    }
    // Special handling for integers and floats since the configuration
    // system is primarily concerned with saving values from the Form API
    // we have to special case the meaning of an empty string for numeric
    // types. In PHP this would be casted to a 0 but for the purposes of
    // configuration we need to treat this as a NULL.
    // @see \Drupal\Core\TypedData\Plugin\DataType\FloatData::getCastedValue()
    // @see \Drupal\Core\TypedData\Plugin\DataType\IntegerData::getCastedValue()
    if (($typed_data instanceof IntegerInterface || $typed_data instanceof FloatInterface) && $value === '') {
      $valid = TRUE;
    }
    // Special handling for integers and floats: almost any string is allowed,
    // for example `"55%"` is interpreted as `55`.
    if (($typed_data instanceof IntegerInterface || $typed_data instanceof FloatInterface) && is_string($value) && str_contains($value, (string) (int) $value)) {
      $valid = TRUE;
    }
    // Special handling for integers: booleans are allowed (cast to 0/1).
    if ($typed_data instanceof IntegerInterface && is_bool($value)) {
      $valid = TRUE;
    }
    if ($typed_data instanceof DecimalInterface && !preg_match('/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/i', $value)) {
      $valid = FALSE;
    }
    if ($typed_data instanceof StringInterface && !is_scalar($value) && !($value instanceof MarkupInterface)) {
      $valid = FALSE;
    }
    // Ensure that URIs comply with http://tools.ietf.org/html/rfc3986, which
    // requires:
    // - That it is well formed (parse_url() returns FALSE if not).
    // - That it contains a scheme (parse_url(, PHP_URL_SCHEME) returns NULL if
    //   not).
    if ($typed_data instanceof UriInterface && in_array(parse_url($value, PHP_URL_SCHEME), [NULL, FALSE], TRUE)) {
      $valid = FALSE;
    }
    // @todo Move those to separate constraint validators.
    try {
      if ($typed_data instanceof DateTimeInterface && $typed_data->getDateTime() && $typed_data->getDateTime()->hasErrors()) {
        $valid = FALSE;
      }
      if ($typed_data instanceof DurationInterface && $typed_data->getDuration() && !($typed_data->getDuration() instanceof \DateInterval)) {
        $valid = FALSE;
      }
    }
    catch (\Exception) {
      // Invalid durations or dates might throw exceptions.
      $valid = FALSE;
    }

    return $valid;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!isset($value)) {
      return;
    }

    if (!$this->isValidPrimitiveTypedData($this->getTypedData())) {
      // @todo Provide a good violation message for each problem.
      $this->context->addViolation($constraint->message, [
        '%value' => is_object($value) ? get_class($value) : (is_array($value) ? 'Array' : (string) $value),
      ]);
    }
  }

}
