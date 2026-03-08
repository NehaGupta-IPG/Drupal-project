/* eslint-disable import/no-extraneous-dependencies */
// cspell:ignore drupalsinglelineediting

import { Plugin } from 'ckeditor5/src/core';
import DrupalSingleLineEditing from './drupalsinglelineediting';

/**
 * Drupal-specific plugin to enforce single-line editing.
 *
 * @private
 */
class DrupalSingleLine extends Plugin {
  /**
   * @inheritdoc
   */
  static get requires() {
    return [DrupalSingleLineEditing];
  }

  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalSingleLine';
  }
}

export default DrupalSingleLine;
