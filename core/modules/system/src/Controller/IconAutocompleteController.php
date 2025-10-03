<?php

declare(strict_types=1);

namespace Drupal\system\Controller;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Icon\IconSearch;
use Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a route controller for icon autocomplete form elements.
 *
 * @internal
 *   This API is experimental.
 */
class IconAutocompleteController extends ControllerBase {

  /**
   * Constructs an IconAutocompleteController object.
   *
   * @param \Drupal\Core\Theme\Icon\IconSearch $iconSearch
   *   The icon search service.
   * @param \Drupal\Core\Theme\Icon\Plugin\IconPackManagerInterface $iconPackManager
   *   The icon pack manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    private readonly IconSearch $iconSearch,
    private readonly IconPackManagerInterface $iconPackManager,
    private readonly RendererInterface $renderer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('icon.search'),
      $container->get('plugin.manager.icon_pack'),
      $container->get('renderer')
    );
  }

  /**
   * Autocomplete callback for icon selection.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object that contains the typed string.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The matched icons as a JSON response.
   */
  public function handleSearchIcons(Request $request): JsonResponse {
    $query = trim((string) $request->query->get('q', ''));

    if (empty($query)) {
      return new JsonResponse([]);
    }

    // Get allowed icon packs from query parameter.
    $allowed_icon_pack = [];
    if ($request->query->get('allowed_icon_pack')) {
      $allowed_icon_pack = explode('+', (string) $request->query->get('allowed_icon_pack', ''));
    }

    // Get max results from query parameter.
    $max_result = (int) $request->query->get('max_result', IconSearch::SEARCH_RESULT);

    // Perform the search without callback to get raw icon IDs.
    $icon_ids = $this->iconSearch->search(
      $query,
      $allowed_icon_pack,
      $max_result,
    );

    // Format results with inline SVG previews.
    $results = [];
    foreach ($icon_ids as $icon_full_id) {
      if ($formatted = $this->formatResultWithPreview($icon_full_id)) {
        $results[] = $formatted;
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Format a single result with inline SVG preview.
   *
   * @param string $icon_full_id
   *   The full icon ID (pack_id:icon_id).
   *
   * @return array|null
   *   The formatted result with value and label, or NULL if icon not found.
   */
  protected function formatResultWithPreview(string $icon_full_id): ?array {
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

    // Render the icon as SVG.
    $icon_svg = $this->renderer->renderRoot($icon_render);

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
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the rendered SVG.
   */
  public function renderIcon(Request $request): JsonResponse {
    $pack_id = $request->query->get('pack_id', '');
    $icon_id = $request->query->get('icon_id', '');

    if (empty($pack_id) || empty($icon_id)) {
      return new JsonResponse(['error' => 'Missing pack_id or icon_id'], 400);
    }

    $icon_full_id = $pack_id . ':' . $icon_id;
    $icon = $this->iconPackManager->getIcon($icon_full_id);

    if (!$icon) {
      return new JsonResponse(['error' => 'Icon not found'], 404);
    }

    // Build the icon render array.
    $build = [
      '#type' => 'icon',
      '#pack_id' => $pack_id,
      '#icon_id' => $icon_id,
    ];

    // Render the icon.
    $svg = $this->renderer->renderRoot($build);

    return new JsonResponse(['svg' => (string) $svg]);
  }

}
