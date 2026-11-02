<?php

declare(strict_types=1);

namespace Drupal\file_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form for testing private unauthorized file uploads.
 */
class FileTestPrivateUnauthorizedForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'file_test_private_unauthorized_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $default_fids = NULL): array {
    $form['test_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Private File'),
      '#upload_location' => 'private://unauthorized',
      '#multiple' => TRUE,
    ];

    if ($default_fids) {
      $form['test_file']['#default_value'] = explode(',', $default_fids);
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $fids = $form_state->getValue('test_file');
    $fids = is_array($fids) ? $fids : ($fids ? [$fids] : []);
    $this->messenger()->addStatus($this->t('The file ids are @fids.', ['@fids' => implode(',', $fids)]));
  }

}
