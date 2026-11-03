/* eslint-disable import/no-extraneous-dependencies */
import { Plugin } from 'ckeditor5/src/core';
import TableColResizeEditing from './tablecolresizeediting';

class TableColResize extends Plugin {
  /**
   * @inheritDoc
   */
  static get requires() {
    return [TableColResizeEditing];
  }

  /**
   * @inheritDoc
   */
  static get pluginName() {
    return 'TableColResize';
  }
}

export default {
  TableColResize,
};
