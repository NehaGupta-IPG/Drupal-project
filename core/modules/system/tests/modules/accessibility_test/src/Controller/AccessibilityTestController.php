<?php

declare(strict_types=1);

namespace Drupal\accessibility_test\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller providing page callbacks for the action admin interface.
 */
class AccessibilityTestController extends ControllerBase {

  /**
   * Prints the stored session value to the screen.
   *
   * @return string
   *   A notification message.
   */
  public function page() {
    $html = <<<HTML
      <section>
        <h2>Poor contrast (WCAG 2 AA)</h2>

        <div>
            <span style="color:gray">Low contrast text</span>
        </div>
      </section>

      <section>
        <h2>Form element without label (WCAG 2 A)</h2>

        <div>
            <input type="text">
        </div>
      </section>
    HTML;

    return [
      '#type' => 'inline_template',
      '#template' => $html,
    ];
  }

}
