<?php

declare(strict_types=1);

namespace Drupal\treegrid_test_ui\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\treegrid\TreegridCsrfToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves a minimal treegrid for exercising the spine JS in isolation.
 *
 * A two/three-level tree kept in State, so a test can change it: page()
 * server-renders the top level into a <drupal-treegrid>, and children() feeds
 * the lazy-expand engine one level at a time. Each row carries a 'checkbox' and
 * a 'markup' column cell so the client cell-renderer registry is exercised on
 * lazily-built rows. This consumer only drives the read/navigate/expand spine.
 */
final class TreegridTestUiController extends ControllerBase {

  /**
   * The State key holding the tree: child IDs keyed by parent ID.
   *
   * The key '' is the root. Without a value in State the tree is CHILDREN.
   */
  public const TREE_KEY = 'treegrid_test_ui.tree';

  /**
   * The State key holding the milliseconds the children endpoint waits.
   */
  public const CHILDREN_DELAY_KEY = 'treegrid_test_ui.children_delay';

  /**
   * The State key holding how many children requests still fail with a 500.
   */
  public const CHILDREN_FAIL_KEY = 'treegrid_test_ui.children_fail';

  /**
   * The State key holding how a column update is refused, if it is.
   *
   * An array with 'code' and, optionally, 'status' (default 400) and 'message'
   * (safe HTML, sent as it is; default 'Update refused.').
   */
  public const COLUMN_UPDATE_FAIL_KEY = 'treegrid_test_ui.column_update_fail';

  /**
   * The State key holding the milliseconds the column-update endpoint waits.
   */
  public const COLUMN_UPDATE_DELAY_KEY = 'treegrid_test_ui.column_update_delay';

  /**
   * The State key holding the column updates received, oldest first.
   *
   * Each entry is an array with the decoded 'body' and a 'token' flag that is
   * TRUE when the request carried the X-CSRF-Token header.
   */
  public const COLUMN_UPDATES_KEY = 'treegrid_test_ui.column_updates';

  /**
   * The tree when State holds none.
   */
  public const CHILDREN = [
    '' => ['p1', 'p2', 'leaf1'],
    'p1' => ['c1a', 'c1b'],
    'c1b' => ['c1b1'],
    'p2' => ['c2a', 'c2b'],
  ];

  /**
   * Renders the test page: the server-rendered top level of the tree.
   */
  public function page(): array {
    $top = $this->tree()[''] ?? [];
    $set_size = count($top);
    $rows = '';
    foreach ($top as $i => $id) {
      $rows .= $this->renderRow($this->item($id), $i + 1, $set_size);
    }

    $build = [
      '#attached' => [
        'library' => ['treegrid_test_ui/treegrid_test_ui'],
      ],
      '#cache' => ['max-age' => 0],
    ];
    $children_url = Url::fromRoute('treegrid_test_ui.children')->toString();
    $update_url = Url::fromRoute('treegrid_test_ui.column_update')->toString();
    $html = '<drupal-treegrid data-provider="treegrid_test_ui" data-children-url="'
      . Html::escape($children_url) . '" data-auto-expand-threshold="0"'
      . ' data-treegrid-column-update-url="' . Html::escape($update_url) . '"'
      . ' data-treegrid-csrf-token="' . TreegridCsrfToken::addPlaceholder($build) . '">'
      . '<table role="treegrid"><tbody>' . $rows . '</tbody></table></drupal-treegrid>';

    $build['#markup'] = Markup::create($html);
    return $build;
  }

  /**
   * Records a column update, or refuses it as State says.
   */
  public function columnUpdate(Request $request): JsonResponse {
    $delay = (int) $this->state()->get(self::COLUMN_UPDATE_DELAY_KEY, 0);
    if ($delay > 0) {
      usleep($delay * 1000);
    }
    $body = json_decode((string) $request->getContent(), TRUE);
    $updates = $this->state()->get(self::COLUMN_UPDATES_KEY, []);
    $updates[] = ['body' => $body, 'token' => $request->headers->has('X-CSRF-Token')];
    $this->state()->set(self::COLUMN_UPDATES_KEY, $updates);

    $fail = $this->state()->get(self::COLUMN_UPDATE_FAIL_KEY);
    if (is_array($fail)) {
      return new JsonResponse(
        ['ok' => FALSE, 'code' => $fail['code'] ?? 'failed', 'message' => $fail['message'] ?? 'Update refused.'],
        (int) ($fail['status'] ?? 400),
      );
    }

    $column_id = is_array($body) ? (string) ($body['column_id'] ?? '') : '';
    $row_id = is_array($body) ? (string) ($body['row_id'] ?? '') : '';
    $manager = \Drupal::service('plugin.manager.treegrid_column');
    if ($manager->hasDefinition($column_id) && !$manager->createInstance($column_id)->handleUpdate($row_id, $body['value'] ?? NULL)) {
      return new JsonResponse(['ok' => FALSE, 'code' => 'not_updatable'], 400);
    }
    return new JsonResponse(['ok' => TRUE]);
  }

