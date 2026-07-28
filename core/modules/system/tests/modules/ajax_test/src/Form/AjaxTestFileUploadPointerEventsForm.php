<?php

declare(strict_types=1);

namespace Drupal\ajax_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test form for pointer-events with managed_file (file upload) widget.
 *
 * Reproduces the scenario where Drupal.file.triggerUploadButton
 * programmatically triggers the upload, and after AJAX replacement
 * the submit button must remain clickable.
 *
 * @internal
 */
class AjaxTestFileUploadPointerEventsForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ajax_test_file_upload_pointer_events_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['file_upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload a file'),
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'FileExtension' => [
          'extensions' => 'txt',
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('File form submitted successfully.'));
  }

}
