<?php

declare(strict_types=1);

namespace Drupal\Tests\history\Kernel\Views;

use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\Tests\views\Kernel\ViewsKernelTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;

/**
 * Tests the history timestamp handlers.
 *
 * @group history
 * @see \Drupal\history\Plugin\views\field\HistoryUserTimestamp
 * @see \Drupal\history\Plugin\views\filter\HistoryUserTimestamp
 */
class HistoryTimestampTest extends ViewsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['history', 'node'];

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_history'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE): void {
    parent::setUp(FALSE);

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installSchema('history', ['history']);
    // Use history_test_theme because its marker is wrapped in a span so it can
    // be easily targeted with xpath.
    \Drupal::service('theme_installer')->install(['history_test_theme']);
    \Drupal::theme()->setActiveTheme(\Drupal::service('theme.initialization')->initTheme('history_test_theme'));

    // For MongoDB to update the views correctly the views must be loaded after
    // the creation of the fields.
    ViewTestData::createTestViews(static::class, ['views_test_config']);
  }

  /**
   * Tests the handlers.
   */
  public function testHandlers(): void {
    $nodes = [];
    $node = Node::create([
      'title' => 'n1',
      'type' => 'default',
    ]);
    $node->save();
    $nodes[] = $node;
    $node = Node::create([
      'title' => 'n2',
      'type' => 'default',
    ]);
    $node->save();
    $nodes[] = $node;

    $account = User::create(['name' => 'admin']);
    $account->save();
    \Drupal::currentUser()->setAccount($account);

    $connection = Database::getConnection();
    $requestTime = \Drupal::time()->getRequestTime();
    $connection->insert('history')
      ->fields([
        'uid' => $account->id(),
        'nid' => $nodes[0]->id(),
        'timestamp' => $requestTime - 100,
      ])->execute();

    $connection->insert('history')
      ->fields([
        'uid' => $account->id(),
        'nid' => $nodes[1]->id(),
        'timestamp' => $requestTime + 100,
      ])->execute();

    if (Database::getConnection()->driver() == 'mongodb') {
      $expected_result = [
        [
          'vid' => $nodes[0]->id(),
        ],
      ];

      $column_map = [
        'vid' => 'vid',
      ];
    }
    else {
      $expected_result = [
        [
          'nid' => $nodes[0]->id(),
        ],
      ];

      $column_map = [
        'nid' => 'nid',
      ];
    }

    // Test the history field.
    $view = Views::getView('test_history');
    $view->setDisplay('page_1');
    $this->executeView($view);
    $this->assertCount(2, $view->result);
    $output = $view->preview();
    $this->setRawContent(\Drupal::service('renderer')->renderRoot($output));
    $result = $this->xpath('//span[@class=:class]', [':class' => 'marker']);
    $this->assertCount(1, $result, 'Just one node is marked as new');

    // Test the history filter.
    $view = Views::getView('test_history');
    $view->setDisplay('page_2');
    $this->executeView($view);
    $this->assertCount(1, $view->result);
    $this->assertIdenticalResultset($view, $expected_result, $column_map);

    // Install Comment module and make sure that content types without comment
    // field will not break the view.
    // See \Drupal\history\Plugin\views\filter\HistoryUserTimestamp::query()
    \Drupal::service('module_installer')->install(['comment']);
    $view = Views::getView('test_history');
    $view->setDisplay('page_2');
    $this->executeView($view);
  }

}
