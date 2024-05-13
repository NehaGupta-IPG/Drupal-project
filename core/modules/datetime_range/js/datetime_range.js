/**
 * @file
 * Provides all day feature for datetime range.
 */

(function ($, Drupal) {

  /**
   * Hide time input for all day datestime elements.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attach behavior for hiding time input on all day datetime elements.
   */
  Drupal.behaviors.datetimeRangeAllDay = {
    attach: function (context, settings) {
      var all_day_field = $('[name$="[all_day]"]');

      all_day_field.change(function () {
        changeCheckbox(this);
      });
      changeCheckbox(all_day_field.get(0));

      function changeCheckbox(item){
        var $this = $(item);
        var name_attr = $this.attr('name');
        var key = '[all_day]';
        var start_time_key = '[value][time]';
        var start_time_value = '00:00:00';
        var end_time_key = '[end_value][time]';
        var end_time_value = '00:00:00';
        var start_time_name_attr = name_attr.replace(key, start_time_key);
        var end_time_name_attr = name_attr.replace(key, end_time_key);
        var start_time_field = $('[name="' + start_time_name_attr + '"]');
        var end_time_field = $('[name="' + end_time_name_attr + '"]');

        if ($this.is(':checked')) {
          start_time_field.val(start_time_value);
          start_time_field.hide();
          end_time_field.val(end_time_value);
          end_time_field.hide();
        }
        else {
          start_time_field.show();
          end_time_field.show();
        }
      }
    },
  };
})(jQuery, Drupal);
