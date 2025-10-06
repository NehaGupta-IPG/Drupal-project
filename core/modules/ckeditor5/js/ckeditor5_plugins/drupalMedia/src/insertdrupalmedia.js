/* eslint-disable import/no-extraneous-dependencies */
/* cspell:ignore drupalelementstyle drupalelementstyleediting */
/* cspell:ignore insertdrupalmediacommand */
import { Command } from 'ckeditor5/src/core';
import { first } from 'ckeditor5/src/utils';
import { groupNameToModelAttributeKey } from './utils';

/**
 * @module drupalMedia/insertdrupalmediacommand
 */

function createDrupalMedia(writer, attributes, model) {
  return writer.createElement(model, attributes);
}

function determineImageTypeForInsertionAtSelection(schema, selection) {
  const firstBlock = first(selection.getSelectedBlocks());
  // Insert a block media if the selection is not in/on block elements or it's
  // on a block widget.
  if (!firstBlock || schema.isObject(firstBlock)) {
    return 'drupalMedia';
  }
  // A block image should also be inserted into an empty block element
  // (that is not an empty list item so the list won't get split).
  if (firstBlock.isEmpty && firstBlock.name !== 'listItem') {
    return 'drupalMedia';
  }
  // Otherwise insert an inline media.
  return 'drupalMediaInline';
}

function determineImageTypeForInsertion(editor, selectable) {
  const schema = editor.model.schema;
  // Try to replace the selected widget (e.g. another image).
  if (selectable.is('selection')) {
    return determineImageTypeForInsertionAtSelection(schema, selectable);
  }
  return schema.checkChild(selectable, 'drupalMediaInline')
    ? 'drupalMediaInline'
    : 'drupalMedia';
}

/**
 * The insert media command.
 *
 * The command is registered by the `DrupalMediaEditing` plugin as
 * `insertDrupalMedia`.
 *
 * In order to insert media at the current selection position, execute the
 * command and pass the attributes desired in the drupal-media element:
 *
 * @example
 *    editor.execute('insertDrupalMedia', {
 *      'alt': 'Alt text',
 *      'data-align': 'left',
 *      'data-caption': 'Caption text',
 *      'data-entity-type': 'media',
 *      'data-entity-uuid': 'media-entity-uuid',
 *      'data-view-mode': 'default',
 *    });
 *
 * @private
 */
export default class InsertDrupalMediaCommand extends Command {
  execute(attributes) {
    const mediaEditing = this.editor.plugins.get('DrupalMediaEditing');

    // Create object that contains supported data-attributes in view data by
    // flipping `DrupalMediaEditing.attrs` object (i.e. keys from object become
    // values and values from object become keys).
    const dataAttributeMapping = Object.entries(mediaEditing.attrs).reduce(
      (result, [key, value]) => {
        result[value] = key;
        return result;
      },
      {},
    );

    // This converts data-attribute keys to keys used in model.
    const modelAttributes = Object.keys(attributes).reduce(
      (result, attribute) => {
        if (dataAttributeMapping[attribute]) {
          result[dataAttributeMapping[attribute]] = attributes[attribute];
        }
        return result;
      },
      {},
    );

    // Check if there's Drupal Element Style matching the default attributes on
    // the media.
    // @see module:drupalMedia/drupalelementstyle/drupalelementstyleediting~DrupalElementStyleEditing
    if (this.editor.plugins.has('DrupalElementStyleEditing')) {
      const elementStyleEditing = this.editor.plugins.get(
        'DrupalElementStyleEditing',
      );

      const { normalizedStyles } = elementStyleEditing;
      // eslint-disable-next-line no-restricted-syntax
      for (const group of Object.keys(normalizedStyles)) {
        // eslint-disable-next-line no-restricted-syntax
        for (const style of elementStyleEditing.normalizedStyles[group]) {
          if (
            attributes[style.attributeName] &&
            style.attributeValue === attributes[style.attributeName]
          ) {
            const modelAttribute = groupNameToModelAttributeKey(group);
            modelAttributes[modelAttribute] = style.name;
          }
        }
      }
    }

    const insertedModel = determineImageTypeForInsertion(
      this.editor,
      this.editor.model.document.selection,
    );

    this.editor.model.change((writer) => {
      this.editor.model.insertObject(
        createDrupalMedia(writer, modelAttributes, insertedModel),
      );
    });
  }

  refresh() {
    const model = this.editor.model;
    const selection = model.document.selection;
    const mediaModel = determineImageTypeForInsertion(this.editor, selection);
    const allowedIn = model.schema.findAllowedParent(
      selection.getFirstPosition(),
      mediaModel,
    );
    this.isEnabled = allowedIn !== null;
  }
}
