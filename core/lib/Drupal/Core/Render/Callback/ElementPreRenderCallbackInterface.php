<?php

declare(strict_types=1);

namespace Drupal\Core\Render\Callback;

/**
 * Callback that can be used in the '#pre_render' part of a render element.
 *
 * @see \Drupal\Core\Render\Renderer::doRender()
 */
interface ElementPreRenderCallbackInterface {

  /**
   * Processes a render element.
   *
   * @param array $element
   *   Original render element.
   *
   * @return array
   *   Processed render element.
   */
  public function __invoke(array $element): array;

}
