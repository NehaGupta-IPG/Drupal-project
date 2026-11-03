<?php

declare(strict_types=1);

namespace Drupal\Tests\menu_tree_ui\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Shared row/keyboard helpers for the menu_tree_ui keyboard-nav tests.
 *
 * MenuTreeUiTreegridFocusTest and MenuTreeUiInRowKeysTest each seed their own
 * fixture and assert their own behavior, but both find a seeded row by label
 * and dispatch keys at it. This trait holds that common plumbing so it exists
 * once. Expects the using class to extend WebDriverTestBase.
 */
trait MenuTreeUiKeyboardNavTrait {

  /**
   * Menu link plugin IDs, keyed by the labels used in the tests.
   *
   * @var array<string, string>
   */
  private array $linkIds = [];

  /**
   * Creates a menu link and returns its plugin ID.
   *
   * @param string $title
   *   The link title.
   * @param string $parent_plugin_id
   *   The parent link plugin ID, or '' for the top level.
   * @param int $weight
   *   The link weight.
   * @param string $uri
   *   The link URI.
   *
   * @return string
   *   The created link's plugin ID.
   */
  private function createLink(string $title, string $parent_plugin_id, int $weight, string $uri = 'route:<nolink>'): string {
    $entity = MenuLinkContent::create([
      'menu_name' => 'test',
      'title' => $title,
      'link' => ['uri' => $uri],
      'parent' => $parent_plugin_id,
      'weight' => $weight,
    ]);
    $entity->save();
    return $entity->getPluginId();
  }

  /**
   * Finds a row by its label.
   *
   * @param string $label
   *   The label used when seeding.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  private function row(string $label): NodeElement {
    $row = $this->getSession()->getPage()->find(
      'css',
      sprintf('tr[role="row"][data-id="%s"]', $this->linkIds[$label]),
    );
    $this->assertNotNull($row, sprintf('Row %s is in the DOM.', $label));
    return $row;
  }

  /**
   * Focuses a row and dispatches a keydown on it.
   *
   * Deliberately focuses the row itself rather than forcing focus onto a
   * handle, so the test exercises the real focus model instead of papering
   * over it.
   *
   * @param string $label
   *   The row label.
   * @param string $key
   *   The KeyboardEvent.key value to dispatch.
   */
  private function pressKeyOnRow(string $label, string $key): void {
    $id = $this->linkIds[$label];
    $key_json = json_encode($key);
    $script = <<<JS
      const row = document.querySelector('tr[role="row"][data-id="$id"]');
      if (!row) throw new Error('row $label not in DOM');
      row.scrollIntoView({block: 'center'});
      row.focus();
      row.dispatchEvent(new KeyboardEvent('keydown', {
        key: $key_json, bubbles: true, cancelable: true,
      }));
    JS;
    $this->getSession()->executeScript($script);
  }

  /**
   * The tag name of the currently focused element.
   *
   * @return string|null
   *   The uppercase tag name, or NULL when nothing is focused.
   */
  private function activeTag(): ?string {
    return $this->getSession()->evaluateScript(
      'return document.activeElement ? document.activeElement.tagName : null;',
    );
  }

  /**
   * The data-id of the currently focused row, when a row is focused.
   *
   * @return string|null
   *   The focused row's data-id, or NULL when the focus is not on a row.
   */
  private function activeRowId(): ?string {
    return $this->getSession()->evaluateScript(
      "return document.activeElement && document.activeElement.matches('tr[role=\"row\"]')"
      . ' ? document.activeElement.dataset.id : null;',
    );
  }

}
