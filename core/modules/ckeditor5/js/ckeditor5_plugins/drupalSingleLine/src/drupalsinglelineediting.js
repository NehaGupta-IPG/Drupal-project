/* eslint-disable import/no-extraneous-dependencies */
import { Plugin } from 'ckeditor5';

/**
 * Enforces single-line editing behavior.
 *
 * - Prevents Enter/Shift+Enter from inserting line breaks.
 * - Normalizes pasted content to a single line by removing line breaks.
 *
 * @private
 */
class DrupalSingleLineEditing extends Plugin {
  /**
   * @inheritdoc
   */
  static get pluginName() {
    return 'DrupalSingleLineEditing';
  }

  /**
   * @inheritdoc
   */
  init() {
    const { editor } = this;
    const { model } = editor;
    const { schema } = model;

    // This plugin is only loaded when the editor has the
    // data-drupal-ckeditor5-single-line attribute, so we can always apply
    // single-line restrictions here.

    // Prevent multiple paragraphs - only allow one paragraph in root.
    schema.addChildCheck((context, childDefinition) => {
      if (context.endsWith('$root') && childDefinition.name === 'paragraph') {
        const root = model.document.getRoot();
        const existingParagraphs = Array.from(root.getChildren()).filter(
          (item) => item.is('paragraph'),
        );
        // Only allow if there's no existing paragraph.
        return existingParagraphs.length === 0;
      }
    });
    // Prevent Enter from inserting a new block.
    editor.keystrokes.set(
      'Enter',
      (data, cancel) => {
        cancel();
      },
      { priority: 'highest' },
    );

    // Prevent Shift+Enter soft breaks.
    editor.keystrokes.set(
      'Shift+Enter',
      (data, cancel) => {
        cancel();
      },
      { priority: 'highest' },
    );

    // Normalize pasted content to a single line while preserving inline
    // formatting (e.g., links, bold, italic).
    const clipboard = editor.plugins.get('ClipboardPipeline');
    clipboard.on(
      'inputTransformation',
      (evt, data) => {
        const dataTransfer = data.dataTransfer;
        if (!dataTransfer) {
          return;
        }

        // Prefer HTML content to preserve inline formatting (links, bold, etc.).
        const html = dataTransfer.getData('text/html');
        const text = dataTransfer.getData('text/plain');

        // If we have HTML, normalize it to a single line while preserving
        // inline markup by flattening block elements.
        if (html) {
          try {
            // Create a temporary DOM element to parse and normalize the HTML.
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;

            // Remove non-content elements (scripts, styles, comments, etc.).
            // These should not appear in editor content.
            const nonContentElements = Array.from(
              tempDiv.querySelectorAll(
                'script, style, noscript, iframe, object, embed',
              ),
            );
            nonContentElements.forEach((el) => el.remove());

            // Flatten block-level elements but preserve their inline content.
            // Include all common block elements: text containers, lists, tables,
            // media containers, etc.
            // Process in reverse to avoid issues with modifying the DOM while
            // iterating.
            const blockSelectors = [
              // Text containers (from CKEditor 5's $block text containers).
              'p',
              'div',
              'h1',
              'h2',
              'h3',
              'h4',
              'h5',
              'h6',
              'pre',
              // Lists and list items.
              'ul',
              'ol',
              'li',
              // Block quotes and horizontal rules.
              'blockquote',
              'hr',
              // Tables (all table-related elements should be removed).
              'table',
              'thead',
              'tbody',
              'tfoot',
              'tr',
              'td',
              'th',
              'caption',
              // Media containers.
              'figure',
              'figcaption',
              // Other block elements.
              'section',
              'article',
              'aside',
              'header',
              'footer',
              'nav',
              'main',
              'address',
              'dl',
              'dt',
              'dd',
            ];
            const blockElements = Array.from(
              tempDiv.querySelectorAll(blockSelectors.join(', ')),
            ).reverse();
            blockElements.forEach((block) => {
              // Move all children before the block element, then remove the block.
              const parent = block.parentNode;
              const nextSibling = block.nextSibling;
              while (block.firstChild) {
                parent.insertBefore(block.firstChild, nextSibling);
              }
              // Insert a space before removing the block to separate content.
              if (nextSibling) {
                parent.insertBefore(document.createTextNode(' '), nextSibling);
              } else {
                parent.appendChild(document.createTextNode(' '));
              }
              parent.removeChild(block);
            });

            // Remove images (block-level media shouldn't be in single-line).
            const images = Array.from(tempDiv.querySelectorAll('img'));
            images.forEach((img) => {
              // Replace with alt text if available, otherwise remove.
              const altText = img.getAttribute('alt') || '';
              const parent = img.parentNode;
              if (altText && parent) {
                parent.insertBefore(document.createTextNode(altText), img);
              }
              parent?.removeChild(img);
            });

            // Replace <br> tags with spaces.
            const brElements = Array.from(tempDiv.querySelectorAll('br'));
            brElements.forEach((br) => {
              const parent = br.parentNode;
              if (parent) {
                parent.replaceChild(document.createTextNode(' '), br);
              }
            });

            // Get the normalized HTML content.
            let normalizedHtml = tempDiv.innerHTML.trim();
            // Replace any remaining line breaks in the HTML string.
            normalizedHtml = normalizedHtml.replace(/[\r\n]+/g, ' ');
            // Normalize multiple consecutive spaces to single space (but preserve
            // non-breaking spaces and other special whitespace).
            normalizedHtml = normalizedHtml.replace(/[ \t]+/g, ' ');

            if (normalizedHtml) {
              evt.stop();
              // Parse the normalized HTML and insert it into the model.
              // This preserves inline formatting like links.
              const viewFragment = editor.data.processor.toView(normalizedHtml);
              const modelFragment = editor.data.toModel(viewFragment);
              editor.model.change((writer) => {
                editor.model.insertContent(
                  modelFragment,
                  editor.model.document.selection,
                );
              });
              return;
            }
          } catch (error) {
            // If HTML parsing fails, fall through to plain text handling.
            // eslint-disable-next-line no-console
            console.warn(
              'Failed to parse pasted HTML, falling back to plain text:',
              error,
            );
          }
        }

        // Fall back to plain text if no HTML is available.
        if (text) {
          const singleLine = text.replace(/[\r\n]+/g, ' ').trim();
          if (singleLine === '') {
            // Let CKEditor handle empty paste normally.
            return;
          }

          evt.stop();
          editor.model.change((writer) => {
            editor.model.insertContent(
              writer.createText(singleLine),
              editor.model.document.selection,
            );
          });
        }
      },
      { priority: 'high' },
    );
  }
}

export default DrupalSingleLineEditing;
