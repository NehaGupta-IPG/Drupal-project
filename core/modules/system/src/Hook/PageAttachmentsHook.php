<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BareHtmlPageRendererInterface;

/**
 * Implements hook_page_attachments() for the system module.
 */
final class PageAttachmentsHook {

  public function __construct(
    private readonly BareHtmlPageRendererInterface $bareHtmlPageRenderer,
  ) {}

  /**
   * Implements hook_page_attachments().
   *
   * @see \Drupal\Core\Theme\ThemePreprocess::preprocessMaintenancePage()
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter
   * @see \Drupal\system\Hook\SystemThemeHooks::preprocessHtml()
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    $this->bareHtmlPageRenderer->systemPageAttachments($page);

    // The <html> element carries a data-color-scheme attribute that varies per
    // user preference. That attribute is set in
    // \Drupal\system\Hook\SystemThemeHooks::preprocessHtml(), but cacheability
    // cannot be declared in a preprocess function because the renderer collects
    // cacheability from the render array before theming happens. Declare the
    // cache context here instead, so that it bubbles up correctly.
    $page['#cache']['contexts'][] = 'user.color_scheme';
  }

}
