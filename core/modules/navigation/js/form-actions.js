((Drupal, once) => {
  Drupal.behaviors.navigationFormActions = {
    attach: (context) => {
      // Should be some common attribute.
      once(
        'form-actions',
        '.node-form',
        '.taxonomy-vocabulary-form',
        '.media-form',
        context,
      ).forEach((form) => {
        const topBar = document.querySelector('.top-bar__content');
        const action = form.querySelector(
          '[data-drupal-selector="edit-actions"] [type="submit"]',
        );

        if (topBar && action) {
          // Set the form attribute for the submit action button
          // and append it to the top bar content section.
          action.setAttribute('form', form.getAttribute('id'));
          topBar.append(action);
        }
      });
    },
  };
})(Drupal, once);
