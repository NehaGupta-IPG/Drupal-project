<?php

/**
 * @file
 * Post update functions for Contact.
 */

use Drupal\contact\ContactFormInterface;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Implements hook_removed_post_updates().
 */
function contact_removed_post_updates(): array {
  return [
    'contact_post_update_add_message_redirect_field_to_contact_form' => '9.0.0',
    'contact_post_update_set_empty_default_form_to_null' => '11.0.0',
  ];
}

/**
 * Updates Contact form's message, redirect and reply from '' to NULL.
 */
function contact_post_update_set_empty_values_to_null(&$sandbox = []): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'contact_form', function (ContactFormInterface $contact_form): bool {
      $updated = FALSE;

      foreach (['redirect', 'message', 'reply'] as $field) {
        $value = trim($contact_form->get($field));
        if (trim($value) === '') {
          $contact_form->set($field, NULL);
          $updated = TRUE;
        }
      }

      return $updated;
    });
}
