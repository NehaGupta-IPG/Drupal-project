<?php

namespace Drupal\Core\Form;

use Drupal\Core\Config\TypedConfigManagerInterface;

trait ConfigSchemaFormTrait {

  protected function buildFormForConfig(array $form, string $config_name): array {
    $config_definition = $this->typedConfigManager()->getDefinition($config_name);
    // dump($config_definition);

    $set_config_targets = match ($config_definition['original_type']) {
      'config_object' => TRUE,
      // Config entities do not yet support config targets on form elements.
      'config_entity' => FALSE,
      // We don't handle any other type of config as our top-level config
      // definition.
      default => throw new \InvalidArgumentException(sprintf("Invalid config name '%s'.", $config_name)),
    };

    $form = $this->buildFormElementForConfig($form, $config_name, NULL, $config_definition['mapping'], $set_config_targets);
    // dsm($form);
    return $form;
  }

  protected function buildFormElementForConfig(array $element, string $config_name, ?string $property_address, array $schema_definition, bool $set_config_targets): array {
    foreach ($schema_definition as $child_property_name => $child_property_schema) {
      // Form the address of this property.
      $child_property_address = ($property_address ? $property_address . '.' : '') . $child_property_name;

      // Skip special properties.
      if (str_starts_with('_', $child_property_name)) {
        continue;
      }

      $child_element = [
        '#title' => $child_property_schema['label'] ?? $child_property_name,
      ];

      // dsm($child_property_schema);

      if ($child_property_schema['type'] == 'mapping') {
        // Compound element for a mapping item: recurse into this method to
        // build multiple form elements.
        $child_element += [
          '#type' => 'details',
          '#open' => TRUE,
        ];

        $child_element = $this->buildFormElementForConfig($child_element, $config_name, $child_property_address, $child_property_schema['mapping'], $set_config_targets);
      }
      else {
        // Simple config item.
        if ($set_config_targets) {
          $child_element['#config_target'] = $config_name . ':' . $child_property_address;
        }

        if (isset($child_property_schema['constraints']['Choice'])) {
          // Item with options. It doesn't matter what the data type is.
          $child_element['#type'] = 'radios';
          $child_element['#options'] = array_combine(
            $child_property_schema['constraints']['Choice']['choices'],
            $child_property_schema['constraints']['Choice']['choices'],
          );
        }
        elseif ($child_property_schema['type'] == 'string') {
          $child_element['#type'] = 'textfield';
        }
        elseif ($child_property_schema['type'] == 'boolean') {
          $child_element['#type'] = 'checkbox';
        }
        elseif ($child_property_schema['type'] == 'text') {
          $child_element['#type'] = 'textarea';
        }
        elseif ($child_property_schema['type'] == 'langcode') {
          $child_element['#type'] = 'language_select';
        }
      }

      $element[$child_property_name] = $child_element;
    }

    return $element;
  }

  /**
   * Returns the typed config manager service.
   *
   * @return \Drupal\Core\Config\TypedConfigManagerInterface
   *   The typed config manager service.
   */
  protected function typedConfigManager(): TypedConfigManagerInterface {
    if (isset($this->typedConfigManager) && $this->typedConfigManager instanceof TypedConfigManagerInterface) {
      return $this->typedConfigManager;
    }
    return \Drupal::service('config.typed');
  }

}