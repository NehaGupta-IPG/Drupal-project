<?php

declare(strict_types=1);

namespace Drupal\Tests\toolbar\FunctionalJavascript;

/**
 * Provides a helper for interacting with inert toolbar trays in tests.
 *
 * The toolbar keeps the active tray inert to prevent screen reader focus
 * jumping on fixed-position elements. Tests that need to click elements
 * inside a tray (e.g. the orientation toggle) must remove inert first.
 */
trait ToolbarTrayTestTrait {

  /**
   * Removes the inert attribute from the active toolbar tray.
   *
   * This simulates what happens when a keyboard user presses Tab on the
   * toolbar trigger to enter the tray.
   */
  protected function removeInertFromActiveTray(): void {
    $this->getSession()->executeScript(
      "document.querySelector('.toolbar-tray.is-active')?.removeAttribute('inert')"
    );
  }

}
