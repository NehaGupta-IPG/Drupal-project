<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Theme\ColorSchemeSupport;

/**
 * Implements hook_page_attachments() for the system module.
 */
final class PageAttachmentsHook {

  public function __construct(
    private readonly BareHtmlPageRendererInterface $bareHtmlPageRenderer,
    private readonly AccountInterface $currentUser,
    private readonly ColorSchemeSupport $colorSchemeSupport,
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

    // Where the color scheme preference has an effect, the <html> element
    // carries a data-color-scheme attribute that varies per user preference.
    // That attribute is set in
    // \Drupal\Core\Theme\ThemePreprocess::preprocessHtml(), but cacheability
    // cannot be declared in a preprocess function because the renderer collects
    // cacheability from the render array before theming happens. Declare it
    // here instead, so that it bubbles up correctly. The decision itself is
    // cacheable too, so collect its metadata as well.
    $cacheability = CacheableMetadata::createFromRenderArray($page);
    if ($this->colorSchemeSupport->appliesToAccount($this->currentUser, $cacheability)) {
      $cacheability->addCacheContexts(['user.color_scheme']);
    }
    $cacheability->applyTo($page);
  }

}
