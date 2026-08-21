<?php

namespace Drupal\Core\Test\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilder;

/**
 * Replacement for the form builder in Kernel tests that need forms.
 *
 * This is only a workaround for https://www.drupal.org/node/2505339.
 *
 * @todo Remove this when https://www.drupal.org/node/2505339 is fixed.
 */
class KernelTestFormBuilder extends FormBuilder {

  /**
   * {@inheritdoc}
   */
  protected function buildFormAction() {
    // Use <current> instead of the main request.
    $request = $this->requestStack->getCurrentRequest();
    $request_uri = $request->getRequestUri();

    // Prevent cross site requests via the Form API by using an absolute URL
    // when the request uri starts with multiple slashes.
    if (str_starts_with($request_uri, '//')) {
      $request_uri = $request->getUri();
    }

    // @todo Remove this parsing once these are removed from the request in
    //   https://www.drupal.org/node/2504709.
    $parsed = UrlHelper::parse($request_uri);
    unset($parsed['query'][static::AJAX_FORM_REQUEST], $parsed['query'][MainContentViewSubscriber::WRAPPER_FORMAT]);
    $action = $parsed['path'] . ($parsed['query'] ? ('?' . UrlHelper::buildQuery($parsed['query'])) : '');
    return UrlHelper::filterBadProtocol($action);
  }

}
