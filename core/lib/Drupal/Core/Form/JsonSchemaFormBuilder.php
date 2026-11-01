<?php

namespace Drupal\Core\Form;

use Drupal\Core\Render\Element;

/**
 * Builds a JSON Schema representation of a form.
 */
class JsonSchemaFormBuilder {

  /**
   * Builds the JSON Schema for a given form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   *
   * @return array
   *   The array of data expected by JSON Schema.
   */
  public function build(array $form) {
    return [
      'schema' => $this->buildSchema($form),
      'uiSchema' => $this->buildUiSchema($form),
      'formData' => $this->buildFormData($form),
    ];
  }

  /**
   * Recursively builds the schema.
   *
   * @param array $parent
   *   The parent element.
   * @param array $result
   *   The array passed through for gathering the recursive data.
   *
   * @return array
   *   The schema.
   */
  protected function buildSchema(array $parent, array $result = []) {
    $keys = Element::children($parent);
    foreach ($keys as $key) {
      $recurse = TRUE;

      $element = $parent[$key];
      $row = [];
      if (isset($element['#type'])) {
        $type = $this->deriveSchemaType($element);
        if ($type === FALSE) {
          continue;
        }
        if ($type) {
          $row['type'] = $type;
        }

        if (in_array($element['#type'], ['checkboxes', 'radios', 'select'], TRUE)) {
          // If no options are defined, skip this element.
          if (empty($element['#options'])) {
            continue;
          }

          $row['enum'] = array_keys($element['#options']);
          $values = array_values($element['#options']);
          if ($values !== $row['enum']) {
            $row['enumNames'] = $values;
          }

          // @todo Does this apply to radios too?
          if ($element['#type'] === 'checkboxes') {
            $row = ['items' => $row];
            $row['uniqueItems'] = TRUE;
            $row['type'] = 'array';
          }

          if (in_array($element['#type'], ['checkboxes', 'radios'], TRUE)) {
            $recurse = FALSE;
          }
        }
      }

      if ((!isset($element['#type']) || $element['#type'] === 'markup') && isset($element['#markup'])) {
        $row['type'] = 'string';
      }

      if ($recurse) {
        $row = $this->buildSchema($element, $row);
      }

      if ($row) {
        $result += [
          'type' => 'object',
          // Explicitly set the title to an empty string if none exists.
          'title' => '',
        ];

        $result['properties'][$key] = $row;

        if (!empty($element['#required'])) {
          $result['required'][] = $key;
        }
      }
    }

    if (($result || $keys) && isset($parent['#title'])) {
      $result['title'] = (string) $parent['#title'];
    }

    return $result;
  }

  /**
   * Derives the schema type for a given element.
   *
   * @param array $element
   *   The form element.
   *
   * @return string
   *   The schema type.
   */
  protected function deriveSchemaType(array $element) {
    $type = $element['#type'];
    switch ($type) {
      case 'textarea':
      case 'textfield':
      case 'token':
      case 'item':
      case 'date':
      case 'datelist':
      case 'datetime':
      case 'color':
      case 'email':
      case 'path':
      case 'url':
      case 'search':
      case 'tel':
      case 'machine_name':
      case 'markup':
      case 'password':
      case 'link':
      case 'submit':
        return 'string';

      case 'checkbox':
        return 'boolean';

      case 'number':
      case 'range':
        return 'number';

      case 'radios':
      case 'checkboxes':
      case 'select':
        return gettype(key($element['#options']));

      // Do not print these to the page.
      case 'value':
        return FALSE;

      // @todo Should probably print some wrapper stuff.
      case 'container':
      case 'details':
      case 'fieldgroup':
      case 'fieldset':
      case 'vertical_tabs':
      case 'actions':
        return NULL;

      // @todo Should be dealt with.
      case 'dropbutton':
      case 'operations':
      case 'html_tag':
      case 'inline_template':
      case 'component':
      case 'icon':
      case 'label':
      case 'more_link':
      case 'break_lock_link':
      case 'system_compact_link':
      case 'page_title':
      case 'status_messages':
      case 'managed_file':
      case 'file':
      case 'entity_autocomplete':
      case 'language_select':
        return NULL;

      // @todo What do we do with this?
      case 'pager':
      case 'ajax':
        return NULL;

      // @todo WTF?
      case 'status_report':
      case 'status_report_page':
        return NULL;

      // Pass through to the value checking below.
      case 'hidden':
        break;

      default:
        throw new \Exception(sprintf('Unknown type: "%s"', $type));
    }

    $value = isset($element['#input']) ? gettype($element['#value']) : NULL;
    switch ($value) {
      case 'boolean':
      case 'string':
      case 'integer':
        return $value;

      // @todo.
      case 'array':
        return NULL;

      default:
        throw new \Exception(sprintf('Unknown value "%s" for type: "%s"', $type, $value));
    }
  }

