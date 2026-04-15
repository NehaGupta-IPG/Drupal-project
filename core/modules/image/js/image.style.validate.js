((Drupal, once) => {
  Drupal.behaviors.imageStyleValidate = {
    attach: (context) => {
      const getMessenger = () => {
        return new Drupal.Message(
          document.querySelector('[data-image-style-validate-messages]'),
        );
      };

      /**
       * Validates a single scheme URL and returns a result object.
       *
       * @param {string} url - The URL to validate via HEAD request.
       * @param {string} scheme - The scheme label (e.g. 'public' or 'private').
       * @return {Promise<{scheme: string, ok: boolean, error: boolean}>}
       */
      const validateScheme = (url, scheme) => {
        return fetch(url, { method: 'HEAD' })
          .then((response) => ({
            scheme,
            ok: response.ok,
            // phpcs:ignore Generic.PHP.UpperCaseConstant.Found
            error: false,
          }))
          .catch(() => ({
            scheme,
            // phpcs:ignore Generic.PHP.UpperCaseConstant.Found
            ok: false,
            // phpcs:ignore Generic.PHP.UpperCaseConstant.Found
            error: true,
          }));
      };

      once(
        'image-style-validate',
        '[data-image-style-validate-public]',
        context,
      ).forEach((link) => {
        link.addEventListener('click', (e) => {
          e.preventDefault();
          const publicUrl = link.dataset.imageStyleValidatePublic;
          const privateUrl = link.dataset.imageStyleValidatePrivate;
          const styleName = link.dataset.imageStyleName;
          const messenger = getMessenger();
          messenger.clear();

          const validations = [validateScheme(publicUrl, 'public')];
          if (privateUrl) {
            validations.push(validateScheme(privateUrl, 'private'));
          }

          Promise.all(validations).then((results) => {
            const allOk = results.every((result) => result.ok && !result.error);
            if (allOk) {
              messenger.add(
                Drupal.t(
                  'Server is configured properly for the @name image style.',
                  {
                    '@name': styleName,
                  },
                ),
              );
              return;
            }

            results.forEach((result) => {
              if (result.error) {
                messenger.add(
                  Drupal.t(
                    'Unable to check @scheme server requirements for the @name image style.',
                    {
                      '@scheme': result.scheme,
                      '@name': styleName,
                    },
                  ),
                  { type: 'warning' },
                );
              } else if (result.ok) {
                messenger.add(
                  Drupal.t(
                    'Server is configured properly for the @name image style (@scheme files).',
                    {
                      '@scheme': result.scheme,
                      '@name': styleName,
                    },
                  ),
                );
              } else {
                messenger.add(
                  Drupal.t(
                    'Server is not configured properly for the @name image style (@scheme files). Review Drupal server requirements.',
                    {
                      '@scheme': result.scheme,
                      '@name': styleName,
                    },
                  ),
                  { type: 'error' },
                );
              }
            });
          });
        });
      });
    },
  };
})(Drupal, once);
