<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Element;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Tests AJAX progress screen reader announcement defaults.
 *
 * @group system
 */
class AjaxProgressAnnounceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'filter'];

  /**
   * Tests default progress settings for screen reader announcements.
   */
  public function testAjaxProgressAnnounceDefaults(): void {
    $element = [
      '#id' => 'ajax-progress-test',
      '#type' => 'submit',
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'progress' => [],
      ],
    ];

    // Simulate preRenderAjaxForm to attach AJAX settings.
    $element = RenderElementBase::preRenderAjaxForm($element);

    $settings = $element['#attached']['drupalSettings']['ajax'][$element['#id']]['progress'];

    // Ensure default values are set when progress is defined.
    $this->assertArrayHasKey('announce', $settings);
    $this->assertArrayHasKey('announceDelay', $settings);
    $this->assertArrayHasKey('announceIntervalTime', $settings);
    $this->assertArrayHasKey('announceMessage', $settings);

    // Check default values.
    $this->assertTrue($settings['announce']);
    $this->assertEquals(1000, $settings['announceDelay']);
    $this->assertEquals(2000, $settings['announceIntervalTime']);
    $this->assertEquals('Busy', (string) $settings['announceMessage']);
  }

}
