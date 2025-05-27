((Drupal, htmx, loadjs) => {
  Drupal.htmx = {};

  /**
   * Helper method to make sure commands are executed in sequence.
   *
   * @param {Array.<Drupal.AjaxCommands~commandDefinition>} response
   *   Drupal Ajax response.
   * @param {number} status
   *   XMLHttpRequest status.
   *
   * @return {Promise}
   *  The promise that will resolve once all commands have finished executing.
   */
  Drupal.htmx.commandExecutionQueue = function (response, status) {
    const ajaxCommands = Drupal.htmx.commands;
    return Object.keys(response || {}).reduce(
      // Add all commands to a single execution queue.
      (executionQueue, key) =>
        executionQueue.then(() => {
          const { command } = response[key];
          if (command && ajaxCommands[command]) {
            // When a command returns a promise, the remaining commands will not
            // execute until that promise has been fulfilled. This is typically
            // used to ensure JavaScript files added via the 'add_js' command
            // have loaded before subsequent commands execute.
            return ajaxCommands[command](response[key], status);
          }
        }),
      Promise.resolve(),
    );
  };

  Drupal.htmx.commands = {
    /**
     * Command to insert new content into the DOM.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.data
     *   The data to use with the jQuery method.
     * @param {string} [response.method]
     *   The jQuery DOM manipulation method to be used.
     * @param {string} [response.selector]
     *   An optional jQuery selector string.
     * @param {object} [response.settings]
     *   An optional array of settings that will be used.
     */
    insert(response) {
      const target = htmx.find(response.selector);
      const newHtml = response.data;

      // Detach behaviors.
      htmx.trigger(target, 'htmx:drupal:unload');

      const styleMap = {
        replaceWith: 'outerHTML',
      };

      // Make the actual swap and initialize everything.
      htmx.swap(target, newHtml, {
        swapStyle: styleMap?.[response.method] || 'outerHTML',
      });
    },

    /**
     * Command to remove a chunk from the page.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.selector
     *   A jQuery selector string.
     * @param {object} [response.settings]
     *   An optional array of settings that will be used.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    remove(response, status) {
      htmx.findAll(response.selector).forEach((element) => {
        htmx.trigger(element, 'htmx:drupal:unload');
        element.remove();
      });
    },

    /**
     * Command to provide triggers audio UAs to read the supplied text.
     *
     * @param {object} response
     *   The JSON response from the Ajax request.
     * @param {string} [response.text]
     *   The text that will be read.
     * @param {string} [response.priority]
     *   An optional priority that will be used for the announcement.
     */
    announce(response) {
      if (response.priority) {
        Drupal.announce(response.text, response.priority);
      } else {
        Drupal.announce(response.text);
      }
    },

    /**
     * Command to set the window.location, redirecting the browser.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.url
     *   The URL to redirect to.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    redirect(response, status) {
      window.location = response.url;
    },

    /**
     * @TODO
     *
     * Command to set the settings used for other commands in this response.
     *
     * This method will also remove expired `drupalSettings.ajax` settings.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {boolean} response.merge
     *   Determines whether the additional settings should be merged to the
     *   global settings.
     * @param {object} response.settings
     *   Contains additional settings to add to the global settings.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    settings(response, status) {
      /*
      const ajaxSettings = drupalSettings.ajax;

      // Clean up drupalSettings.ajax.
      if (ajaxSettings) {
        Drupal.ajax.expired().forEach((instance) => {
          // If the Ajax object has been created through drupalSettings.ajax
          // it will have a selector. When there is no selector the object
          // has been initialized with a special class name picked up by the
          // Ajax behavior.

          if (instance.selector) {
            const selector = instance.selector.replace('#', '');
            if (selector in ajaxSettings) {
              delete ajaxSettings[selector];
            }
          }
        });
      }

      if (response.merge) {
        $.extend(true, drupalSettings, response.settings);
      } else {
        ajax.settings = response.settings;
      }
       */
    },

    /**
     * Command to update a form's build ID.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.old
     *   The old form build ID.
     * @param {string} response.new
     *   The new form build ID.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    update_build_id(response, status) {
      document
        .querySelectorAll(
          `input[name="form_build_id"][value="${response.old}"]`,
        )
        .forEach((item) => {
          item.value = response.new;
        });
    },

    /**
     * Command to add css.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {object[]} response.data
     *   An array of styles to be added.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    add_css(response, status) {
      const allUniqueBundleIds = response.data.map(function (style) {
        const uniqueBundleId = style.href;
        // Force file to load as a CSS stylesheet using 'css!' flag.
        if (!loadjs.isDefined(uniqueBundleId)) {
          loadjs(`css!${style.href}`, uniqueBundleId, {
            before(path, styleEl) {
              // This allows all attributes to be added, like media.
              Object.keys(style).forEach((attributeKey) => {
                styleEl.setAttribute(attributeKey, style[attributeKey]);
              });
            },
          });
        }
        return uniqueBundleId;
      });
      // Returns the promise so that the next AJAX command waits on the
      // completion of this one to execute, ensuring the CSS is loaded before
      // executing.
      return new Promise((resolve, reject) => {
        loadjs.ready(allUniqueBundleIds, {
          success() {
            // All CSS files were loaded. Resolve the promise and let the
            // remaining commands execute.
            resolve();
          },
          error(depsNotFound) {
            const message = Drupal.t(
              `The following files could not be loaded: @dependencies`,
              { '@dependencies': depsNotFound.join(', ') },
            );
            reject(message);
          },
        });
      });
    },

    /**
     * Command to add a message to the message area.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.messageWrapperQuerySelector
     *   The zone where to add the message. If null, the default will be used.
     * @param {string} response.message
     *   The message text.
     * @param {string} response.messageOptions
     *   The options argument for Drupal.Message().add().
     * @param {boolean} response.clearPrevious
     *   If true, clear previous messages.
     */
    message(response) {
      const messages = new Drupal.Message(
        document.querySelector(response.messageWrapperQuerySelector),
      );
      if (response.clearPrevious) {
        messages.clear();
      }
      messages.add(response.message, response.messageOptions);
    },

    /**
     * Command to add JS.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {Array} response.data
     *   An array of objects of script attributes.
     * @param {number} [status]
     *   The XMLHttpRequest status.
     */
    add_js(response, status) {
      const parentEl = document.querySelector(response.selector || 'body');
      const allUniqueBundleIds = response.data.map((script) => {
        const uniqueBundleId = script.src;
        if (!loadjs.isDefined(uniqueBundleId)) {
          loadjs(script.src, uniqueBundleId, {
            // The default loadjs behavior is to load script with async, in Drupal
            // we need to explicitly tell scripts to load async, this is set in
            // the before callback below if necessary.
            async: false,
            before(path, scriptEl) {
              // This allows all attributes to be added, like defer, async and
              // crossorigin.
              Object.keys(script).forEach((attributeKey) => {
                scriptEl.setAttribute(attributeKey, script[attributeKey]);
              });

              // By default, loadjs appends the script to the head. When scripts
              // are loaded via AJAX, their location has no impact on
              // functionality. But, since non-AJAX loaded scripts can choose
              // their parent element, we provide that option here for the sake of
              // consistency.
              parentEl.appendChild(scriptEl);
              // Return false to bypass loadjs' default DOM insertion mechanism.
              return false;
            },
          });
        }
        return uniqueBundleId;
      });
      // Returns the promise so that the next AJAX command waits on the
      // completion of this one to execute, ensuring the JS is loaded before
      // executing.
      return new Promise((resolve, reject) => {
        loadjs.ready(allUniqueBundleIds, {
          success() {
            htmx.trigger(parentEl, 'htmx:drupal:load');
            // All JS files were loaded and new and old behaviors have
            // been attached. Resolve the promise and let the remaining
            // commands execute.
            resolve();
          },
          error(depsNotFound) {
            const message = Drupal.t(
              `The following files could not be loaded: @dependencies`,
              { '@dependencies': depsNotFound.join(', ') },
            );
            reject(message);
          },
        });
      });
    },
  };
})(Drupal, htmx, loadjs);
