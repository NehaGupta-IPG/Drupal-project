/**
 * @file
 * Defines form element theme functions.
 */

((Drupal) => {
  /**
   * Theme function for a label element.
   *
   * @param {object} options
   *   Options object.
   * @param {string} [options.label]
   *   The label text.
   * @param {object} [options.properties]
   *   An object with the following properties:
   * @param {string} [options.properties.className]
   *   The class attribute of the label.
   * @param {string} [options.properties.labelFor]
   *   The for attribute of the label.
   *
   * @return {string}
   *   The HTML markup for the label element.
   */
  Drupal.theme.formLabel = ({ label, properties = {} }) => {
    const { className, labelFor } = properties;
    const attributes = [
      className ? `class="${className}"` : '',
      labelFor ? `for="${labelFor}"` : '',
    ].filter((attribute) => attribute !== '');

    return `<label ${attributes.join(' ')}>${label}</label>`;
  };
})(Drupal);
