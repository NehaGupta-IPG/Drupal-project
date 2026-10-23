<?php

declare(strict_types=1);

namespace Drupal\Core\Ajax;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AttachedAssets;

/**
 * Provides an AJAX command for opening a modal with URL.
 *
 * OpenDialogCommand is a similar class which opens modals but works
 * differently as it needs all data to be passed through dialogOptions while
 * OpenModalDialogWithUrl fetches the data from routing info of the URL.
 *
 * @see \Drupal\Core\Ajax\OpenDialogCommand
 */
class OpenModalDialogWithUrl implements CommandInterface, CommandWithAttachedAssetsInterface {

  /**
   * Constructs a OpenModalDialogWithUrl object.
   *
   * @param string $url
   *   Only Internal URLs or URLs with the same domain and base path are
   *   allowed.
   * @param array $settings
   *   The dialog settings.
   * @param array $ajaxSettings
   *   (optional) Drupal.Ajax settings to override the defaults, for example to
   *   open the dialog with a POST request. Without overrides, the dialog opens
   *   with a GET request. The URL is always taken from $url.
   */
  public function __construct(
    protected string $url,
    protected array $settings,
    protected array $ajaxSettings = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function render() {
    // @see \Drupal\Core\Routing\LocalAwareRedirectResponseTrait::isLocal()
    if (!UrlHelper::isExternal($this->url) || UrlHelper::externalIsLocal($this->url, $this->getBaseURL())) {
      return [
        'command' => 'openModalDialogWithUrl',
        'url' => $this->url,
        'dialogOptions' => $this->settings,
        'ajaxSettings' => $this->ajaxSettings,
      ];
    }
    throw new \LogicException('External URLs are not allowed.');
  }

  /**
   * Gets the complete base URL.
   */
  private function getBaseUrl() {
    $requestContext = \Drupal::service('router.request_context');
    return $requestContext->getCompleteBaseUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachedAssets() {
    return (new AttachedAssets())->setLibraries(['core/drupal.dialog.ajax']);
  }

}
