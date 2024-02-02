((Drupal, once) => {
  Drupal.behaviors.systemPerformanceAssetsValidate = {
    attach: (context, settings) => {
      const getAssetsValidationMessenger = () => {
        return new Drupal.Message(
          context.querySelector('[data-assets-validate-messages]'),
        );
      };
      const validate = (e) => {
        const el = e.currentTarget;
        if (!el.checked) {
          return;
        }
        const validateUrl = el.dataset.performanceAssetsValidatePath;
        getAssetsValidationMessenger().clear();
        try {
          fetch(validateUrl)
            .then((response) => {
              if (response.status === 404) {
                getAssetsValidationMessenger().add(
                  Drupal.t(
                    'Your server is not configured properly to access @name assets. Review Drupal server requirements.',
                    {
                      '@name': el.name,
                    },
                  ),
                  {
                    type: 'error',
                  },
                );
              } else {
                getAssetsValidationMessenger().add(
                  Drupal.t(
                    'Your server is configured properly to access @name assets.',
                    {
                      '@name': el.name,
                    },
                  ),
                );
              }
            })
            .catch((error) => {
              getAssetsValidationMessenger().add(
                Drupal.t(
                  'Unable to check server requirements for @name assets.',
                  {
                    '@name': el.name,
                  },
                ),
                {
                  type: 'warning',
                },
              );
            });
        } catch (err) {
          getAssetsValidationMessenger().add(
            Drupal.t('Unable to check server requirements for @name assets.', {
              '@name': el.name,
            }),
            {
              type: 'warning',
            },
          );
        }
      };
      once('performance-assets-validate', document.body).forEach(() => {
        context
          .querySelectorAll('[data-performance-assets-validate-path]')
          .forEach((chb) => {
            chb.addEventListener('change', validate);
          });
      });
    },
  };
})(Drupal, once);
