<?php

namespace Drupal\Core\PathProcessor;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Symfony\Component\HttpFoundation\Request;

/**
 * Transforms inbound HTMX dialog paths.
 *
 * Detects inbound requests starting with `/htmx/dialog/` looking for paths
 * that match the form `/htmx/dialog/{type}/{path}` and transforms these into
 * just the catch-all path, while also setting the `drupal_htmx_dialog.{type}`
 * wrapper format so that the resulting main content is wrapped in the
 * necessary markup to display as the requested dialog type.
 *
 * @see \Drupal\Core\Render\MainContent\HtmxDialogRenderer
 */
class HtmxDialogPathProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request) {
    // Return unmodified path if the path does not match our pattern.
    if (!str_starts_with($path, '/htmx/dialog/') && substr_count(strtolower($path), "/") >= 4) {
      return $path;
    }
    // Trim our dialog prefix and type from the beginning of the path.
    $trimmedPath = substr($path, strlen('/htmx/dialog/'));
    $remainingPathStart = strpos($trimmedPath, '/');

    // Split the string into two values.
    $dialogType = substr($trimmedPath, 0, $remainingPathStart);
    if (empty($dialogType)) {
      // Path is missing a dialog type, return unmodified path.
      return $path;
    }
    $remaining_path = substr($trimmedPath, $remainingPathStart);

    $format = 'drupal_htmx_dialog.' . $dialogType;
    $request->query->set(MainContentViewSubscriber::WRAPPER_FORMAT, $format);

    return $remaining_path;
  }

}
