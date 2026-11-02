/**
 * @file
 * Handles AJAX fetching of views, including filter submission and response.
 */

(function ($, Drupal, drupalSettings) {
  /**
   * Attaches the AJAX behavior to exposed filters forms and key View links.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches ajaxView functionality to relevant elements.
   */
  Drupal.behaviors.ViewsAjaxView = {};
  Drupal.behaviors.ViewsAjaxView.attach = function (context, settings) {
    if (settings?.views?.ajaxViews) {
      const {
        views: { ajaxViews },
      } = settings;
      Drupal.views.sortByNestingLevel(ajaxViews).forEach(({ key, value }) => {
        Drupal.views.instances[key] = new Drupal.views.ajaxView(value);
      });
    }
  };
  Drupal.behaviors.ViewsAjaxView.detach = (context, settings, trigger) => {
    if (trigger === 'unload') {
      if (settings?.views?.ajaxViews) {
        const {
          views: { ajaxViews },
        } = settings;
        Object.keys(ajaxViews || {}).forEach((i) => {
          const selector = `.js-view-dom-id-${ajaxViews[i].view_dom_id}`;
          if ($(selector, context).length) {
            delete Drupal.views.instances[i];
            delete settings.views.ajaxViews[i];
          }
        });
      }
    }
  };

  /**
   * @namespace
   */
  Drupal.views = {};

  /**
   * @type {object.<string, Drupal.views.ajaxView>}
   */
  Drupal.views.instances = {};

  /**
   * JavaScript object for a certain view.
   * Sort the view Javascript objects by nesting level.
   *
   * @param {object} ajaxViews
   *   The views JavaScript object.
   * @param {string} settings.view_dom_id
   *   The DOM id of the view.
   * @param {string} settings.selector
   *   The selector of the view.
   *
   * @return {object}
   *   The sorted ajaxViews object.
   */
  Drupal.views.sortByNestingLevel = function (ajaxViews) {
    const ajaxViewsArray = Object.keys(ajaxViews || {}).map((key) => {
      ajaxViews[key].selector = `.js-view-dom-id-${ajaxViews[key].view_dom_id}`;

      return {
        key,
        value: ajaxViews[key],
        nestingLevel: $(ajaxViews[key].selector).parents(
          '[class*=js-view-dom-id-]',
        ).length,
      };
    });

    return ajaxViewsArray.sort((a, b) => b.nestingLevel - a.nestingLevel);
  };

  /*
   * Javascript object for a certain view.
   *
   * @constructor
   *
   * @param {object} settings
   *   Settings object for the ajax view.
   * @param {string} settings.view_dom_id
   *   The DOM id of the view.
   */
  Drupal.views.ajaxView = function (settings) {
    const { selector } = settings;
    this.$view = $(selector);
    this.$exposed_form = $(
      `form#views-exposed-form-${settings.view_name.replace(
        /_/g,
        '-',
      )}-${settings.view_display_id.replace(/_/g, '-')}`,
    );

    // Retrieve the path to use for views' ajax.
    let ajaxPath = drupalSettings.views.ajax_path;

    // If there are multiple views this might've ended up showing up multiple
    // times.
    if (ajaxPath.constructor.toString().includes('Array')) {
      ajaxPath = ajaxPath[0];
    }

    // Check if there are any GET parameters to send to views.
    let queryString = window.location.search || '';
    if (queryString !== '') {
      // Remove the question mark and Drupal path component if any.
      queryString = queryString.slice(1);

      // Remove current exposed filters.
      const params = decodeURI(queryString)
        .split('&')
        .filter((param) => {
          const [name, value] = param.split('=');
          return (
            this.$exposed_form.find(`input[name="${name}"]`).length === 0 &&
            /*
            Submitting filters should reset paging and sorting
            because that is what happens without AJAX.
             */
            !['page', 'reset', 'sort', 'order', 'q', 'render'].includes(name)
          );
        });
      queryString = encodeURI(params.join('&'));
      // If there is a '?' in ajaxPath, clean URL are on and & should be
      // used to add parameters.
      if (queryString !== '') {
        queryString = (ajaxPath.includes('?') ? '&' : '?') + queryString;
      }
    }

    this.element_settings = {
      url: ajaxPath + queryString,
      submit: settings,
      httpMethod: 'GET',
      setClick: true,
      event: 'click',
      selector,
      progress: { type: 'fullscreen' },
    };

    this.settings = settings;

    // Add the ajax to exposed forms.
    once('exposed-form', this.$exposed_form).forEach(
      this.attachExposedFormAjax.bind(this),
    );

    // Add the ajax to pagers.
    this.$pager_links = this.$view.find(
      'ul.js-pager__items > li > a, th.views-field a, .attachment .views-summary a',
    );
    once('ajax-pager', this.$pager_links).forEach((linkElement) => {
      this.attachPagerLinkAjax.call(this, linkElement);
    });

    // Add a trigger to update this view specifically. In order to trigger a
    // refresh use the following code.
    //
    // @code
    // $('.view-name').trigger('RefreshView');
    // @endcode
    const selfSettings = $.extend({}, this.element_settings, {
      event: 'RefreshView',
      base: this.selector,
      httpMethod: 'GET',
      element: this.$view.get(0),
    });
    // Remove unwanted parameter.
    delete selfSettings.selector;
    this.refreshViewAjax = Drupal.ajax(selfSettings);
  };

  /**
   * Attach the ajax behavior to exposed form fields.
   *
   * @param {HTMLElement} form
   *   The form element.
   */
  Drupal.views.ajaxView.prototype.attachExposedFormAjax = function (form) {
    this.exposedFormAjax = [];
    // Exclude the reset buttons so no AJAX behaviors are bound. Many things
    // break during the form reset phase if using AJAX.
    $('input[type=submit], button[type=submit], input[type=image]', form)
      .not('[data-drupal-selector=edit-reset]')
      .each((index, element) => {
        const selfSettings = $.extend({}, this.element_settings, {
          base: $(element).attr('id'),
          element,
        });
        // Remove unwanted parameter.
        delete selfSettings.selector;
        this.exposedFormAjax[index] = Drupal.ajax(selfSettings);
      });
  };

  /**
   * Attach the ajax behavior to a singe link.
   *
   * @param {HTMLElement} linkElement
   *   The link element.
   */
  Drupal.views.ajaxView.prototype.attachPagerLinkAjax = function (linkElement) {
    const $link = $(linkElement);
    const viewData = {};
    const href = $link.attr('href');
    // Construct an object using the settings defaults and then overriding
    // with data specific to the link.
    $.extend(
      viewData,
      this.settings,
      Drupal.Views.parseQueryString(href),
      // Extract argument data from the URL.
      Drupal.Views.parseViewArgs(href, this.settings.view_base_path),
    );

    const selfSettings = $.extend({}, this.element_settings, {
      submit: viewData,
      base: false,
      element: linkElement,
      httpMethod: 'GET',
    });
    // Remove unwanted parameter.
    delete selfSettings.selector;
    this.pagerAjax = Drupal.ajax(selfSettings);
  };

  /**
   * Sets the browser URL ajax command.
   *
   * @param {Drupal.Ajax} [ajax]
   *   A {@link Drupal.ajax} object.
   * @param {object} response
   *   Ajax response.
   * @param {string} response.url
   *   URL to be set.
   */
  Drupal.AjaxCommands.prototype.setBrowserUrl = (ajax, response) => {
    // Do not change browser URL if we are in a dialog wrapper.
    if (ajax.element && !ajax.element.closest('.ui-dialog-content')) {
      window.history.replaceState(null, '', response.url);
    }
  };
})(jQuery, Drupal, drupalSettings);
