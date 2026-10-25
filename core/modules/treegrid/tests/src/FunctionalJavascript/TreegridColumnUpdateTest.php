<?php

declare(strict_types=1);

namespace Drupal\Tests\treegrid\FunctionalJavascript;

use Behat\Mink\Element\NodeElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\treegrid_test_ui\Controller\TreegridTestUiController;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests treegrid's column-update client against the treegrid_test_ui page.
 */
#[Group('treegrid')]
#[RunTestsInSeparateProcesses]
final class TreegridColumnUpdateTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'treegrid',
    'treegrid_test_ui',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->drupalCreateUser());
  }

  /**
   * A toggle sends the documented body with the token and keeps the state.
   */
  public function testToggleSendsDocumentedBody(): void {
    $this->openPage();
    $checkbox = $this->checkbox('p1');
    $this->assertTrue($checkbox->isChecked());

    $checkbox->click();
    $updates = $this->waitForUpdates(1);

    $this->assertEquals(['column_id' => 'check', 'row_id' => 'p1', 'value' => FALSE], $updates[0]['body']);
    $this->assertTrue($updates[0]['token']);
    $this->waitForEnabled('p1');
    $this->assertFalse($this->checkbox('p1')->isChecked());
  }

  /**
   * The checkbox is disabled while its request is in flight.
   */
  public function testCheckboxDisabledWhileInFlight(): void {
    \Drupal::state()->set(TreegridTestUiController::COLUMN_UPDATE_DELAY_KEY, 1500);
    $this->openPage();

    $this->checkbox('p1')->click();
    $this->assertTrue($this->getSession()->evaluateScript(
      "return document.querySelector('tr[data-id=\"p1\"] input.treegrid-cell-checkbox').disabled;",
    ));
    $this->waitForEnabled('p1');
    $this->assertFalse($this->checkbox('p1')->isChecked());
  }

  /**
   * A refused update reverts the checkbox and fires the failure event.
   */
  public function testRefusalRevertsAndFiresFailureEvent(): void {
    \Drupal::state()->set(TreegridTestUiController::COLUMN_UPDATE_FAIL_KEY, ['code' => 'nope', 'status' => 400]);
    $this->openPage();
    $this->recordFailures();

    $this->checkbox('p1')->click();
    $this->assertJsCondition('window.columnTest.failures.length === 1');

    $this->assertTrue($this->checkbox('p1')->isChecked(), 'The checkbox goes back to its earlier value.');
    $failure = $this->getSession()->evaluateScript('return window.columnTest.failures[0];');
    $this->assertSame('nope', $failure['code']);
    $this->assertSame(400, $failure['status']);
    $this->assertSame('p1', $failure['rowId']);
    $this->assertSame('check', $failure['columnId']);
  }

  /**
   * A refusal message with an escaped ampersand is announced once-escaped.
   */
  public function testRefusalMessageIsAnnouncedOnceEscaped(): void {
    \Drupal::state()->set(TreegridTestUiController::COLUMN_UPDATE_FAIL_KEY, ['message' => 'Tom &amp; Jerry']);
    $this->openPage();

    $this->checkbox('p1')->click();

    $this->assertJsCondition("document.getElementById('drupal-live-announce').textContent.includes('Tom & Jerry')");
  }

  /**
   * A busy refusal reverts the checkbox.
   */
  public function testBusyRefusalReverts(): void {
    \Drupal::state()->set(TreegridTestUiController::COLUMN_UPDATE_FAIL_KEY, ['code' => 'busy', 'status' => 409]);
    $this->openPage();
    $this->recordFailures();

    $this->checkbox('p2')->click();
    $this->assertJsCondition('window.columnTest.failures.length === 1');

    $this->assertTrue($this->checkbox('p2')->isChecked());
    $this->assertSame('busy', $this->getSession()->evaluateScript('return window.columnTest.failures[0].code;'));
  }

  /**
   * A toggle made while a slow unit runs is sent at once.
   */
  public function testToggleIsSentWhileAUnitRuns(): void {
    $this->openPage();
    $this->getSession()->executeScript(<<<'JS'
      window.columnTest = { unitDone: false };
      document.querySelector('drupal-treegrid').exclusive(
        () => new Promise((resolve) => window.setTimeout(resolve, 3000)),
      ).then(() => {
        window.columnTest.unitDone = true;
      });
    JS);

    $this->checkbox('p1')->click();
    $this->waitForUpdates(1);

    $this->assertFalse($this->getSession()->evaluateScript('return window.columnTest.unitDone;'));
  }

  /**
   * After the element is moved in the DOM a toggle sends exactly one request.
   */
  public function testToggleAfterDomMoveSendsOneRequest(): void {
    $this->openPage();
    $this->getSession()->executeScript(<<<'JS'
      const element = document.querySelector('drupal-treegrid');
      const wrapper = document.createElement('div');
      element.parentNode.appendChild(wrapper);
      wrapper.appendChild(element);
    JS);

    $this->checkbox('p1')->click();
    $this->waitForUpdates(1);
    $this->waitForEnabled('p1');
    usleep(500000);

    $this->assertCount(1, $this->updates());
  }

  // Helpers.

  /**
   * Opens the test page and waits for the element to be ready.
   */
  private function openPage(): void {
    $this->drupalGet('/treegrid-test-ui');
    $this->assertNotNull($this->assertSession()->waitForElement('css', 'drupal-treegrid[data-treegrid-ready]'));
  }

  /**
   * The Enabled-style checkbox of a row.
   *
   * @param string $id
   *   The row ID.
   */
  private function checkbox(string $id): NodeElement {
    $checkbox = $this->getSession()->getPage()->find('css', sprintf('tr[data-id="%s"] input.treegrid-cell-checkbox', $id));
    $this->assertNotNull($checkbox, "Row $id should have a checkbox.");
    return $checkbox;
  }

  /**
   * Waits until a row's checkbox is enabled again.
   *
   * @param string $id
   *   The row ID.
   */
  private function waitForEnabled(string $id): void {
    $this->assertJsCondition(sprintf(
      "!document.querySelector('tr[data-id=\"%s\"] input.treegrid-cell-checkbox').disabled",
      $id,
    ));
  }

  /**
   * Records failure events on window.columnTest.failures.
   */
  private function recordFailures(): void {
    $this->getSession()->executeScript(<<<'JS'
      window.columnTest = { failures: [] };
      document.querySelector('drupal-treegrid').addEventListener('treegrid:cell-update-failed', (event) => {
        window.columnTest.failures.push(event.detail);
      });
    JS);
  }

  /**
   * The column updates the server received.
   *
   * @return array<int, array{body: array, token: bool}>
   *   The updates, oldest first.
   */
  private function updates(): array {
    $state = \Drupal::state();
    $state->resetCache();
    return $state->get(TreegridTestUiController::COLUMN_UPDATES_KEY, []);
  }

  /**
   * Waits until the server has received a number of updates.
   *
   * @param int $count
   *   The number of updates to wait for.
   *
   * @return array<int, array{body: array, token: bool}>
   *   The updates.
   */
  private function waitForUpdates(int $count): array {
    for ($i = 0; $i < 50; $i++) {
      $updates = $this->updates();
      if (count($updates) >= $count) {
        return $updates;
      }
      usleep(100000);
    }
    $this->fail("The server did not receive $count column update(s).");
  }

}
