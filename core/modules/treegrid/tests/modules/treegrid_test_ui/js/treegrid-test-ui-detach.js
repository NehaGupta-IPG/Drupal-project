/**
 * @file
 * A behavior that counts the rows whose behaviors are detached.
 *
 * Every detach call on a row increments window.treegridTestUiDetaches, so a
 * test can see that treegrid releases the rows it removes.
 */

((Drupal) => {
  window.treegridTestUiDetaches = 0;

  Drupal.behaviors.treegridTestUiDetach = {
    detach(context) {
      if (context instanceof Element && context.matches('tr[role="row"]')) {
        window.treegridTestUiDetaches += 1;
      }
    },
  };
})(Drupal);
