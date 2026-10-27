/**
 * @file
 * Provides all day feature for datetime range.
 */

(function (Drupal, once) {

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
      Object.keys(settings.datetime_range || {}).forEach((fieldname) => {
        once('datetime-range-all-day', '[name^="' + fieldname + '["][name$="[all_day]"]', context).forEach(function(checkbox) {
          const handler = typeof Drupal.datetimeRangeAllDay[settings.datetime_range[fieldname]] === 'function'
            ? Drupal.datetimeRangeAllDay[settings.datetime_range[fieldname]]
            : () => {};
          checkbox.addEventListener('change', handler);
          handler.apply(checkbox);
        });
      });
    }
  };

  Drupal.datetimeRangeAllDay = {
    daterange_default: function () {
      const name_attr = this.getAttribute('name');
      const key = '[all_day]';
      const start_time_key = '[value][time]';
      const start_time_value = '00:00:00';
      const end_time_key = '[end_value][time]';
      const end_time_value = '00:00:00';
      const start_time_name_attr = name_attr.replace(key, start_time_key);
      const end_time_name_attr = name_attr.replace(key, end_time_key);
      const start_time_field = document.querySelector('[name="' + start_time_name_attr + '"]');
      const end_time_field = document.querySelector('[name="' + end_time_name_attr + '"]');

      if (this.checked) {
        start_time_field.value = start_time_value;
        start_time_field.style.display = 'none';
        end_time_field.value = end_time_value;
        end_time_field.style.display = 'none';
      }
      else {
        start_time_field.style.display = '';
        end_time_field.style.display = '';
      }

    },
    daterange_datelist: function () {
      const name_attr = this.getAttribute('name');
      const key = '[all_day]';

      const start_hour_key = '[value][hour]';
      const start_minute_key = '[value][minute]';
      const start_hour_value = '0';
      const start_minute_value = '0';

      const start_hour_name_attr = name_attr.replace(key, start_hour_key);
      const start_minute_name_attr = name_attr.replace(key, start_minute_key);
      const start_hour_field = document.querySelector('[name="' + start_hour_name_attr + '"]');
      const start_minute_field = document.querySelector('[name="' + start_minute_name_attr + '"]');

      const end_hour_key = '[end_value][hour]';
      const end_minute_key = '[end_value][minute]';
      const end_hour_value = '0';
      const end_minute_value = '0';

      const end_hour_name_attr = name_attr.replace(key, end_hour_key);
      const end_minute_name_attr = name_attr.replace(key, end_minute_key);
      const end_hour_field = document.querySelector('[name="' + end_hour_name_attr + '"]');
      const end_minute_field = document.querySelector('[name="' + end_minute_name_attr + '"]');

      if (this.checked) {
        start_hour_field.value = start_hour_value;
        start_minute_field.value = start_minute_value;
        start_hour_field.parentNode.style.display = 'none';
        start_minute_field.parentNode.style.display = 'none';

        end_hour_field.value = end_hour_value;
        end_minute_field.value = end_minute_value;
        end_hour_field.parentNode.style.display = 'none';
        end_minute_field.parentNode.style.display = 'none';
      }
      else {
        start_hour_field.parentNode.style.display = '';
        start_minute_field.parentNode.style.display = '';

        end_hour_field.parentNode.style.display = '';
        end_minute_field.parentNode.style.display = '';
      }
    }
  };
})(Drupal, once);
