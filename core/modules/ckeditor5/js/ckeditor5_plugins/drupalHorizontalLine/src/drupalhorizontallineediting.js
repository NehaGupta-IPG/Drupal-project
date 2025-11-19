/* eslint-disable import/no-extraneous-dependencies */
import { Plugin } from 'ckeditor5/src/core';

/**
 * Alters the horizontalLine command to output a `class` attribute for `hrClass` in model.
 *
 * @private
 */
class DrupalHorizontalLineEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalHorizontalLineEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    this.editor.conversion.attributeToAttribute({
      model: 'hrClass',
      view: 'class'
    });
  }
}

export default DrupalHorizontalLineEditing;
