<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Implements hook_page_attachments() for the system module.
 */
final class PageAttachmentsHook {

  public function __construct(
    private readonly BareHtmlPageRendererInterface $bareHtmlPageRenderer,
    private readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * Implements hook_page_attachments().
   *
   * @see \Drupal\Core\Theme\ThemePreprocess::preprocessMaintenancePage()
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter
   * @see \Drupal\Core\Theme\ThemePreprocess::preprocessHtml()
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    $this->bareHtmlPageRenderer->systemPageAttachments($page);

    // Themes that declare 'supports color scheme' render a data-color-scheme
    // attribute on the <html> element, which varies per user preference. That
    // attribute is set in \Drupal\Core\Theme\ThemePreprocess::preprocessHtml(),
    // but cacheability cannot be declared in a preprocess function because the
    // renderer collects cacheability from the render array before theming
    // happens. Declare the cache context here instead, so that it bubbles up
    // correctly.
    if ($this->themeManager->getActiveTheme()->supportsColorScheme()) {
      $page['#cache']['contexts'][] = 'user.color_scheme';
    }
  }

}
