/**
 * @file
 * Add aria attribute handling for details and summary elements.
 */

(function (Drupal, once) {
  Drupal.behaviors.detailsAria = {
    attach(context) {
      once('detailsAria', 'details', context).forEach((details) => {
        // Only a direct child <summary> is valid.
        const summary = details.querySelector(':scope > summary');

        if (!summary) {
          return;
        }

        // Add role only if it is not already present.
        if (!summary.hasAttribute('role')) {
          summary.setAttribute('role', 'button');
        }

        // Set initial state when JS runs.
        summary.setAttribute('aria-expanded', details.open);

        // Keep aria-expanded in sync with the real open state.
        details.addEventListener('toggle', () => {
          summary.setAttribute('aria-expanded', details.open);
        });
      });
    },
  };
})(Drupal, once);
