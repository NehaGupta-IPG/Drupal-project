<?php

namespace Drupal\Core\Ajax;

use Drupal\Component\Utility\UrlHelper;

/**
 * Defines an AJAX command to set the window.location, loading that URL.
 *
 * @ingroup ajax
 */
class RedirectCommand implements CommandInterface {

  /**
   * The URL that will be loaded into window.location.
   *
   * @var string
   */
  protected $url;

  /**
   * Constructs an RedirectCommand object.
   *
   * @param string $url
   *   The URL that will be loaded into window.location. This should be a full
   *   URL.
   * @param bool $trusted
   *   (optional) Set to TRUE only when the URL has already been verified as
   *   safe to redirect to. Defaults to FALSE.
   *
   * @throws \InvalidArgumentException
   *   Thrown when $url is an external URL, $trusted is FALSE, and $url is not
   *   local to the current site.
   */
  public function __construct($url, bool $trusted = FALSE) {
    if (!$trusted && UrlHelper::isExternal($url)) {
      $base_url = \Drupal::service('router.request_context')->getCompleteBaseUrl();
      if (!UrlHelper::externalIsLocal($url, $base_url)) {
        throw new \InvalidArgumentException(sprintf('It is not safe to redirect to %s. Pass TRUE as the $trusted argument only if the URL has already been verified as safe.', $url));
      }
    }
    $this->url = $url;
  }

  /**
   * Implements \Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {
    return [
      'command' => 'redirect',
      'url' => $this->url,
    ];
  }

}
