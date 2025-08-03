<?php

namespace Drupal\Core\Render\MainContent;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\HtmlResponse;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Default main content renderer for HTML requests.
 *
 * Attachments in HTML responses are handled by
 * \Drupal\Core\Render\AttachmentsResponseProcessorInterface and
 * \Drupal\Core\Render\HtmlResponseAttachmentsProcessor
 *
 * @see \Drupal\Core\Theme\ThemePreprocess::preprocessHtml()
 * @see \Drupal\Core\Render\AttachmentsResponseProcessorInterface
 * @see \Drupal\Core\Render\BareHtmlPageRenderer
 * @see \Drupal\Core\Render\HtmlResponse
 * @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor
 */
class HtmxRenderer implements MainContentRendererInterface {

  /**
   * The display variant manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $displayVariantManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The render cache service.
   *
   * @var \Drupal\Core\Render\RenderCacheInterface
   */
  protected $renderCache;

  /**
   * The renderer configuration array.
   *
   * @var array
   *
   * @see sites/default/default.services.yml
   */
  protected $rendererConfig;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * Constructs a new HtmlRenderer.
   *
   * @param \Drupal\Core\Controller\TitleResolverInterface $title_resolver
   *   The title resolver.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $display_variant_manager
   *   The display variant manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Render\RenderCacheInterface $render_cache
   *   The render cache service.
   * @param array $renderer_config
   *   The renderer configuration array.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   */
  public function __construct(PluginManagerInterface $display_variant_manager, ModuleHandlerInterface $module_handler, RendererInterface $renderer, RenderCacheInterface $render_cache, array $renderer_config, ThemeManagerInterface $theme_manager) {
    $this->displayVariantManager = $display_variant_manager;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->renderCache = $render_cache;
    $this->rendererConfig = $renderer_config;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   *
   * The entire HTML: takes a #type 'page' and wraps it in a #type 'html'.
   */
  public function renderResponse(array $main_content, Request $request, RouteMatchInterface $route_match) {
    [$page, $title] = $this->prepare($main_content, $request, $route_match);

    /*
    if (!isset($page['#type']) || $page['#type'] !== 'page') {
      throw new \LogicException('Must be #type page');
    }
    */

    $page['#title'] = $title;

    // Now render the rendered page.html.twig template inside the html.html.twig
    // template, and use the bubbled #attached metadata from $page to ensure we
    // load all attached assets.
    $html = [
      '#type' => 'html',
      'page' => $page,
    ];

    // The special page regions will appear directly in html.html.twig, not in
    // page.html.twig, hence add them here, just before rendering
    // html.html.twig.
    //$this->buildPageTopAndBottom($html);

    // Render, but don't replace placeholders yet, because that happens later in
    // the render pipeline. To not replace placeholders yet, we use
    // RendererInterface::render() instead of RendererInterface::renderRoot().
    // @see \Drupal\Core\Render\HtmlResponseAttachmentsProcessor.
    $render_context = new RenderContext();
    $this->renderer->executeInRenderContext($render_context, function () use (&$html) {
      // RendererInterface::render() renders the $html render array and updates
      // it in place. We don't care about the return value (which is just
      // $html['#markup']), but about the resulting render array.
      // @todo Simplify this when https://www.drupal.org/node/2495001 lands.
      $this->renderer->render($html);
    });
    // RendererInterface::render() always causes bubbleable metadata to be
    // stored in the render context, no need to check it conditionally.
    $bubbleable_metadata = $render_context->pop();
    $bubbleable_metadata->applyTo($html);
    $content = $this->renderCache->getCacheableRenderArray($html);

    // Also associate the required cache contexts.
    // (Because we use ::render() above and not ::renderRoot(), we manually must
    // ensure the HTML response varies by the required cache contexts.)
    $content['#cache']['contexts'] = Cache::mergeContexts($content['#cache']['contexts'], $this->rendererConfig['required_cache_contexts']);

    // Also associate the "rendered" cache tag. This allows us to invalidate the
    // entire render cache, regardless of the cache bin.
    $content['#cache']['tags'][] = 'rendered';

    $response = new HtmlResponse($content, 200, [
      'Content-Type' => 'text/html; charset=UTF-8',
    ]);

    return $response;
  }

  /**
   * Prepares the HTML body: wraps the main content in #type 'page'.
   *
   * @param array $main_content
   *   The render array representing the main content.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object, for context.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match, for context.
   *
   * @return array
   *   An array with two values:
   *   0. A #type 'page' render array.
   *   1. The page title.
   *
   * @throws \LogicException
   *   If the selected display variant does not implement PageVariantInterface.
   */
  protected function prepare(array $main_content, Request $request, RouteMatchInterface $route_match) {

    // If the _controller result already is #type => page,
    // we have no work to do: The "main content" already is an entire "page"
    // (see html.html.twig).
    if (isset($main_content['#type']) && $main_content['#type'] === 'page') {
      $page = $main_content;
    }
    // Otherwise, render it as the main content of a #type => page, by selecting
    // page display variant to do that and building that page display variant.
    else {
      // Instantiate the page display, and give it the main content.
      $page_display = $this->displayVariantManager->createInstance('simple_page');
      $page_display
        ->setMainContent($main_content);

      $page = $page_display->build();
    }

    /*
    // $page is now fully built. Find all non-empty page regions, and add a
    // theme wrapper function that allows them to be consistently themed.
    $regions = $this->themeManager->getActiveTheme()->getRegions();
    foreach ($regions as $region) {
      if (!empty($page[$region])) {
        $page[$region]['#theme_wrappers'][] = 'region';
        $page[$region]['#region'] = $region;
      }
    }*/

    // Allow hooks to add attachments to $page['#attached'].
    $this->renderer->executeInRenderContext(new RenderContext(), function () use (&$page) {
      $this->invokePageAttachmentHooks($page);
    });

    return [$page, FALSE];
  }

  /**
   * Invokes the page attachment hooks.
   *
   * @param array &$page
   *   A #type 'page' render array, for which the page attachment hooks will be
   *   invoked and to which the results will be added.
   *
   * @throws \LogicException
   *
   * @internal
   *
   * @see hook_page_attachments()
   * @see hook_page_attachments_alter()
   */
  public function invokePageAttachmentHooks(array &$page) {
    // Modules can add attachments.
    $attachments = [];
    $this->moduleHandler->invokeAllWith(
      'page_attachments',
      function (callable $hook, string $module) use (&$attachments) {
        $hook($attachments);
      }
    );
    if (array_diff(array_keys($attachments), ['#attached', '#cache']) !== []) {
      throw new \LogicException('Only #attached and #cache may be set in hook_page_attachments().');
    }

    // Modules and themes can alter page attachments.
    $this->moduleHandler->alter('page_attachments', $attachments);
    $this->themeManager->alter('page_attachments', $attachments);
    if (array_diff(array_keys($attachments), ['#attached', '#cache']) !== []) {
      throw new \LogicException('Only #attached and #cache may be set in hook_page_attachments_alter().');
    }

    // Merge the attachments onto the $page render array.
    $page = $this->renderer->mergeBubbleableMetadata($page, $attachments);
  }

}
