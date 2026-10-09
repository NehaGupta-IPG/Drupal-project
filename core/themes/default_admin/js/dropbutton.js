((Drupal, once) => {
  /**
   * Normalizes the markup of the dropbutton. Some dropbuttons do not have the nested list heirarchy that indicates a dropdown. An example is the "View <DISPLAY>" dropbutton within Views UI. This fixes this.
   * @param {HTMLElement} el - The dropbutton wrapper element.
   */
  function init(el) {
    if (el.querySelectorAll('.dropbutton--multiple > .secondary-action:not(:has(ul.dropbutton__items))').length) {
      const dropdownItems = el.querySelectorAll('.dropbutton-toggle ~ li.dropbutton__item');
      const toggleButtonItem = el.querySelector('li.dropbutton-toggle');

      const dropdownEl = document.createElement('li');
      dropdownEl.classList.add('dropbutton__item', 'dropbutton-action', 'secondary-action');
      dropdownEl.innerHTML = `<ul class="dropbutton__items"></ul>`;
      const dropdown = dropdownEl.querySelector('ul.dropbutton__items');

      dropdownItems.forEach(el => {
        el.querySelector('.button, .button--primary')?.classList.remove('button', 'button--primary');
        dropdown.append(el);
      });

      toggleButtonItem?.after(dropdownEl);
    }
  }

  /**
   * Normalize dropbutton markup.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Normalizes the dropbutton markup.
   */
  Drupal.behaviors.dropbuttonNormalize = {
    attach(context) {
      once('admin-dropbutton-normalize', '.dropbutton-wrapper', context).forEach(init);
    },
  };

  /**
   * Overrides the dropbutton toggle markup.
   *
   * We have to keep the 'dropbutton-toggle' CSS class because the dropbutton JS
   * operates with that one.
   *
   * @param {object} options
   *   Options object.
   * @param {string} [options.title]
   *   The button text.
   *
   * @return {string}
   *   A string representing a DOM fragment.
   */
  Drupal.theme.dropbuttonToggle = (options) =>
    `<li class="dropbutton-toggle"><button type="button" class="dropbutton__toggle"><span class="visually-hidden">${options.title}</span></button></li>`;
})(Drupal, once);