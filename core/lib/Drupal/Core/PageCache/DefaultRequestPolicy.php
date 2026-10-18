<?php

namespace Drupal\Core\PageCache;

use Drupal\Core\PageCache\RequestPolicy\CommandLineOrUnsafeMethod;
use Drupal\Core\PageCache\RequestPolicy\NoSessionOpen;
use Drupal\Core\Session\SessionConfigurationInterface;

/**
 * The default page cache request policy.
 *
 * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Core\PageCache\ChainRequestPolicy with tagged services instead.
 *
 * @see https://www.drupal.org/node/3579668
 */
class DefaultRequestPolicy extends ChainRequestPolicy {

  /**
   * Constructs the default page cache request policy.
   *
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   */
  public function __construct(SessionConfigurationInterface $session_configuration) {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\Core\PageCache\ChainRequestPolicy with tagged services instead. See https://www.drupal.org/node/3579668', E_USER_DEPRECATED);
    parent::__construct(new \ArrayIterator([
      new CommandLineOrUnsafeMethod(),
      new NoSessionOpen($session_configuration),
    ]));
  }

}
