<?php

namespace Drupal\Core\Form;

use Drupal\Component\Utility\NestedArray;

/**
 * Provides methods to manage form state values.
 *
 * @see \Drupal\Core\Form\FormStateInterface
 *
 * @ingroup form_api
 */
trait FormStateValuesTrait {

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::getValues()
   */
  abstract public function &getValues();

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::getValue()
   */
  public function &getValue($key, $default = NULL) {
    $exists = NULL;
    $value = &NestedArray::getValue($this->getValues(), (array) $key, $exists);
    if (!$exists) {
      $value = $default;
    }

    if (method_exists($this, 'get') &&
        ($deprecated_keys = $this->get('deprecated_keys') ?? []) &&
        (is_array($deprecated_keys))) {
      $deprecated = FALSE;
      // Deprecated keys are stored as arrays, so convert single string key to
      // array for comparison.
      $key = is_array($key) ? $key : [$key];
      foreach ($deprecated_keys as $deprecated_key) {
        // This will check whether the array values of $key start with the same
        // values as the deprecated key. For example, if the key is
        // ['a', 'b', 'c'], and the deprecated key is ['a', 'b'], then
        // ['a', 'b', 'c'] is deprecated as well because its parent in the array
        // is deprecated.
        if (array_intersect_assoc($deprecated_key['key'], $key) === $deprecated_key['key']) {
          $deprecated = TRUE;
          break;
        }
      }
      if ($deprecated) {
        // @phpcs:ignore
        @trigger_error(sprintf('The form element with key [%s] in class %s is deprecated in %s and is removed from %s. For any subclass that extends the class and expects a value for this key, the element should be added into that subclass\'s buildForm(). See %s', implode(', ', $key), $deprecated_key['class'], $deprecated_key['deprecation_version'], $deprecated_key['removed_version'], $deprecated_key['change_record']), E_USER_DEPRECATED);
      }
    }
    return $value;
  }

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::setValues()
   */
  public function setValues(array $values) {
    $existing_values = &$this->getValues();
    $existing_values = $values;
    return $this;
  }

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::setValue()
   */
  public function setValue($key, $value) {
    NestedArray::setValue($this->getValues(), (array) $key, $value, TRUE);
    return $this;
  }

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::unsetValue()
   */
  public function unsetValue($key) {
    NestedArray::unsetValue($this->getValues(), (array) $key);
    return $this;
  }

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::hasValue()
   */
  public function hasValue($key) {
    $exists = NULL;
    $value = NestedArray::getValue($this->getValues(), (array) $key, $exists);
    return $exists && isset($value);
  }

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::isValueEmpty()
   */
  public function isValueEmpty($key) {
    $exists = NULL;
    $value = NestedArray::getValue($this->getValues(), (array) $key, $exists);
    return !$exists || empty($value);
  }

  /**
   * Implements \Drupal\Core\Form\FormStateInterface::setValueForElement()
   */
  public function setValueForElement(array $element, $value) {
    return $this->setValue($element['#parents'], $value);
  }

}
