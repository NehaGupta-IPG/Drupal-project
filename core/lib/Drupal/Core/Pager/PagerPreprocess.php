<?php

namespace Drupal\Core\Pager;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Htmx\Htmx;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Pager theme preprocess.
 *
 * @internal
 */
class PagerPreprocess {

  /**
   * Indicates if the current request is an Htmx request.
   *
   * @var bool
   */
  protected bool $isHtmxRequest;

  /**
   * The pager element ID.
   *
   * @var int|null
   */
  protected ?int $element;

  /**
   * The current query parameters.
   *
   * @var array|null
   */
  protected ?array $parameters;

  /**
   * Default link attributes.
   *
   * @var array
   */
  protected array $default_link_attributes = [];

  public function __construct(
    protected PagerManagerInterface $pagerManager,
    protected RequestStack $requestStack,
  ) {
    if ($this->requestStack->getCurrentRequest()?->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_modal') {
      $this->default_link_attributes = [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-accepts' => 'application/json',
      ];
    }
  }

  /**
   * Prepares variables for pager templates.
   *
   * Default template: pager.html.twig.
   *
   * Menu callbacks that display paged query results should use #type => pager
   * to retrieve a pager control so that users can view other results. Format a
   * list of nearby pages with additional query results.
   *
   * @param array $variables
   *   An associative array containing:
   *   - pager: A render element containing:
   *     - #tags: An array of labels for the controls in the pager.
   *     - #element: An optional integer to distinguish between multiple pagers
   *       on one page.
   *     - #pagination_heading_level: An optional heading level for the pager.
   *     - #parameters: An associative array of query string parameters to
   *       append to the pager links.
   *     - #route_parameters: An associative array of the route parameters.
   *     - #quantity: The number of pages in the list.
   */
  public function preprocessPager(array &$variables): void {
    $element = $variables['pager']['#element'];
    $this->element = $element;
    $this->isHtmxRequest = $this->requestStack->getCurrentRequest()?->headers->has('HX-Request') || !empty($variables["pager"]["#htmx"]);
    $this->parameters = $variables['pager']['#parameters'];
    $quantity = empty($variables['pager']['#quantity']) ? 0 : $variables['pager']['#quantity'];
    $route_name = $variables['pager']['#route_name'];
    $route_parameters = $variables['pager']['#route_parameters'] ?? [];

    $pager = $this->pagerManager->getPager($element);

    // Nothing to do if there is no pager.
    if (!isset($pager)) {
      return;
    }

    $pager_max = $pager->getTotalPages();

    // Nothing to do if there is only one page.
    if ($pager_max <= 1) {
      return;
    }

    $tags = $variables['pager']['#tags'];

    // Calculate various markers within this pager piece:
    // Middle is used to "center" pages around the current page.
    $pager_middle = ceil($quantity / 2);
    $current_page = $pager->getCurrentPage();
    // The current pager is the page we are currently paged to.
    $pager_current = $current_page + 1;
    // The first pager is the first page listed by this pager piece (re
    // quantity).
    $pager_first = $pager_current - $pager_middle + 1;
    // The last is the last page listed by this pager piece (re quantity).
    $pager_last = $pager_current + $quantity - $pager_middle;
    // End of marker calculations.

    // Prepare for generation loop.
    $i = $pager_first;
    if ($pager_last > $pager_max) {
      // Adjust "center" if at end of query.
      $i = $i + ($pager_max - $pager_last);
      $pager_last = $pager_max;
    }
    if ($i <= 0) {
      // Adjust "center" if at start of query.
      $pager_last = $pager_last + (1 - $i);
      $i = 1;
    }
    // End of generation loop preparation.

    // Create the "first" and "previous" links if we are not on the first page.
    $items = [];
    if ($current_page > 0) {
      $items['first'] = $this->buildPageLink($route_name, $route_parameters, 0, $tags[0] ?? NULL);
      $items['previous'] = $this->buildPageLink($route_name, $route_parameters, $current_page - 1, $tags[1] ?? NULL);
    }

    // Add an ellipsis if there are further previous pages.
    if ($i > 1) {
      $variables['ellipses']['previous'] = TRUE;
    }
    // Now generate the actual pager piece.
    for (; $i <= $pager_last && $i <= $pager_max; $i++) {
      $items['pages'][$i] = $this->buildPageLink($route_name, $route_parameters, $i - 1);
      if ($i == $pager_current) {
        $variables['current'] = $i;
        $items['pages'][$i]['attributes']->setAttribute('aria-current', 'page');
      }
    }
    // Add an ellipsis if there are further next pages.
    if ($i < $pager_max + 1) {
      $variables['ellipses']['next'] = TRUE;
    }

    // Create the "next" and "last" links if we are not on the last page.
    if ($current_page < ($pager_max - 1)) {
      $items['next'] = $this->buildPageLink($route_name, $route_parameters, $current_page + 1, $tags[3] ?? NULL);
      $items['last'] = $this->buildPageLink($route_name, $route_parameters, $pager_max - 1, $tags[4] ?? NULL);
    }

    $variables['items'] = $items;
    $variables['heading_id'] = Html::getUniqueId('pagination-heading');
    $variables['pagination_heading_level'] = $variables['pager']['#pagination_heading_level'] ?? 'h4';
    if (!preg_match('/^h[1-6]$/', $variables['pagination_heading_level'])) {
      $variables['pagination_heading_level'] = 'h4';
    }

    // The rendered link needs to play well with any other query parameter used
    // on the page, like exposed filters, so for the cacheability all query
    // parameters matter.
    $variables['#cache']['contexts'][] = 'url.query_args';
  }

  /**
   * Builds a page link array, processing it for Htmx if needed.
   *
   * @param string $route_name
   *   The route name.
   * @param array $route_parameters
   *   The route parameters.
   * @param int $index
   *   The page index.
   * @param string|null $text
   *   (optional) The link text.
   *
   * @return array
   *   A renderable link array.
   */
  protected function buildPageLink(string $route_name, array $route_parameters, int $index, string $text = NULL): array {
    $link = [
      'attributes' => new Attribute($this->default_link_attributes),
      'href' => Url::fromRoute(
        $route_name,
        $route_parameters,
        [
          'query' => $this->pagerManager->getUpdatedParameters($this->parameters, $this->element, $index),
        ]
      ),
    ];
    if ($this->isHtmxRequest) {
      $this->processHtmxLink($link);
    }
    if (!empty($text)) {
      $link['text'] = $text;
    }
    $link['href'] = $link['href']->toString();
    return $link;
  }

  /**
   * Modifies a link item to work with Htmx.
   *
   * @param array $item
   *   A link item with 'href' and 'attributes' keys. The 'href' key
   */
  protected function processHtmxLink(array &$item): void {
    /** @var Url $url */
    $url = $item['href'];
    // Filter the parameters that break non-js navigation.
    $exclude = [
      'ajax_page_state',
      MainContentViewSubscriber::WRAPPER_FORMAT,
    ];
    $query = UrlHelper::filterQueryParameters($url->getOption('query'), $exclude);
    $url->setOption('query', $query);
    (new Htmx())
      ->get($url)
      // Only trigger the AJAX request, updating the necessary element
      // is done with an out-of-band swap.
      ->pushUrl($url)
      ->swap('none')
      ->applyTo($item, 'attributes');
    $item['href'] = $url;
  }
}
