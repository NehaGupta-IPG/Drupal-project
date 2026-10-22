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
  public function getQueryParameters() {
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      return UrlHelper::filterQueryParameters(
        $request->query->all(), ['page']
      );
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function findPage($pager_id = 0) {
    $pages = $this->getPagerQuery();
    // Ignore anything that is not a non-negative integer, including numbers
    // too large to be represented as one.
    $page = filter_var($pages[$pager_id] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    return $page === FALSE ? 0 : $page;
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
