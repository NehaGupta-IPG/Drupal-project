<?php

declare(strict_types=1);

namespace Drupal\system\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BareHtmlPageRendererInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Implements hook_page_attachments() for the system module.
 */
final class PageAttachmentsHook {

  public function __construct(
    private readonly BareHtmlPageRendererInterface $bareHtmlPageRenderer,
    private readonly AccountProxyInterface $currentUser,
  ) {}

  /**
   * Implements hook_page_attachments().
   *
   * @see \Drupal\Core\Theme\ThemePreprocess::preprocessMaintenancePage()
   * @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$page): void {
    $this->bareHtmlPageRenderer->systemPageAttachments($page);

    // Handle setting the "active" class on links by:
    // - loading the active-link library if the current user is authenticated;
    // - applying a response filter if the current user is anonymous.
    // @see \Drupal\Core\Link
    // @see \Drupal\Core\Utility\LinkGenerator::generate()
    // @see \Drupal\Core\Theme\ThemePreprocess::preprocessLinks()
    // @see \Drupal\Core\EventSubscriber\ActiveLinkResponseFilter
    $page['#cache']['contexts'][] = 'user.roles:authenticated';
    if ($this->currentUser->isAuthenticated()) {
      $page['#attached']['library'][] = 'core/drupal.active-link';
    }
  }

}
