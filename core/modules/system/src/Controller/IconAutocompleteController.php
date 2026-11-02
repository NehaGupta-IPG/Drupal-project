<?php

declare(strict_types=1);

namespace Drupal\system\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconSearch;
use Drupal\Core\Theme\Icon\IconSearchInterface;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Defines a route controller for icon autocomplete form elements.
 *
 * @internal
 *   This API is experimental.
 */
final class IconAutocompleteController extends ControllerBase {

  /**
   * Constructs an IconAutocompleteController object.
   *
   * @param \Drupal\Core\Theme\Icon\IconSearchInterface $iconSearch
   *   The icon search service.
   * @param \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface $iconPackManager
   *   The icon pack manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    private readonly IconSearchInterface $iconSearch,
    private readonly IconPackManagerInterface $iconPackManager,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * Autocomplete callback for icon selection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains the typed string.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   The matched icons as a JSON response.
   */
  #[Route('/system/icon-autocomplete', name: 'system.icon_autocomplete', requirements: ['_access' => 'TRUE'])]
  public function handleSearchIcons(Request $request): CacheableJsonResponse {
    $query = trim((string) $request->query->get('q', ''));

    if (empty($query)) {
      return new CacheableJsonResponse([]);
    }

    // Get allowed icon packs from query parameter.
    $allowed_icon_pack = [];
    if ($request->query->get('allowed_icon_pack')) {
      $allowed_icon_pack = explode('+', (string) $request->query->get('allowed_icon_pack', ''));
    }

    // Get max results from query parameter. This route is accessible to
    // anonymous users, so the value is clamped to a safe upper bound.
    $max_result = (int) $request->query->get('max_result', IconSearchInterface::SEARCH_RESULT);
    if ($max_result < 1) {
      $max_result = IconSearchInterface::SEARCH_RESULT;
    }
    $max_result = min($max_result, IconSearchInterface::SEARCH_RESULT_MAX);

    // Perform the search to get raw icon IDs.
    $icon_ids = $this->iconSearch->search(
      $query,
      $allowed_icon_pack,
      $max_result,
    );

    // Format results, collecting cache metadata from each icon render.
    $results = [];
    $cacheability = (new CacheableMetadata())
      ->addCacheTags(['icon_pack_plugin'])
      ->addCacheContexts(['url.query_args']);
    foreach ($icon_ids as $icon_full_id) {
      if ($formatted = $this->formatResultWithPreview($icon_full_id, $cacheability)) {
        $results[] = $formatted;
      }
    }

    $response = new CacheableJsonResponse($results);
    $response->addCacheableDependency($cacheability);
    return $response;
  }

  /**
   * Format a single result with inline SVG preview.
   *
   * @param string $icon_full_id
   *   The full icon ID (pack_id:icon_id).
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cacheability metadata accumulator; updated in place with render metadata.
   *
   * @return array|null
   *   The formatted result with value and label, or NULL if icon not found.
   */
  protected function formatResultWithPreview(string $icon_full_id, CacheableMetadata $cacheability): ?array {
    $icon = $this->iconPackManager->getIcon($icon_full_id);

    if (!$icon) {
      return NULL;
    }

    // Parse the icon ID to get pack and icon name.
    [$pack_id, $icon_id] = explode(':', $icon_full_id, 2);

    // Build the icon render array.
    $icon_render = [
      '#type' => 'icon',
      '#pack_id' => $pack_id,
      '#icon_id' => $icon_id,
      '#settings' => [
        'width' => IconSearch::ICON_PREVIEW_SIZE,
        'height' => IconSearch::ICON_PREVIEW_SIZE,
      ],
    ];

    $icon_svg = $this->renderer->renderInIsolation($icon_render);
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($icon_render));

    // Create a formatted label with inline icon + icon name + collection name.
    $label = new FormattableMarkup(
      '<div class="icon-autocomplete-result">' . $icon_svg . '<span class="icon-autocomplete-result__name">:icon_name</span><strong class="icon-autocomplete-pack">:pack_name</strong></div>',
      [
        ':icon_name' => $icon_id,
        ':pack_name' => $pack_id,
      ]
    );

    return [
      'value' => $icon_full_id,
      'label' => $label,
    ];
  }

  /**
   * Renders an icon as SVG.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object containing pack_id and icon_id parameters.
   *
   * @return \Drupal\Core\Cache\CacheableJsonResponse
   *   A JSON response containing the rendered SVG.
   */
  #[Route('/system/icon-render', name: 'system.icon_render', requirements: ['_access' => 'TRUE'])]
  public function renderIcon(Request $request): CacheableJsonResponse {
    $pack_id = $request->query->get('pack_id', '');
    $icon_id = $request->query->get('icon_id', '');

    if (empty($pack_id) || empty($icon_id)) {
      return new CacheableJsonResponse(['error' => 'Missing pack_id or icon_id'], 400);
    }

    $icon_full_id = $pack_id . ':' . $icon_id;
    $icon = $this->iconPackManager->getIcon($icon_full_id);

    if (!$icon) {
      return new CacheableJsonResponse(['error' => 'Icon not found'], 404);
    }

    // Build the icon render array.
    $build = [
      '#type' => 'icon',
      '#pack_id' => $pack_id,
      '#icon_id' => $icon_id,
    ];

    $context = new RenderContext();
    $svg = $this->renderer->executeInRenderContext($context, function () use (&$build) {
      return $this->renderer->render($build);
    });

    $response = new CacheableJsonResponse(['svg' => (string) $svg]);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    $response->addCacheableDependency((new CacheableMetadata())->addCacheContexts(['url.query_args']));
    if (!$context->isEmpty()) {
      $bubbleable = $context->pop();
      assert($bubbleable instanceof BubbleableMetadata);
      $response->addCacheableDependency($bubbleable);
    }
    return $response;
  }

}
