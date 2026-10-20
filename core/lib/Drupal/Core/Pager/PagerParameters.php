<?php

namespace Drupal\Core\Pager;

use Drupal\Component\Utility\UrlHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides pager information contained within the current request.
 *
 * @see \Drupal\Core\Pager\PagerManagerInterface
 */
class PagerParameters implements PagerParametersInterface {

  /**
   * The HTTP request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Construct a PagerManager object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $stack
   *   The current HTTP request stack.
   */
  public function __construct(RequestStack $stack) {
    $this->requestStack = $stack;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueryParameters($context = []) {
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      $parameters = \Drupal::request()->query->all();

      if (isset($context['view'])) {
        /** @var \Drupal\views\Plugin\views\pager\PagerPluginBase $pager */
        $pager = $context['view']->display_handler->getPlugin('pager');
        if ($pager && $pager->usesStrictQueryArgs()) {
          // Filter out $parameters based on
          // the views url.query_args:* cache contexts.
          $pager->filterQueryArgs($parameters);
        }
      }

      return UrlHelper::filterQueryParameters($parameters, ['page']);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function findPage($pager_id = 0) {
    $pages = $this->getPagerQuery();
    return (int) ($pages[$pager_id] ?? 0);
  }

  /**
   * {@inheritdoc}
   */
  public function getPagerQuery() {
    $query = $this->getPagerParameter();
    return !empty($query) ? explode(',', $query) : [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPagerParameter() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      return $request->query->get('page', '');
    }
    return '';
  }

}
