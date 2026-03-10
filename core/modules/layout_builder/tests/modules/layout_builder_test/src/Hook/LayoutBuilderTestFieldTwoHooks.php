<?php

declare(strict_types=1);

namespace Drupal\layout_builder_test\Hook;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Additional Field hook implementations for layout_builder_test.
 */
class LayoutBuilderTestFieldTwoHooks {

  const string MESSAGE = 'Second field formatter third party settings form element.';

  /**
   * Implements hook_field_formatter_third_party_settings_form().
   */
  #[Hook('field_formatter_third_party_settings_form')]
  public function fieldFormatterThirdPartySettingsForm(FormatterInterface $plugin, FieldDefinitionInterface $field_definition, $view_mode, $form, FormStateInterface $form_state): array {
    $element['field_formatter_third_party_settings_form_second'] = [
      '#type' => 'markup',
      '#markup' => self::MESSAGE,
    ];
    return $element;
  }

}
