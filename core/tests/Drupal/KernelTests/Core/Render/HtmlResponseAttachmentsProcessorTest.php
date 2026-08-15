<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Render;

use Drupal\KernelTests\AssertContentTrait;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests \Drupal\Core\Render\HtmlResponseAttachmentsProcessor.
 */
#[Group('Render')]
#[RunTestsInSeparateProcesses]
class HtmlResponseAttachmentsProcessorTest extends KernelTestBase {

  use AssertContentTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Tests that core/drupalSettings is always loaded on HTML pages.
   *
   * If no library depends on core/drupalSettings, the window.drupalSettings
   * global is not created, leaving it open to being faked through DOM
   * clobbering.
   *
   * @see https://www.drupal.org/project/drupal/issues/3591821
   */
  public function testDrupalSettingsAlwaysLoaded(): void {
    // Stark is a theme that does not depend on core/drupalSettings.
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();

    // Render a bare HTML page without attaching any library that depends on
    // core/drupalSettings.
    $renderer = $this->container->get('bare_html_page_renderer');
    $response = $renderer->renderBarePage(['#markup' => 'content'], '', 'maintenance_page');

    // The library should be added to the response's attachments.
    $this->assertContains('core/drupalSettings', $response->getAttachments()['library']);

    // The loader that creates the window.drupalSettings global and the script
    // containing the settings should both be present in the output.
    $this->setRawContent($response->getContent());
    $this->assertRaw('drupalSettingsLoader.js');
    $this->assertNotSame([], $this->getDrupalSettings());
  }

}
