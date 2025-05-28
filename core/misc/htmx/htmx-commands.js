((Drupal, drupalSettings, htmx, loadjs) => {
  /**
   * Helper function to merge two objects recursively.
   *
   * @param current
   *   The object to receive the merged values.
   * @param sources
   *   The objects to merge into current.
   *
   * @return object
   *   The merged object.
   *
   * @see https://youmightnotneedjquery.com/#deep_extend
   */
  function mergeSettings(current, ...sources) {
    if (!current) {
      return {};
    }

    sources
      .filter((obj) => Boolean(obj))
      .forEach((obj) => {
        Object.entries(obj).forEach(([key, value]) => {
          switch (Object.prototype.toString.call(value)) {
            case '[object Object]':
              current[key] = current[key] || {};
              current[key] = mergeSettings(current[key], value);
              break;

            case '[object Array]':
              current[key] = mergeSettings(new Array(value.length), value);
              break;

            default:
              current[key] = value;
          }
        });
      });

    return current;
  }

  function addAssets(data) {
    const bundleIds = data
      .filter(({ href, src }) => !loadjs.isDefined(href ?? src))
      .map(({ href, src, type, ...attributes }) => {
        const bundleId = href ?? src;
        let prefix = 'css!';
        if (src) {
          prefix = type === 'module' ? 'module!' : 'js!';
        }

        loadjs(prefix + bundleId, bundleId, {
          // JS files are loaded in order, so this needs to be false when 'src'
          // is defined.
          async: !src,
          // Copy asset tag attributes to the new element.
          before(path, element) {
            // This allows all attributes to be added, like defer, async and
            // crossorigin.
            Object.entries(attributes).forEach(([name, value]) => {
              element.setAttribute(name, value);
            });
          },
        });

        return bundleId;
      });

    // Nothing to load, we resolve the promise right away.
    let assetsLoaded = Promise.resolve();
    // If there are assets to load, use loadjs to manage this process.
    if (bundleIds.length) {
      // Trigger the event once all the dependencies have loaded.
      assetsLoaded = new Promise((resolve, reject) => {
        loadjs.ready(bundleIds, {
          success: resolve,
          error(depsNotFound) {
            const message = Drupal.t(
              `The following files could not be loaded: @dependencies`,
              { '@dependencies': depsNotFound.join(', ') },
            );
            reject(message);
          },
        });
      });
    }

    return assetsLoaded;
  }

  /**
   * Namespace for htmx utilities.
   */
  Drupal.htmx = {};

  /**
   * Helper method to make sure commands are executed in sequence.
   *
   * @param {Array} response
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

  /**
   * Implementation of Drupal ajax commands with htmx.
   *
   * @type {object}
   */
  Drupal.htmx.commands = {
    /**
     * Command to insert new content into the DOM.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.data
     *   The new HTML.
     * @param {string} [response.method]
     *   The jQuery DOM manipulation method to be used.
     * @param {string} [response.selector]
     *   An optional selector string.
     */
    insert({ data, method, selector }) {
      const target = htmx.find(selector);
      const newHtml = data;

      // Detach behaviors.
      htmx.trigger(target, 'htmx:drupal:unload');

      const styleMap = {
        replaceWith: 'outerHTML',
        html: 'innerHTML',
        before: 'beforebegin',
        prepend: 'afterbegin',
        append: 'beforeend',
        after: 'afterend',
      };

      // Make the actual swap and initialize everything.
      htmx.swap(target, newHtml, {
        swapStyle: styleMap[method] || 'outerHTML',
      });
    },

    /**
     * Command to remove a chunk from the page.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.selector
     *   A jQuery selector string.
     */
    remove({ selector }) {
      htmx.findAll(selector).forEach((element) => {
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
    announce({ text, priority }) {
      if (priority) {
        Drupal.announce(text, priority);
      } else {
        Drupal.announce(text);
      }
    },

    /**
     * Command to set the window.location, redirecting the browser.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {string} response.url
     *   The URL to redirect to.
     */
    redirect({ url }) {
      window.location = url;
    },

    /**
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
     */
    settings({ merge, settings }) {
      if (merge) {
        mergeSettings(drupalSettings, settings);
      }
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
     */
    update_build_id(response) {
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
     */
    add_css({ data }) {
      return addAssets(data);
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
    message({
      message,
      messageOptions,
      messageWrapperQuerySelector,
      clearPrevious,
    }) {
      const messages = new Drupal.Message(
        document.querySelector(messageWrapperQuerySelector),
      );
      if (clearPrevious) {
        messages.clear();
      }
      messages.add(message, messageOptions);
    },

    /**
     * Command to add JS.
     *
     * @param {object} response
     *   The response from the Ajax request.
     * @param {Array} response.data
     *   An array of objects of script attributes.
     */
    add_js({ data }) {
      return addAssets(data).then(() => {
        htmx.trigger(document.body, 'htmx:drupal:load');
      });
    },
  };
})(Drupal, drupalSettings, htmx, loadjs);
