<?php

namespace Drupal\Core\Render;

/**
 * Defines an interface for processing attachments of responses that have them.
 *
 * @see \Drupal\Core\Ajax\AjaxResponse
 * @see \Drupal\Core\Ajax\AjaxResponseAttachmentsProcessor
 * @see \Drupal\Core\Render\HtmlResponse
 * @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor
 */
interface AttachmentsResponseProcessorInterface {

  /**
   * Processes the attachments of a response that has attachments.
   *
   * Libraries, JavaScript settings, feeds, HTML <head> tags, HTML <head> links,
   * HTTP headers, and the HTTP status code are attached to render arrays using
   * the #attached property. The #attached property is an associative array,
   * where the keys are the attachment types and the values are the attached
   * data. For example:
   *
   * @code
   * $build['#attached']['library'][] = [
   *   'library' => ['core/jquery']
   * ];
   * $build['#attached']['http_header'] = [
   *   ['Content-Type', 'application/rss+xml; charset=utf-8'],
   * ];
   * @endcode
   *
   * The available keys are:
   * - 'library': Asset library definitions. Each item is an array of
   *   library names, e.g. ['core/drupal'].
   * - 'drupalSettings': JavaScript settings passed to Drupal behaviours.
   *   Must be an associative array, e.g. ['myModule' => ['key' => 'value']].
   * - 'feed': RSS or Atom feed links. Each item is an array of two values:
   *   the URL and an associative array of attributes, e.g.
   *   ['url' => 'example.xml', 'attributes' => ['title' => 'Feed']].
   * - 'html_head': Items for the HTML <head> section. Each item is a numeric
   *   array [$data, $key], where $data is a render array for an 'html_tag'
   *   element and $key is a unique identifier.
   * - 'html_head_link': <link> tags to add to the HTML <head>. Each item is
   *   an associative array of attributes, e.g. ['rel' => 'canonical',
   *   'href' => 'https://example.com'].
   * - 'http_header': HTTP headers to add to the response. Each item is a
   *   numeric array [$header_name, $header_value]. Can also include
   *   ['status', $code] to override the response status.
   *
   * Placeholders need to be rendered first in order to have all attachments
   * available for processing. For an example, see
   * \Drupal\Core\Render\HtmlResponseAttachmentsProcessor::renderPlaceholders()
   *
   * @param \Drupal\Core\Render\AttachmentsInterface $response
   *   The response to process.
   *
   * @return \Drupal\Core\Render\AttachmentsInterface
   *   The processed response, with the attachments updated to reflect their
   *   final values.
   *
   * @throws \InvalidArgumentException
   *    Thrown when the $response parameter is not the expected response type.
   */
  public function processAttachments(AttachmentsInterface $response);

}
