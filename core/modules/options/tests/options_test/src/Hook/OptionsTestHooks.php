<?php

declare(strict_types=1);

namespace Drupal\options_test\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\options_test\OptionsAllowedValues;

/**
 * Hook implementations for options_test.
 */
class OptionsTestHooks {

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_entity_test_entity_test_form_alter')]
  public function formEntityTestEntityTestFormAlter(&$form, FormStateInterface $form_state, $form_id) : void {
    if (\Drupal::state()->get('options_test.form_alter_enable', FALSE)) {
      $form['card_1']['widget']['#required_error'] = 'This is custom message for required field.';
    }
  }

  /**
   * Implements hook_options_list_alter().
   */
  #[Hook('options_list_alter')]
  public function optionsListAlter(array &$options, array $context): void {
    if ($context['fieldDefinition']->getName() === 'card_4' && $context['widget']->getPluginId() === 'options_select') {
      // Rename _none option.
      $options['_none'] = '- Select something -';
    }
    if ($context['fieldDefinition']->getName() === 'card_4' && $context['entity']->bundle() === 'entity_test') {
      // Remove 0 option.
      unset($options[0]);
    }
  }

  /**
   * Implements hook_entity_field_storage_info_alter().
   */
  #[Hook('entity_field_storage_info_alter')]
  public function entityFieldStorageInfoAlter(&$fields, EntityTypeInterface $entity_type): void {
    if (($entity_type->id() === 'node') && isset($fields['field_options_allowed_values'])) {
      $fields['field_options_allowed_values']
        ->setSetting('allowed_values_function', [OptionsAllowedValues::class, 'simpleValues']);
    }
  }

}
