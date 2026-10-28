<?php

declare(strict_types=1);

namespace Drupal\FunctionalJavascriptTests\Ajax;

use Drupal\Component\Serialization\Json;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests dialog sizes passed through AJAX commands.
 */
#[Group('Ajax')]
#[RunTestsInSeparateProcesses]
class DialogSizeTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['ajax_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests size options passed through AJAX for modal and non-modal dialogs.
   */
  #[DataProvider('dialogSizesProvider')]
  public function testDialogSizes(bool $modal, array $cases): void {
    $auto_height = NULL;
    foreach ($cases as $label => ['input' => $options, 'expected' => $expected]) {
      ['width' => $width, 'height' => $height] = $expected;
      $content_height = array_pop($options);
      $message = ($modal ? 'Modal' : 'Non-modal') . ' dialog: ' . $label;
      $this->drupalGet('ajax-test/dialog-sizes', [
        'query' => [
          'modal' => (int) $modal,
          'height' => $content_height,
          'options' => Json::encode($options + ['width' => '350px']),
        ],
      ]);
      $this->clickLink('Open dialog');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $dialog = $this->assertSession()->elementExists('css', '.ui-dialog');
      $this->assertStringContainsString("width: {$width}px;", $dialog->getAttribute('style'), $message);
      $actual_height = $this->getDialogHeight();
      if ($height === 'auto') {
        $auto_height ??= $actual_height;
        $height = $auto_height;
        $this->assertSession()->elementTextContains('css', '.ui-dialog-content', 'Dialog content');
      }
      elseif ($height === '80%') {
        $height = floor(0.8 * $this->getAvailableViewportHeight());
      }
      $this->assertEqualsWithDelta($height, $actual_height, 1, $message);
    }
  }

  /**
   * Provides size options, expected dimensions, and both dialog modes.
   */
  public static function dialogSizesProvider(): \Generator {
    $cases = [
      // AJAX sends both numbers and numeric strings as strings.
      'numeric string' => [
        'input' => ['maxHeight' => '400', 'content_height' => 1000],
        'expected' => ['width' => 350, 'height' => 400],
      ],
      'pixels' => [
        'input' => ['maxHeight' => '400px', 'content_height' => 1000],
        'expected' => ['width' => 350, 'height' => 400],
      ],
      'pixel height' => [
        'input' => ['height' => '180px', 'content_height' => 40],
        'expected' => ['width' => 350, 'height' => 180],
      ],
      'percentage maximum' => [
        'input' => ['maxHeight' => '80%', 'content_height' => 1000],
        'expected' => ['width' => 350, 'height' => '80%'],
      ],
      'percentage height' => [
        'input' => ['height' => '80%', 'content_height' => 1000],
        'expected' => ['width' => 350, 'height' => '80%'],
      ],
      'percentage height with pixel maximum' => [
        'input' => ['height' => '80%', 'maxHeight' => '400px', 'content_height' => 1000],
        'expected' => ['width' => 350, 'height' => 400],
      ],
      'minimum sizes' => [
        'input' => [
          'width' => '100px',
          'minWidth' => '320px',
          'height' => 'auto',
          'minHeight' => '180px',
          'content_height' => 40,
        ],
        'expected' => ['width' => 320, 'height' => 180],
      ],
      'short automatic height' => [
        'input' => ['content_height' => 40],
        'expected' => ['width' => 350, 'height' => 'auto'],
      ],
      'short percentage height' => [
        'input' => ['height' => '80%', 'content_height' => 40],
        'expected' => ['width' => 350, 'height' => 'auto'],
      ],
    ];
    // Keep the automatic height and its percentage comparison in one test.
    $short_content = array_slice($cases, -2, NULL, TRUE);
    $cases = array_slice($cases, 0, -2, TRUE);
    foreach ([TRUE, FALSE] as $modal) {
      $mode = $modal ? 'modal' : 'non-modal';
      foreach ($cases as $label => $case) {
        yield "$mode, $label" => [$modal, [$label => $case]];
      }
      yield "$mode, short content" => [$modal, $short_content];
    }
  }

  /**
   * Tests replacing and retaining sizes across successive AJAX dialog steps.
   */
  public function testDialogSizeSteps(): void {
    $this->drupalGet('ajax-test/dialog-sizes', [
      'query' => [
        'options' => Json::encode(['width' => '350px', 'maxHeight' => '400px']),
      ],
    ]);
    foreach ([400, 300, '95%'] as $step => $height) {
      $message = 'Dialog step ' . ($step + 1);
      $this->clickLink($step === 0 ? 'Open dialog' : 'Next step');
      $this->assertSession()->assertWaitOnAjaxRequest();
      $dialog = $this->assertSession()->elementExists('css', '.ui-dialog');
      $this->assertStringContainsString('width: 350px;', $dialog->getAttribute('style'), $message);
      if ($height === '95%') {
        $height = floor(0.95 * $this->getAvailableViewportHeight());
      }
      $this->assertEqualsWithDelta($height, $this->getDialogHeight(), 1, $message);
      $this->assertSession()->assertVisibleInViewport('css', '.ui-dialog-title');
    }
  }

  /**
   * Returns the viewport height available to the dialog, in pixels.
   */
  protected function getAvailableViewportHeight(): float {
    return $this->getSession()->evaluateScript(
      'jQuery(window).height() - Drupal.displace.offsets.top'
      . ' - Drupal.displace.offsets.bottom',
    );
  }

  /**
   * Returns the rendered dialog height in pixels.
   */
  protected function getDialogHeight(): float {
    return $this->getSession()->evaluateScript(
      "document.querySelector('.ui-dialog').getBoundingClientRect().height",
    );
  }

}
