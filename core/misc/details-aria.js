/**
 * @file
 * Add aria attribute handling for details and summary elements.
 */

((Drupal, once) => {
  Drupal.behaviors.detailsAria = {
    attach(context) {
      once('detailsAria', 'details', context).forEach((details) => {
        details.addEventListener('toggle', () => {
          const summary = details.querySelector('summary');

          if (!summary) {
            return;
          }

          summary.setAttribute(
            'aria-expanded',
            details.open ? 'true' : 'false',
          );
        });
      });
    },
  };
})(Drupal, once);
