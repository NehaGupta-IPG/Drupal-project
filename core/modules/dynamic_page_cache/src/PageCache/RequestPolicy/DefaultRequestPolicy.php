<?php

namespace Drupal\dynamic_page_cache\PageCache\RequestPolicy;

use Drupal\Core\PageCache\ChainRequestPolicy;

/**
 * The default Dynamic Page Cache request policy.
 *
 * @deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use
 *   \Drupal\Core\PageCache\ChainRequestPolicy with tagged services instead.
 *
 * @see https://www.drupal.org/node/3579668
 */
class DefaultRequestPolicy extends ChainRequestPolicy {

  /**
   * Constructs the default Dynamic Page Cache request policy.
   */
  public function __construct() {
    @trigger_error(__CLASS__ . ' is deprecated in drupal:11.5.0 and is removed from drupal:13.0.0. Use \Drupal\Core\PageCache\ChainRequestPolicy with tagged services instead. See https://www.drupal.org/node/3579668', E_USER_DEPRECATED);
    parent::__construct(NULL);
  }

}
