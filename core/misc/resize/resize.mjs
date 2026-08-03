import 'https://cdn.interactjs.io/v1.9.20/auto-start/index.js'
import 'https://cdn.interactjs.io/v1.9.20/actions/resize/index.js'
import 'https://cdn.interactjs.io/v1.9.20/modifiers/index.js'
import 'https://cdn.interactjs.io/v1.9.20/dev-tools/index.js'
import interact from 'https://cdn.interactjs.io/v1.9.20/interactjs/index.js'

(function ($, Drupal, interact) {
  Drupal.behaviors.resizeAnything = {
    attach() {

      $(window).on('dialog:aftercreate', () => {
        [].forEach.call(
          document.querySelectorAll('.ui-dialog-position-side'),
          (resizeElement) => {
            interact(resizeElement).resizable({
              edges: { top: false, left: true, bottom: false, right: false },
              listeners: {
                move: function (event) {
                  let { x, y } = event.target.dataset;

                  x = (parseFloat(x) || 0) + event.deltaRect.left;
                  y = (parseFloat(y) || 0) + event.deltaRect.top;

                  Object.assign(event.target.style, {
                    width: `${event.rect.width}px`,
                    height: `${event.rect.height}px`,
                    transform: `translate(${x}px, ${y}px)`,
                  });

                  Object.assign(event.target.dataset, { x, y });
                },
              },
            });
          },
        );

        [].forEach.call(
          document.querySelectorAll('.workspaces-dialog'),
          (resizeElement) => {
            interact(resizeElement).resizable({
              edges: { top: false, left: false, bottom: true, right: false },
              listeners: {
                move: function (event) {
                  let { x, y } = event.target.dataset;

                  x = (parseFloat(x) || 0) + event.deltaRect.left;
                  y = (parseFloat(y) || 0) + event.deltaRect.top;

                  Object.assign(event.target.style, {
                    width: `${event.rect.width}px`,
                    height: `${event.rect.height}px`,
                    transform: `translate(${x}px, ${y}px)`,
                  });

                  Object.assign(event.target.dataset, { x, y });
                },
              },
            });
          },
        );
      });
    },
  };
})(jQuery, Drupal, interact);
