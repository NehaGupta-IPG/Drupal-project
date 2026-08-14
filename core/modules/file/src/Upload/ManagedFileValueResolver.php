<?php

declare(strict_types=1);

namespace Drupal\file\Upload;

use Drupal\Component\Utility\Crypt;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\PrivateKey;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;

/**
 * Resolves the #value of a managed_file element, saving any new upload.
 */
class ManagedFileValueResolver {

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly PrivateKey $privateKey,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Determines how user input is mapped to a managed_file element's #value.
   *
   * @param array $element
   *   The FAPI element whose values are being saved.
   * @param array|false|null $input
   *   The incoming input to populate the form element. If this is FALSE,
   *   the element's default value should be returned.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The value to assign to the element.
   */
  public function resolveValue(array &$element, mixed $input, FormStateInterface $form_state): array {
    $fileStorage = $this->entityTypeManager->getStorage('file');

    // Find the current value of this field.
    $fids = !empty($input['fids']) ? explode(' ', $input['fids']) : [];
    foreach ($fids as $key => $fid) {
      $fids[$key] = (int) $fid;
    }
    $force_default = FALSE;

    // Process any input and save new uploads.
    if ($input !== FALSE) {
      $input['fids'] = $fids;
      $return = $input;

      // Uploads take priority over all other values.
      if ($files = file_managed_file_save_upload($element, $form_state)) {
        if ($element['#multiple']) {
          $fids = array_merge($fids, array_keys($files));
          $denied_fids = [];
          foreach ($fileStorage->loadMultiple($fids) as $fid => $file) {
            if (!$file->access('download')) {
              $denied_fids[] = $fid;
            }
          }
          // Remove denied file ids from the original file ID array.
          $fids = array_diff($fids, $denied_fids);
        }
        else {
          $fids = array_keys($files);
        }
      }
      else {
        // Check for #filefield_value_callback values.
        // Because FAPI does not allow multiple #value_callback values like it
        // does for #element_validate and #process, this fills the missing
        // functionality to allow File fields to be extended through FAPI.
        if (isset($element['#file_value_callbacks'])) {
          foreach ($element['#file_value_callbacks'] as $callback) {
            $callback($element, $input, $form_state);
          }
        }

        // Load files if the FIDs have changed to confirm they exist.
        if (!empty($input['fids'])) {
          $fids = [];
          foreach ($input['fids'] as $fid) {
            if ($file = $fileStorage->load($fid)) {
              $fids[] = $file->id();
              if (!$file->access('download')) {
                $force_default = TRUE;
                break;
              }
              // Temporary files that belong to other users should never be
              // allowed.
              if ($file->isTemporary()) {
                if ($file->getOwnerId() != $this->currentUser->id()) {
                  $force_default = TRUE;
                  break;
                }
                // Since file ownership can't be determined for anonymous
                // users, they are not allowed to reuse temporary files at
                // all. But they do need to be able to reuse their own files
                // from earlier submissions of the same form, so to allow
                // that, check for the token added by
                // \Drupal\file\Element\ManagedFile::processManagedFile().
                elseif ($this->currentUser->isAnonymous()) {
                  $parents = array_merge($element['#parents'], ['file_' . $file->id(), 'fid_token']);
                  $token = NestedArray::getValue($form_state->getUserInput(), $parents);
                  $file_hmac = Crypt::hmacBase64('file-' . $file->id(), $this->privateKey->get() . Settings::getHashSalt());
                  if ($token === NULL || !hash_equals($file_hmac, $token)) {
                    $force_default = TRUE;
                    break;
                  }
                }
              }
            }
          }
          if ($force_default) {
            $fids = [];
          }
        }
      }
    }

    // If there is no input or if the default value was requested above, use
    // the default value.
    if ($input === FALSE || $force_default) {
      if ($element['#extended']) {
        $default_fids = $element['#default_value']['fids'] ?? [];
        $return = $element['#default_value'] ?? ['fids' => []];
      }
      else {
        $default_fids = $element['#default_value'] ?? [];
        $return = ['fids' => []];
      }

      // Confirm that the file exists when used as a default value.
      if (!empty($default_fids)) {
        $fids = [];
        foreach ($fileStorage->loadMultiple($default_fids) as $file) {
          if ($file->access('download')) {
            $fids[] = $file->id();
          }
        }
      }
    }

    $return['fids'] = $fids;
    return $return;
  }

}
