<?php

declare(strict_types=1);

namespace Drupal\views_test_entity_reference\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Test EntityReferenceSelection with configuration form validation.
 *
 * @todo https://www.drupal.org/project/drupal/issues/3543036
 *   - The entity_reference_test module is a more appropriate location for this
 *     plugin, but it can't be moved until this issue is fixed.
 */
#[EntityReferenceSelection(
  id: "entity_reference_test_validation_selection",
  label: new TranslatableMarkup("Test Validation Selection Handler"),
  group: "entity_reference_test_validation_selection",
  weight: 0
)]
class TestValidationSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   *
   * Add test element that will be validated in validateConfigurationForm().
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['test_required'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test required setting'),
      '#default_value' => $this->configuration['test_required'] ?? '',
      // Intentionally not setting '#required' to exercise validateConfigurationForm().
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);
    $value = (string) ($form_state->getValue(['settings', 'handler_settings', 'test_required']) ?? '');
    $test_required_element = $form['test_required'] ?? $form['handler']['handler_settings']['test_required'] ?? NULL;
    // Ensure the `test_required` value has been provided and
    // `FormStateInterface::getValue` is working as expected.
    if ($value === '') {
      if (is_array($test_required_element)) {
        $form_state->setError($test_required_element, $this->t('This setting is required.'));
      }
      return;
    }
    if ($value === 'set me') {
      // Update the `test_required` value to ensure
      // `FormStateInterface::setValue` is working as expected.
      $form_state->setValue(['settings', 'handler_settings', 'test_required'], 'you have been set');
      \Drupal::messenger()->addMessage($this->t('The test_required value was updated.'));
    }
  }

}
