<?php

namespace Drupal\Core\Render\MainContent;

use Drupal\Core\Controller\TitleResolverInterface;
use Drupal\Core\Render\RenderCacheInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extend the dialog renderer to return a modal dialog.
 */
class HtmxOffCanvasRenderer extends HtmxDialogRenderer implements MainContentRendererInterface {

  public function __construct(TitleResolverInterface $titleResolver, RendererInterface $renderer, RenderCacheInterface $renderCache, array $rendererConfig, protected string $position) {
    parent::__construct($titleResolver, $renderer, $renderCache, $rendererConfig);
    if ($this->position == 'top') {
      // Insert the dialog before the main canvas wrapper so the main content
      // is pushed down.
      $this->swapStrategy = 'beforebegin';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function generateDialog(array $main_content, string $dialogId, mixed $title, Request $request): array {
    $dialog = parent::generateDialog($main_content, $dialogId, $title, $request);
    $dialog['#props']['position'] = $this->position;
    $dialog['#variant'] = 'off-canvas';
    return $dialog;
  }

}
