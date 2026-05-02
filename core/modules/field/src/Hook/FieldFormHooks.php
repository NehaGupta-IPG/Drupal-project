<?php

namespace Drupal\field\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\ConfigImporterFieldPurger;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Form hook implementations for field.
 */
class FieldFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly MessengerInterface $messenger,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Adds a warning if field data will be permanently removed by the
   * configuration synchronization.
   *
   * @see \Drupal\field\ConfigImporterFieldPurger
   */
  #[Hook('form_config_admin_import_form_alter')]
  public function formConfigAdminImportFormAlter(&$form, FormStateInterface $form_state) : void {
    // Only display the message when core.extension is available in the source
    // storage and the form is not submitted.
    $user_input = $form_state->getUserInput();
    $storage_comparer = $form_state->get('storage_comparer');
    if ($storage_comparer?->getSourceStorage()->exists('core.extension') && empty($user_input)) {
      $field_storages = ConfigImporterFieldPurger::getFieldStoragesToPurge($storage_comparer->getSourceStorage()->read('core.extension'), $storage_comparer->getChangelist('delete'));
      if ($field_storages) {
        foreach ($field_storages as $field) {
          $field_labels[] = $field->label();
        }
        $this->messenger->addWarning($this->formatPlural(count($field_storages), 'This synchronization will delete data from the field %fields.', 'This synchronization will delete data from the fields: %fields.', ['%fields' => implode(', ', $field_labels)]));
      }
    }
  }

}
