/**
 * @file
 * Provides a test row handler for a display group.
 */

(({ fieldUIDisplayOverview }) => {
  fieldUIDisplayOverview.group = function (row, data) {
    this.row = row;
    this.name = data.name;
    this.region = data.region;
    this.tableDrag = data.tableDrag;
  };

  fieldUIDisplayOverview.group.prototype = {
    regionChange(region) {
      this.region = region;
      return {};
    },
  };
})(Drupal);
