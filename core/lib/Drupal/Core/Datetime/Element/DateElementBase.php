<?php

namespace Drupal\Core\Datetime\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides a base class for date elements.
 *
 * Date elements are a subset of render elements, representing elements for
 * HTML forms, which can be referenced in form arrays. See the
 *
 * @link theme_render Render API topic @endlink for an overview of render
 * arrays and render elements, and the @link form_api Form API topic @endlink
 * for an overview of forms and form arrays.
 *
 * This is a list of the properties that are available for all date elements.
 * Properties specific to a particular element are documented on that element's
 * class.
 *
 * List of properties that are used during the rendering and form processing of
 * date elements,
 * - #default_value: A DrupalDateTime object, adjusted to the proper local
 *   timezone. Converting a date stored in the database from UTC to the local
 *   zone and converting it back to UTC before storing it is not handled here.
 *   This element accepts a date as the default value, and then converts the
 *   user input strings back into a new date object on submission. No timezone
 *   adjustment is performed.
 * - #date_date_callbacks: (optional) Array of optional callbacks for the date
 *   element.
 * - #date_year_range: A description of the range of years to allow, like
 *   '1900:2050', '-3:+3' or '2000:+3', where the first value describes the
 *   earliest year and the second the latest year in the range. A year in either
 *   position means that specific year. A +/- value describes a dynamic value
 *   that is that many years earlier or later than the current year at the time
 *   the form is displayed. Used in HTML5 min/max date settings. Defaults to
 *   '1900:2050'.
 * - #date_increment: (optional) The interval (step) to use when incrementing or
 *   decrementing time, in seconds. For example, if this value is set to 30,
 *   time increases (or decreases) in steps of 30 seconds (00:00:00, 00:00:30,
 *   00:01:00, and so on.) If this value is a multiple of 60, the
 *   "seconds"-component will not be shown in the input. Used for HTML5 step
 *   values. Defaults to 1 to show every second.
 * - #date_timezone: (optional) The Time Zone Identifier (TZID) to use when
 *   displaying or interpreting dates, i.e: 'Asia/Kolkata'. Defaults to the
 *   value returned by date_default_timezone_get().
 *
 * @see \Drupal\Core\Render\Element\RenderElementBase()
 * @see \Drupal\Core\Datetime\Element\Datelist()
 * @see \Drupal\Core\Datetime\Element\Datetime()
 */
abstract class DateElementBase extends FormElementBase {

  /**
   * Specifies the start and end year to use as a date range.
   *
   * Handles a string like -3:+3 or 2001:2010 to describe a dynamic range of
   * minimum and maximum years to use in a date selector.
   *
   * Centers the range around the current year, if any, but expands it far
   * enough so it will pick up the year value in the field in case the value in
   * the field is outside the initial range.
   *
   * @param string $string
   *   A min and max year string like '-3:+1' or '2000:2010' or '2000:+3'.
   * @param object $date
   *   (optional) A date object to test as a default value. Defaults to NULL.
   *
   * @return array
   *   A numerically indexed array, containing the minimum and maximum year
   *   described by this pattern.
   */
  protected static function datetimeRangeYears($string, $date = NULL) {
    $datetime = new DrupalDateTime();
    $this_year = $datetime->format('Y');
    [$min_year, $max_year] = explode(':', $string);

    // Valid patterns would be -5:+5, +0:+1, 2008:2010, 1:9999.
    // Note that year 0 is 0 while current year is: +0.
    // Due to the +/- logic we can't support years BC (<0).
    $plus_pattern = '@[\+|\-][0-9]{1,4}@';
    $year_pattern = '@^[0-9]{1,4}@';
    if (!preg_match($year_pattern, $min_year, $matches)) {
      if (preg_match($plus_pattern, $min_year, $matches)) {
        $min_year = $this_year + $matches[0];
      }
      else {
        $min_year = $this_year;
      }
    }
    if (!preg_match($year_pattern, $max_year, $matches)) {
      if (preg_match($plus_pattern, $max_year, $matches)) {
        $max_year = $this_year + $matches[0];
      }
      else {
        $max_year = $this_year;
      }
    }
    // We expect the $min year to be less than the $max year. Some custom values
    // for -99:+99 might not obey that.
    if ($min_year > $max_year) {
      $temp = $max_year;
      $max_year = $min_year;
      $min_year = $temp;
    }
    // If there is a current value, stretch the range to include it.
    $value_year = $date instanceof DrupalDateTime ? $date->format('Y') : '';
    if (!empty($value_year)) {
      $min_year = min($value_year, $min_year);
      $max_year = max($value_year, $max_year);
    }
    return [$min_year, $max_year];
  }

  /**
   * Returns the most relevant title of a datetime element.
   *
   * Since datetime form elements often consist of combined date and time fields
   * the element title might not be located on the element itself but on the
   * parent container element.
   *
   * @param array $element
   *   The element being processed.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return string
   *   The title.
   */
  protected static function getElementTitle($element, $complete_form) {
    $title = '';
    if (!empty($element['#title'])) {
      $title = $element['#title'];
    }
    else {
      $parents = $element['#array_parents'];
      array_pop($parents);
      $parent_element = NestedArray::getValue($complete_form, $parents);
      if (!empty($parent_element['#title'])) {
        $title = $parent_element['#title'];
      }
    }

    return $title;
  }

}
