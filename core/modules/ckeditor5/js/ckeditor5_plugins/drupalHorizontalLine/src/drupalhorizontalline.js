/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore drupalhorizontallineediting

import { Plugin } from 'ckeditor5/src/core';
import DrupalHorizontalLineEditing from './drupalhorizontallineediting';

/**
 * Drupal-specific plugin to alter the CKEditor 5 horizontalLine command.
 *
 * @private
 */
class DrupalHorizontalLine extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [DrupalHorizontalLineEditing];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalHorizontalLine';
  }
}

export default DrupalHorizontalLine;
