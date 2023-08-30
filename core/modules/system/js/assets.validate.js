((Drupal, drupalSettings) => {
  Drupal.behaviors.systemPerformanceAssetsValidate = {
    'attach': (context, settings) => {
      const validate = () => {
        const messages = new Drupal.Message(context.querySelector('[data-assets-validate-messages]'));
        drupalSettings.systemPerformanceAssetsChecklist.forEach(urlInfo => {
          messages.clear();
          try {
            fetch(urlInfo['url'])
              .then(response => {
                if (response.status === 404) {
                  messages.error(Drupal.t(
                    'Your server is not configured properly to access @name assets. Review Drupal server requirements.',
                    { '@name': urlInfo['name'] },
                  ));
                } else {
                  messages.add(Drupal.t(
                    'Your server is configured properly to access @name assets.',
                    { '@name': urlInfo['name'] },
                  ));
                }
              })
              .catch(error => {
                messages.warning(Drupal.t(
                  'Unable to check server requirements for @name assets.',
                  { '@name': urlInfo['name'] },
                ));
              });
          } catch (err) {
            messages.warning(Drupal.t(
              'Unable to check server requirements for @name assets.',
              { '@name': urlInfo['name'] },
            ));
          }
        });
      };
      context.querySelectorAll('[data-assets-validate-button="init"]').forEach(btn => {
        btn.setAttribute('data-assets-validate-button', '');
        btn.setAttribute('type', 'button');
        btn.addEventListener('keyup', validate);
        btn.addEventListener('click', validate);

      });
    },
  };
})(Drupal, drupalSettings);
