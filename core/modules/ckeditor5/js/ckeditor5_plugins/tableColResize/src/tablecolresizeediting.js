/* eslint-disable import/no-extraneous-dependencies */
/* eslint-disable no-restricted-syntax */
import { Plugin } from 'ckeditor5/src/core';

export default class TableColResizeEditing extends Plugin {
  /**
   * @inheritDoc
   */
  static get pluginName() {
    return 'TableColResizeEditing';
  }

  /**
   * @inheritDoc
   */
  afterInit() {
    if (this.editor.plugins.has('TableColumnResizeEditing')) {
      this._registerConverters();
    }
  }

  /**
   * Registers converters necessary for column resizing.
   */
  _registerConverters() {
    const { editor } = this;
    const { dataAttribute } = editor.config._config.tableColResize;

    // Converts the width style of a tableColumn model into a data attribute on
    // the <col> view.
    editor.conversion.for('downcast').add((dispatcher) =>
      dispatcher.on(
        'attribute:columnWidth:tableColumn',
        (evt, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const elementView = conversionApi.mapper.toViewElement(data.item);

          if (data.attributeNewValue !== null) {
            // The data attribute is set for applying the width via a style
            // attribute when drupal renders the WYSIWYG content via the
            // resize_tablecolumns_filter. We use a data attribute to work around
            // the filter_html restriction that prohibits usage of the style
            // attribute.
            viewWriter.setAttribute(
              dataAttribute,
              data.attributeNewValue,
              elementView,
            );
          } else {
            viewWriter.removeAttribute(dataAttribute, elementView);
          }
        },
      ),
    );

    // Ensures that the value of the data-resize-width attribute is added to the
    // ckeditor model when the editor loads.
    editor.conversion.for('upcast').add((dispatcher) =>
      dispatcher.on(`element:col`, (evt, data, conversionApi) => {
        const { schema, writer } = conversionApi;
        const colWidth = data.viewItem.getAttribute(dataAttribute);

        // Do not go for the model element after data.modelCursor because it might happen
        // that a single view element was converted to multiple model elements. Get all of them.
        for (const item of data.modelRange.getItems({ shallow: true })) {
          if (schema.checkAttribute(item, 'columnWidth')) {
            writer.setAttribute('columnWidth', colWidth, item);
          }
        }
      }),
    );

    // Converts the width style of a table model into a data attribute on the
    // <table> view.
    editor.conversion.for('downcast').add((dispatcher) =>
      dispatcher.on(
        'attribute:tableWidth:table',
        (evt, data, conversionApi) => {
          const viewWriter = conversionApi.writer;
          const elementView = conversionApi.mapper.toViewElement(data.item);

          if (data.attributeNewValue !== null) {
            // The data attribute is set for applying the width via a style
            // attribute when drupal renders the WYSIWYG content via the
            // resize_tablecolumns_filter. We use a data attribute to work around
            // the filter_html restriction that prohibits usage of the style
            // attribute.
            viewWriter.setAttribute(
              dataAttribute,
              data.attributeNewValue,
              elementView,
            );
          } else {
            viewWriter.removeAttribute(dataAttribute, elementView);
          }
        },
      ),
    );

    // Ensures that the value of the data-resize-width attribute is added to the
    // ckeditor model when the editor loads.
    editor.conversion.for('upcast').add((dispatcher) =>
      dispatcher.on(`element:table`, (evt, data, conversionApi) => {
        const { schema, writer } = conversionApi;
        const tableWidth = data.viewItem.getAttribute(dataAttribute);

        // Do not go for the model element after data.modelCursor because it might happen
        // that a single view element was converted to multiple model elements. Get all of them.
        for (const item of data.modelRange.getItems({ shallow: true })) {
          if (schema.checkAttribute(item, 'tableWidth')) {
            writer.setAttribute('tableWidth', tableWidth, item);
          }
        }
      }),
    );
  }
}
