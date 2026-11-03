(function ($, Drupal) {
  Drupal.behaviors.ckeditorImageFileUploadValidation = {
    attach(context, settings) {
      // Using native javascript listener to intercept creditor's listening.
      document.addEventListener(
        'change',
        function (event) {
          if (
            event.target &&
            event.target.localName === 'input' &&
            event.target.className === 'ck-hidden'
          ) {
            const { files } = event.target;
            if (files.length > 0) {
              const fileSize = files[0].size;
              const ckEditor = event.target.closest('.ck-editor');
              const textareaElement = ckEditor.previousElementSibling;
              // Check the selected text formatter.
              if (
                textareaElement &&
                textareaElement.hasAttribute('data-editor-active-text-format')
              ) {
                const textFormat = textareaElement.getAttribute(
                  'data-editor-active-text-format',
                );
                const maxSize =
                  settings.editor.formats[textFormat].editorSettings.config
                    .drupalImageUpload.imageUploadSettings.max_size;
                if (textFormat && maxSize) {
                  if (fileSize > maxSize) {
                    const maxSizeMB = maxSize / 1024 / 1024;
                    const alertMessage = Drupal.t(
                      'File size exceeds the allowed limit @size MB',
                      { '@size': maxSizeMB },
                    );
                    alert(alertMessage);
                    event.target.value = '';
                  }
                }
              }
            }
          }
        },
        true,
      );
    },
  };
})(jQuery, Drupal);