  /**
   * Returns one level of children as JSON for the lazy-expand engine.
   */
  public function children(Request $request): JsonResponse {
    $delay = (int) $this->state()->get(self::CHILDREN_DELAY_KEY, 0);
    if ($delay > 0) {
      usleep($delay * 1000);
    }
    $failing = (int) $this->state()->get(self::CHILDREN_FAIL_KEY, 0);
    if ($failing > 0) {
      $this->state()->set(self::CHILDREN_FAIL_KEY, $failing - 1);
      return new JsonResponse(['ok' => FALSE], 500);
    }
    $parent = (string) $request->query->get('parent', '');
    $ids = $this->tree()[$parent] ?? [];
    $items = array_map(fn(string $id): array => $this->item($id), $ids);
    return new JsonResponse(['parent' => $parent ?: NULL, 'items' => array_values($items)]);
  }

  /**
   * The current tree: child IDs keyed by parent ID.
   *
   * @return array<string, string[]>
   *   The tree.
   */
  private function tree(): array {
    return $this->state()->get(self::TREE_KEY, self::CHILDREN);
  }

  /**
   * Builds the data array for one item, including its column cells.
   *
   * @param string $id
   *   The item ID.
   *
   * @return array<string, mixed>
   *   The item data, matching the JSON shape the spine consumes.
   */
  private function item(string $id): array {
    $tree = $this->tree();
    $parents = [];
    foreach ($tree as $parent_id => $child_ids) {
      foreach ($child_ids as $child_id) {
        $parents[$child_id] = (string) $parent_id;
      }
    }
    $parent = $parents[$id] ?? '';
    $depth = 1;
    for ($up = $parent; $up !== ''; $up = $parents[$up] ?? '') {
      $depth++;
    }
    // Checkbox value varies by ID so a test can assert the renderer reads it:
    // IDs containing "a" render unchecked, the rest checked.
    $checked = !str_contains($id, 'a');
    return [
      'id' => $id,
      'parent_id' => $parent,
      'title' => 'Item ' . $id,
      'has_children' => !empty($tree[$id]),
      'depth' => $depth,
      'columns' => [
        'check' => [
          'type' => 'checkbox',
          'value' => $checked,
          'label' => 'Toggle ' . $id,
          'interactive' => TRUE,
        ],
        'mark' => [
          'type' => 'markup',
          'html' => '<span class="tgu-mark">m-' . Html::escape($id) . '</span>',
        ],
      ],
    ];
  }

  /**
   * Server-renders one row, matching the DOM the JS row builder produces.
   *
   * @param array<string, mixed> $item
   *   The item data.
   * @param int $pos_in_set
   *   The 1-based position among siblings.
   * @param int $set_size
   *   The number of siblings.
   *
   * @return string
   *   The row HTML.
   */
  private function renderRow(array $item, int $pos_in_set, int $set_size): string {
    $id = Html::escape($item['id']);
    $has_children = $item['has_children'];
    $tabindex = ($item['parent_id'] === '' && $pos_in_set === 1) ? '0' : '-1';

    $disclosure = $has_children
      ? '<button type="button" class="tgu-toggle" data-treegrid-toggle tabindex="-1" aria-label="Toggle ' . $id . '">&#9656;</button>'
      : '<span class="tgu-toggle-spacer" aria-hidden="true"></span>';

    $check = $item['columns']['check'];
    $checkbox = '<input type="checkbox" class="treegrid-cell-checkbox" aria-label="'
      . Html::escape($check['label']) . '" data-column-id="check" data-treegrid-control'
      . ($check['value'] ? ' checked' : '') . '>';
    $markup = '<div class="treegrid-cell-markup">' . $item['columns']['mark']['html'] . '</div>';

    return '<tr role="row" data-id="' . $id . '" data-parent-id="' . Html::escape($item['parent_id'])
      . '" data-has-children="' . ($has_children ? 'true' : 'false') . '"'
      . ' aria-level="' . $item['depth'] . '" aria-posinset="' . $pos_in_set
      . '" aria-setsize="' . $set_size . '"' . ($has_children ? ' aria-expanded="false"' : '')
      . ' aria-label="Row ' . $id . '" tabindex="' . $tabindex . '">'
      . '<td role="gridcell" class="tgu-name">' . $disclosure
      . '<span class="tgu-title">' . Html::escape($item['title']) . '</span></td>'
      . '<td role="gridcell" data-column="check">' . $checkbox . '</td>'
      . '<td role="gridcell" data-column="mark">' . $markup . '</td>'
      . '</tr>';
  }

}
