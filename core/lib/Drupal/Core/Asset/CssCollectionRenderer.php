<?php

namespace Drupal\Core\Asset;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Renders CSS assets.
 */
class CssCollectionRenderer implements AssetCollectionRendererInterface {

  /**
   * Constructs a CssCollectionRenderer.
   *
   * @param \Drupal\Core\Asset\AssetQueryStringInterface $assetQueryString
   *   The asset query string.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Request stack.
   */
  public function __construct(
    protected AssetQueryStringInterface $assetQueryString,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected RouteMatchInterface $routeMatch,
    protected RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $css_assets) {
    $elements = [];

    // Defaults for LINK and STYLE elements.
    $link_element_defaults = [
      '#type' => 'html_tag',
      '#tag' => 'link',
      '#attributes' => [
        'rel' => 'stylesheet',
      ],
    ];

    foreach ($css_assets as $css_asset) {
      $element = $link_element_defaults;
      $element['#attributes']['media'] = $css_asset['media'];

      switch ($css_asset['type']) {
        // For file items, output a LINK tag for file CSS assets.
        case 'file':
          $element['#attributes']['href'] = $this->fileUrlGenerator->generateString($css_asset['data']);
          // For unaggregated assets, add a query string to force edge/browser
          // cache invalidation. This query string is updated after each full
          // cache clear.
          if (!isset($css_asset['preprocessed'])) {
            $query_string_separator = str_contains($css_asset['data'], '?') ? '&' : '?';
            $element['#attributes']['href'] .= $query_string_separator . $this->assetQueryString->get();
          }
          break;

        case 'external':
          $element['#attributes']['href'] = $css_asset['data'];
          break;

        default:
          throw new \Exception('Invalid CSS asset type.');
      }

      // Merge any additional attributes.
      if (!empty($css_asset['attributes'])) {
        $element['#attributes'] += $css_asset['attributes'];
      }

      $elements[] = $element;
    }

    return $this->inlineCriticalCss($elements);
  }

  /**
   * Uses an inline style tag for any CSS files that are flagged as critical.
   *
   * To make use of this, add {attributes: {critical: true} to CSS files in
   * your theme or module's libraries.yml file. Be sure to mark the files as
   * preprocess: false too so they're not aggregated.
   *
   * @param array $assets
   *   Existing CSS elements.
   *
   * @return array
   *   CSS elements with any CSS inlined as required.
   */
  protected function inlineCriticalCss(array $assets): array {
    // Only inline CSS if we are using the frontend theme.
    // Also skip anything other than HTML requests.
    $request = $this->requestStack->getCurrentRequest();
    $wrapperFormat = $request->get('_wrapper_format', 'html');
    // Views AJAX is special and doesn't set wrapper_format properly, so check
    // for that too.
    $currentRoute = $this->routeMatch->getRouteName();
    if (
      // Not HTML.
      $wrapperFormat !== 'html'
      // OR views AJAX.
      || $currentRoute === 'views.ajax'
      // OR Big Pipe placeholders.
      || $request->headers->get('accept') === 'application/vnd.drupal-ajax') {
      return $assets;
    }

    $elements = [];
    foreach ($assets as $asset) {
      $attributes = $asset['#attributes'];
      // Skip files with print media.
      if ($asset['#attributes']['media'] === 'print') {
        $elements[] = $asset;
        continue;
      }
      // Any CSS file with a critical flag should be inlined.
      if (isset($attributes['critical'])) {
        $inlineCriticalCSS = $this->inlineCssFile(\substr($attributes['href'], 1));
        if ($inlineCriticalCSS !== NULL) {
          $elements[] = $inlineCriticalCSS;
          continue;
        }
      }
      // @todo Defer all non critical CSS -
      //   https://www.drupal.org/project/drupal/issues/2989324
      $elements[] = $asset;
    }

    return \array_filter($elements);
  }

  /**
   * Turn a file into a renderable <style> tag.
   *
   * @param string $path
   *   File URL to turn into inline CSS.
   *
   * @return array|null
   *   Return NULL if the file does not exist.
   */
  protected function inlineCssFile(string $path): ?array {
    $path = \strtok($path, "?");
    if (!\is_string($path)) {
      return NULL;
    }
    $file_name = \sprintf('%s/%s', DRUPAL_ROOT, $path);
    if (\file_exists($file_name)) {
      $contents = \file_get_contents($path);
      if (!\is_string($contents)) {
        return NULL;
      }
      return [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#value' => Markup::create($contents),
        '#attributes' => [
          // Add a data-src attribute to aid in debugging/identification.
          'data-src' => \basename($path),
        ],
      ];
    }
    return NULL;
  }

}
