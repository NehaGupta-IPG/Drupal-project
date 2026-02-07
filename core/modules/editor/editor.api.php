<?php

/**
 * @file
 * Documentation for Text Editor API.
 */

use Drupal\filter\FilterFormatInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Performs alterations on text editor definitions.
 *
 * @param array $editors
 *   An array of metadata of text editors, as collected by the plugin annotation
 *   discovery mechanism.
 *
 * @see \Drupal\editor\Plugin\EditorBase
 */
function hook_editor_info_alter(array &$editors) {
  $editors['some_other_editor']['label'] = t('A different name');
  $editors['some_other_editor']['library']['module'] = 'my_editor_override';
}

/**
 * Modifies JavaScript settings that are added for text editors.
 *
 * @param array $settings
 *   All the settings that will be added to the page for the text formats to
 *   which a user has access.
 */
function hook_editor_js_settings_alter(array &$settings) {
  if (isset($settings['editor']['formats']['basic_html'])) {
    $settings['editor']['formats']['basic_html']['editor'] = 'MyDifferentEditor';
    $settings['editor']['formats']['basic_html']['editorSettings']['buttons'] = ['strong', 'italic', 'underline'];
  }
}

/**
 * @} End of "addtogroup hooks".
 */
