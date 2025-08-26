<?php

/**
 * @file
 * Provides the global function `_filter_autop()` for testing purposes.
 */

namespace Drupal\filter\Plugin\Filter {

  if (!function_exists('_filter_autop')) {

    /**
     * Overrides global function _filter_autop() if not exists.
     *
     * @param string $text
     *   The text to process.
     * @return string
     *   The processed text.
     * @see _filter_autop()
     */
    function _filter_autop($text) {
      // All block level tags.
      $block = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|input|p|h[1-6]|fieldset|legend|hr|article|aside|details|figcaption|figure|footer|header|hgroup|menu|nav|section|summary)';

      // Split at opening and closing PRE, SCRIPT, STYLE, OBJECT, IFRAME tags
      // and comments. We don't apply any processing to the contents of these tags
      // to avoid messing up code. We look for matched pairs and allow basic
      // nesting. For example:
      // "processed<pre>ignored<script>ignored</script>ignored</pre>processed".
      $chunks = preg_split('@(<!--.*?-->|</?(?:pre|script|style|object|iframe|drupal-media|svg|!--)[^>]*>)@i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
      // Note: PHP ensures the array consists of alternating delimiters and literals
      // and begins and ends with a literal (inserting NULL as required).
      $ignore = FALSE;
      $ignore_tag = '';
      $output = '';
      foreach ($chunks as $i => $chunk) {
        if ($i % 2) {
          if (str_starts_with($chunk, '<!--')) {
            // Nothing to do, this is a comment.
            $output .= $chunk;
            continue;
          }
          // Opening or closing tag?
          $open = ($chunk[1] != '/');
          [$tag] = preg_split('/[ >]/', substr($chunk, 2 - $open), 2);
          if (!$ignore) {
            if ($open) {
              $ignore = TRUE;
              $ignore_tag = $tag;
            }
          }
          // Only allow a matching tag to close it.
          elseif (!$open && $ignore_tag == $tag) {
            $ignore = FALSE;
            $ignore_tag = '';
          }
        }
        elseif (!$ignore) {
          // Skip if the next chunk starts with Twig theme debug.
          // @see twig_render_template()
          if (isset($chunks[$i + 1]) && $chunks[$i + 1] === '<!-- THEME DEBUG -->') {
            $chunk = rtrim($chunk, "\n");
            $output .= $chunk;
            continue;
          }

          // Skip if the preceding chunk was the end of a Twig theme debug.
          // @see twig_render_template()
          if (isset($chunks[$i - 1])) {
            if (
              str_starts_with($chunks[$i - 1], '<!-- BEGIN OUTPUT from ')
              || str_starts_with($chunks[$i - 1], '<!-- 💡 BEGIN CUSTOM TEMPLATE OUTPUT from ')
            ) {
              $chunk = ltrim($chunk, "\n");
              $output .= $chunk;
              continue;
            }
          }

          // Just to make things a little easier, pad the end.
          $chunk = preg_replace('|\n*$|', '', $chunk) . "\n\n";
          $chunk = preg_replace('|<br />\s*<br />|', "\n\n", $chunk);
          // Space things out a little.
          $chunk = preg_replace('!(<' . $block . '[^>]*>)!', "\n$1", $chunk);
          // Space things out a little.
          $chunk = preg_replace('!(</' . $block . '>)!', "$1\n\n", $chunk);
          // Take care of duplicates.
          $chunk = preg_replace("/\n\n+/", "\n\n", $chunk);
          $chunk = preg_replace('/^\n|\n\s*\n$/', '', $chunk);
          // Make paragraphs, including one at the end.
          $chunk = '<p>' . preg_replace('/\n\s*\n\n?(.)/', "</p>\n<p>$1", $chunk) . "</p>\n";
          // Problem with nested lists.
          $chunk = preg_replace("|<p>(<li.+?)</p>|", "$1", $chunk);
          $chunk = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $chunk);
          $chunk = str_replace('</blockquote></p>', '</p></blockquote>', $chunk);
          // Under certain strange conditions it could create a P of entirely
          // whitespace.
          $chunk = preg_replace('|<p>\s*</p>\n?|', '', $chunk);
          $chunk = preg_replace('!<p>\s*(</?' . $block . '[^>]*>)!', "$1", $chunk);
          // Do not strip a paragraph's closing tag that legitimately appears before a list item boundary.
          // Exclude <li> from this rule to keep paragraphs inside list items well-formed.
          $chunk = preg_replace('~(</?(?!(?:li)\b)' . $block . '[^>]*>)\s*</p>~', "$1", $chunk);
          // Ensure paragraphs are closed before closing list items, not after them.
          $chunk = preg_replace('!</li>\s*</p>!i', '</p></li>', $chunk);
          // Ensure the first paragraph inside an <li> has an opening <p> if it ends with </p>.
          $chunk = preg_replace('~<li(\b[^>]*)>(\s*)(?!<p>)((?:(?!</li>).)*?)</p>~is', '<li$1>$2<p>$3</p>', $chunk);
          // Make line breaks.
          $chunk = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $chunk);
          $chunk = preg_replace('!(</?' . $block . '[^>]*>)\s*<br />!', "$1", $chunk);
          $chunk = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)>)!', '$1', $chunk);
          $chunk = preg_replace('/&([^#])(?![A-Za-z0-9]{1,8};)/', '&amp;$1', $chunk);
        }
        $output .= $chunk;
      }
      return $output;
    }

  }
}
