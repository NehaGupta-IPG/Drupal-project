<?php

declare(strict_types=1);

namespace Drupal\content_translation\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Field hook implementations for content_translation.
 */
readonly class ContentTranslationFieldHooks {

  /**
   * Implements hook_field_info_alter().
   *
   * Content translation extends the @FieldType annotation with following key:
   * - column_groups: contains information about the field type properties
   *   which columns should be synchronized across different translations and
   *   which are translatable. This is useful for instance to translate the
   *   "alt" and "title" textual elements of an image field, while keeping the
   *   same image on every translation. Each group has the following keys:
   *   - title: Title of the column group.
   *   - translatable: (optional) If the column group should be translatable by
   *     default, defaults to FALSE.
   *   - columns: (optional) A list of columns of this group. Defaults to the
   *     name of the group as the single column.
   *   - require_all_groups_for_translation: (optional) Set to TRUE to enforce
   *     that making this column group translatable requires all others to be
   *     translatable too.
   *
   * @see Drupal\image\Plugin\Field\FieldType\ImageItem
   */
  #[Hook('field_info_alter')]
  public function fieldInfoAlter(array &$info): void {
    foreach ($info as $key => $settings) {
      // Supply the column_groups key if it's not there.
      if (empty($settings['column_groups'])) {
        $info[$key]['column_groups'] = [];
      }
    }
  }

}
