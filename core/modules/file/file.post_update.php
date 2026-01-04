<?php

/**
 * @file
 * Post update functions for File.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\ViewEntityInterface;

/**
 * Implements hook_removed_post_updates().
 */
function file_removed_post_updates(): array {
  return [
    'file_post_update_add_txt_if_allows_insecure_extensions' => '10.0.0',
    'file_post_update_add_permissions_to_roles' => '11.0.0',
    'file_post_update_add_default_filename_sanitization_configuration' => '11.0.0',
  ];
}

/**
 * Adds a value for the 'playsinline' setting of the 'file_video' formatter.
 *
 * @deprecated in drupal:12.3.0 and is removed from drupal:13.0.0. This was
 *   rolled back due to a bug in the implementation and is now a no-op function
 *   and is kept only to prevent errors when updating.
 *
 * @see https://www.drupal.org/project/drupal/issues/3533291
 */
function file_post_update_add_playsinline(array &$sandbox = []): ?TranslatableMarkup {
  // No-op.
  return NULL;
}

/**
 * Update the files view to use file_usage field plugin.
 */
function file_post_update_files_view_file_usage(array &$sandbox = []): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {
    if ($view->id() !== 'files') {
      return FALSE;
    }

    $displays = $view->get('display');

    // Remove the 'count' field from file_usage table.
    unset($displays['default']['display_options']['fields']['count']);

    // Remove file_usage relationship.
    unset($displays['default']['display_options']['relationships']['fid']);
    if (empty($displays['default']['display_options']['relationships'])) {
      $displays['default']['display_options']['relationships'] = [];
    }
    unset($displays['page_1']['display_options']['relationships']);

    // Disable aggregation.
    $displays['default']['display_options']['group_by'] = FALSE;

    // Add the new file_usage field.
    $file_usage_field = [
      'id' => 'file_usage',
      'table' => 'file_managed',
      'field' => 'file_usage',
      'relationship' => 'none',
      'group_type' => 'group',
      'admin_label' => '',
      'plugin_id' => 'file_usage',
      'label' => 'Used in',
      'exclude' => FALSE,
      'alter' => [
        'alter_text' => FALSE,
        'text' => '',
        'make_link' => TRUE,
        'path' => 'admin/content/files/usage/{{ fid }}',
        'absolute' => FALSE,
        'external' => FALSE,
        'replace_spaces' => FALSE,
        'path_case' => 'none',
        'trim_whitespace' => FALSE,
        'alt' => '',
        'rel' => '',
        'link_class' => '',
        'prefix' => '',
        'suffix' => '',
        'target' => '',
        'nl2br' => FALSE,
        'max_length' => 0,
        'word_boundary' => TRUE,
        'ellipsis' => TRUE,
        'more_link' => FALSE,
        'more_link_text' => '',
        'more_link_path' => '',
        'strip_tags' => FALSE,
        'trim' => FALSE,
        'preserve_tags' => '',
        'html' => FALSE,
      ],
      'element_type' => '',
      'element_class' => '',
      'element_label_type' => '',
      'element_label_class' => '',
      'element_label_colon' => TRUE,
      'element_wrapper_type' => '',
      'element_wrapper_class' => '',
      'element_default_classes' => TRUE,
      'empty' => '0',
      'hide_empty' => FALSE,
      'empty_zero' => FALSE,
      'hide_alter_empty' => TRUE,
    ];

    // Insert file_usage field before operations.
    $fields = $displays['default']['display_options']['fields'];
    $ordered_fields = [];
    foreach ($fields as $field_name => $field_config) {
      if ($field_name === 'operations') {
        $ordered_fields['file_usage'] = $file_usage_field;
      }
      $ordered_fields[$field_name] = $field_config;
    }
    if (!isset($ordered_fields['file_usage'])) {
      $ordered_fields['file_usage'] = $file_usage_field;
    }
    $displays['default']['display_options']['fields'] = $ordered_fields;

    // Update style options.
    unset($displays['default']['display_options']['style']['options']['columns']['count']);
    $displays['default']['display_options']['style']['options']['columns']['file_usage'] = 'file_usage';
    $displays['default']['display_options']['style']['options']['info']['file_usage'] = $displays['default']['display_options']['style']['options']['info']['count'];
    unset($displays['default']['display_options']['style']['options']['info']['count']);

    // Disable view caching since file_usage is dynamically computed.
    $displays['default']['display_options']['cache']['type'] = 'none';

    $view->set('display', $displays);
    return TRUE;
  });
}
