<?php

namespace Drupal\Component\Utility;

use Dom\HTMLDocument;

/**
 * Provides helpers for PHP's native HTML5 parser and DOM API.
 *
 * Unlike \Drupal\Component\Utility\Html, this class uses \Dom\HTMLDocument
 * rather than \DOMDocument.
 * The two APIs differ in their handling of malformed HTML and serialization.
 * These helpers do not sanitize HTML or make untrusted markup safe to render.
 *
 * @ingroup utility
 */
class Html5 {

  /**
   * Parses an HTML snippet using PHP's native HTML5 parser.
   *
   * The snippet is loaded inside the body of a full document using UTF-8.
   * Invalid markup is corrected according to the native parser's HTML5 rules.
   * Elements retain their HTML namespace. Use \Dom\XPath to query the document
   * and self::serialize() to convert its body back to an HTML snippet.
   *
   * @param string|\Stringable $html
   *   The partial HTML snippet to load.
   *
   * @return \Dom\HTMLDocument
   *   The document containing the HTML snippet.
   */
  public static function load(string|\Stringable $html): HTMLDocument {
    return HTMLDocument::createFromString('<body>' . $html, LIBXML_NOERROR, 'UTF-8');
  }

  /**
   * Serializes a native HTML5 document's body as an HTML snippet.
   *
   * Uses PHP's native serialization rules, with newlines normalized to LF.
   * Unlike \Drupal\Component\Utility\Html::serialize(), this can emit literal
   * angle brackets in attribute values, which
   * \Drupal\Component\Utility\Xss::filter() does not support. Do not use this
   * method as a drop-in replacement in existing XSS filtering workflows.
   * Serialization does not sanitize HTML.
   *
   * @param \Dom\HTMLDocument $document
   *   The document to serialize. Only the body's children are included.
   *
   * @return string
   *   The HTML snippet.
   */
  public static function serialize(HTMLDocument $document): string {
    return str_replace(["\r\n", "\r"], "\n", $document->body?->innerHTML ?? '');
  }

}