  /**
   * Recursively builds the UI schema.
   *
   * @param array $parent
   *   The parent element.
   * @param array $result
   *   The array passed through for gathering the recursive data.
   *
   * @return array
   *   The UI schema.
   */
  protected function buildUiSchema(array $parent, array $result = []) {
    foreach (Element::children($parent) as $key) {
      $element = $parent[$key];

      // @todo Move this to FormBuilder.
      if (!isset($element['#type']) && (isset($element['#markup']) || isset($element['#plain_text']))) {
        $element['#type'] = 'markup';
      }

      $row = $this->handleWidgetType($element);

      if (!isset($element['#type']) || !in_array($element['#type'], ['checkboxes', 'radios'], TRUE)) {
        $row = $this->buildUiSchema($element, $row);
      }

      if ($row) {
        $result[$key] = $row;
      }
    }
    return $result;
  }

  /**
   * Allows individual widget types to control the UI schema.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   UI schema information specific to the widget type.
   */
  protected function handleWidgetType(array $element) {
    if (!isset($element['#type'])) {
      return [];
    }

    $type = $element['#type'];
    switch ($type) {
      case 'hidden':
      case 'value':
      case 'token':
        $row['ui:widget'] = 'hidden';
        break;

      case 'link':
        $row['ui:widget'] = 'link';
        $row['ui:options']['label'] = FALSE;
        if (!empty($element['#url'])) {
          $row['ui:options']['title'] = (string) $element['#title'];
          $row['ui:options']['url'] = $element['#url']->toString();
        }
        break;

      case 'checkboxes':
        $row['ui:widget'] = 'checkboxes';
        break;

      case 'radios':
        $row['ui:widget'] = 'radio';
        break;

      case 'radio':
      case 'checkbox':
        $row['ui:widget'] = $type;
        $row['ui:options']['label'] = FALSE;
        break;

      case 'markup':
        $row['ui:options']['label'] = FALSE;
      case 'item':
        $row['ui:widget'] = $type;
        if (isset($element['#plain_text']) && $element['#plain_text'] !== '') {
          $row['ui:options']['markup'] = (string) $element['#plain_text'];
        }
        elseif (isset($element['#markup']) && $element['#markup'] !== '') {
          $row['ui:options']['markup'] = (string) $element['#markup'];
        }
        break;

      case 'submit':
        $row['ui:widget'] = 'submit';
        $row['ui:options']['label'] = FALSE;
        if (isset($element['#name'])) {
          $row['ui:options']['name'] = $element['#name'];
        }
        break;

      // No custom widget needed.
      default:
        $row = [];
    }

    if (isset($element['#description'])) {
      $row['ui:description'] = $element['#description'];
    }

    return $row;
  }

  /**
   * Recursively builds the form data.
   *
   * @param array $parent
   *   The parent element.
   * @param array $result
   *   The array passed through for gathering the recursive data.
   *
   * @return array
   *   The form data.
   */
  protected function buildFormData(array $parent, $result = []) {
    foreach (Element::children($parent) as $key) {
      $element = $parent[$key];

      $value = isset($element['#value']) ? $element['#value'] : [];

      if (!isset($element['#type']) || !in_array($element['#type'], ['checkboxes', 'radios'], TRUE)) {
        $value = $this->buildFormData($element, $value);
      }

      // Retain 0 and FALSE, but ignore other empty values.
      if (!in_array($value, [NULL, [], ''], TRUE)) {
        $result[$key] = $value;
      }
    }

    return $result;
  }

}
