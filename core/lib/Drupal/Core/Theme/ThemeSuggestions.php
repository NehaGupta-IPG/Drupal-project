<?php

namespace Drupal\Core\Theme;

use Drupal\Core\Path\PathMatcherInterface;

/**
 * Builds suggestion list from the current path.
 */
class ThemeSuggestions {

  public function __construct(
    protected readonly PathMatcherInterface $pathMatcher,
  ) {}

  /**
   * Generate an array of suggestions from path arguments.
   *
   * This is typically called for adding to the suggestions in
   * hook_theme_suggestions_HOOK_alter() or adding to 'attributes' class key
   * variables from within preprocess functions, when wanting to base the
   * additional suggestions or classes on the path of the current page.
   *
   * @param array $args
   *   An array of path arguments.
   * @param string $base
   *   A string identifying the base 'thing' from which more specific suggestions
   *   are derived. For example, 'page' or 'html'.
   * @param string $delimiter
   *   The string used to delimit increasingly specific information. The default
   *   of '__' is appropriate for theme hook suggestions. '-' is appropriate for
   *   extra classes.
   *
   * @return array
   *   An array of suggestions, suitable for adding to
   *   hook_theme_suggestions_HOOK_alter() or to $variables['attributes']['class']
   *   if the suggestions represent extra CSS classes.
   */
  public function get($args, $base, $delimiter = '__'): array {
    // Build a list of suggested theme hooks in order of
    // specificity. One suggestion is made for every element of the current path,
    // though numeric elements are not carried to subsequent suggestions. For
    // example, for $base='page', http://www.example.com/node/1/edit would result
    // in the following suggestions:
    //
    // page__node
    // page__node__%
    // page__node__1
    // page__node__edit

    $suggestions = [];
    $prefix = $base;
    foreach ($args as $arg) {
      // Remove slashes or null per SA-CORE-2009-003 and change - (hyphen) to _
      // (underscore).
      //
      // When we discover templates in @see TemplateFinder::scan(),
      // hyphens (-) are converted to underscores (_) before the theme hook
      // is registered. We do this because the hyphens used for delimiters
      // in hook suggestions cannot be used in the function names of the
      // associated preprocess functions. Any page templates designed to be used
      // on paths that contain a hyphen are also registered with these hyphens
      // converted to underscores so here we must convert any hyphens in path
      // arguments to underscores here before fetching theme hook suggestions
      // to ensure the templates are appropriately recognized.
      $arg = str_replace(["/", "\\", "\0", '-'], ['', '', '', '_'], $arg);
      // The percent acts as a wildcard for numeric arguments since
      // asterisks are not valid filename characters on many filesystems.
      if (is_numeric($arg)) {
        $suggestions[] = $prefix . $delimiter . '%';
      }
      $suggestions[] = $prefix . $delimiter . $arg;
      if (!is_numeric($arg)) {
        $prefix .= $delimiter . $arg;
      }
    }
    if ($this->pathMatcher->isFrontPage()) {
      // Front templates should be based on root only, not prefixed arguments.
      $suggestions[] = $base . $delimiter . 'front';
    }

    return $suggestions;
  }

}
