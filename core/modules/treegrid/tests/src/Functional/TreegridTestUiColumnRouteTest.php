<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\treegrid_test_ui\Controller\TreegridTestUiController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the test consumer's column route stays out of column plugins.
 *
 * The route is open to everyone, so it must only record what it receives and
 * never run a registered column plugin's update.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridTestUiColumnRouteTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_test',
    'treegrid_test_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A column that is a registered plugin is recorded and answered ok.
   */
  public function testRegisteredColumnIsRecordedNotUpdated(): void {
    $this->drupalGet('session/token');
    $token = $this->getSession()->getPage()->getContent();
    $body = ['column_id' => 'treegrid_test_a', 'row_id' => 'p1', 'value' => TRUE];

    $client = $this->getSession()->getDriver()->getClient();
    $client->request(
      'POST',
      Url::fromRoute('treegrid_test_ui.column_update')->setAbsolute()->toString(),
      [],
      [],
      ['HTTP_X-CSRF-Token' => $token, 'CONTENT_TYPE' => 'application/json'],
      json_encode($body),
    );

    $this->assertSame(200, $client->getInternalResponse()->getStatusCode());
    $this->assertSame(['ok' => TRUE], json_decode($client->getInternalResponse()->getContent(), TRUE));
    $updates = \Drupal::state()->get(TreegridTestUiController::COLUMN_UPDATES_KEY, []);
    $this->assertSame($body, $updates[0]['body']);
  }

}
