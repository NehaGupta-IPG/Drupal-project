<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests;

use WebDriver\WebDriver;

/**
 * A WebDriver client that preserves capabilities the library would drop.
 *
 * \WebDriver\WebDriver::session() silently drops standard W3C capabilities
 * missing from its hardcoded allowlist. This subclass injects them back into
 * the session request.
 *
 * @todo Fix in https://github.com/lullabot/php-webdriver
 */
class DrupalWebDriver extends WebDriver {

  /**
   * Constructs a DrupalWebDriver object.
   *
   * @param string $url
   *   The URL of the WebDriver server.
   * @param array $extraCapabilities
   *   Capabilities to merge into every new session request. These take
   *   precedence over the parent's values.
   */
  public function __construct($url = 'http://localhost:4444/', protected array $extraCapabilities = []) {
    parent::__construct($url);
  }

  /**
   * {@inheritdoc}
   */
  protected function curl($requestMethod, $command, $parameters = NULL, $extraOptions = []): array {
    // Inject extra capabilities into the W3C new session request. Uses
    // firstMatch to avoid duplicating a capability already in alwaysMatch,
    // which would make the request invalid.
    if ($requestMethod === 'POST' && $command === '/session' && is_array($parameters['capabilities']['firstMatch'] ?? NULL)) {
      foreach ($parameters['capabilities']['firstMatch'] as $index => $capabilities) {
        $parameters['capabilities']['firstMatch'][$index] = $this->extraCapabilities + $capabilities;
      }
    }

    return parent::curl($requestMethod, $command, $parameters, $extraOptions);
  }

}
