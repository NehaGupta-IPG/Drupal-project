<?php

namespace Drupal\Core\Htmx;

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\AttributeArray;
use Drupal\Core\Template\AttributeJson;
use Drupal\Core\Url;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Provides helpers to operate on forms.
 *
 * @ingroup form_api
 */
class HtmxHelper {

  const ATTRIBUTE_PREFIX = 'data-hx-';

  protected static function getWrapperKey(array $elements) {
    $wrapper_key = (($elements['#markup'] ?? FALSE) === '' && ($elements['#input'] ?? FALSE) === TRUE) ? '#wrapper_attributes' : '#attributes';
    if ($elements['#type'] === 'textarea' || $elements['#theme'] === 'item_list') {
      $wrapper_key = '#wrapper_attributes';
    }
    return $wrapper_key;
  }

  /**
   *
   */
  public static function processHtmx(array &$elements) {
    $conf = $elements['#htmx'];

    // Only add the library to attributes that trigger a request, or an event.
    if (!empty(array_intersect(['post', 'get', 'put', 'delete', 'patch', 'trigger'], array_keys($conf)))) {
      $elements['#attached']['library'][] = 'core/drupal.htmx';
    }

    $wrapper_key = self::getWrapperKey($elements);

    foreach ($conf as $key => $value) {
      $attribute_value = NULL;
      switch ($key) {
        case 'post':
        case 'get':
        case 'put':
        case 'delete':
        case 'patch':
          if ($value instanceof Url) {
            $attribute_value = $value->toString();
          }
          else {
            $attribute_value = $value === TRUE ? '' : $value;
          }
          break;

        case 'swap':
          $attribute_value = new AttributeArray('swap', [$value['style']->name, $value['modifiers']->__toString()]);
          break;

        case 'select-oob':
          $attribute_value = new AttributeArray('select-oob', $value);
          break;

        case 'swap-oob':
          if (empty($elements[$wrapper_key]['id'])) {
            $elements[$wrapper_key]['id'] = Html::getId($elements['#id'] . '-wrapper');
          }
          $attribute_value = $value === TRUE ? 'true' : $value;
          break;

        case 'on':
          foreach ($value as $event => $handler) {
            $key = self::ensureKebabCase($event);
            $elements[$wrapper_key][self::ATTRIBUTE_PREFIX . 'on' . $key] = $handler;
          }
          break;

        case 'vals':
          $attribute_value = new AttributeJson('vals', $value);
          break;

        default:
          if (!empty($value)) {
            $attribute_value = $value;
          }
          break;
      }
      if (!is_null($attribute_value)) {
        $elements[$wrapper_key][self::ATTRIBUTE_PREFIX . $key] = $attribute_value;
      }
    }
  }

  /**
   * Utility method to transform camelCase strings to kebab-case strings.
   *
   * Passes kebab-case strings through without any transformation.
   *
   * @param string $identifier
   *   The string to verify or transform.
   *
   * @return string
   *   The original or transformed string.
   */
  public static function ensureKebabCase(string $identifier): string {
    // Check for existing kebab case.
    $kebabParts = explode('-', $identifier);
    // If the number of lower case parts matches the number of parts, then
    // all the parts are lower case.
    $isKebab = count($kebabParts) === count(array_filter($kebabParts, function ($part) {
        return preg_match('#^[[:lower:]]+$#', $part) === 1;
      }));
    if ($isKebab) {
      return $identifier;
    }

    $converter = new CamelCaseToSnakeCaseNameConverter();
    $snakeCase = $converter->normalize($identifier);
    return preg_replace('#[_:]#', '-', $snakeCase);
  }
}
