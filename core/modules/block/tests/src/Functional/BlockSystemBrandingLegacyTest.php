<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Functional;

use Drupal\Core\Extension\ThemeSettingsProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests legacy site_logo variable in the system branding block.
 */
#[Group('block')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
class BlockSystemBrandingLegacyTest extends BlockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'system'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'test_theme';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add the system branding block to the page.
    $this->drupalPlaceBlock('system_branding_block', ['region' => 'content', 'id' => 'site_branding']);
  }

  /**
   * Tests that using site_logo variable triggers deprecation and renders logo URL.
   */
  public function testLegacySiteLogoDeprecation(): void {
    $this->expectUserDeprecationMessage("'site_logo' is deprecated in drupal:12.1.0 and is removed in drupal:13.0.0. Use 'site_logo_element' instead. See https://www.drupal.org/node/3626551");
    $this->drupalGet('');
    $this->assertSession()->elementExists('xpath', '//div[@id="block-site-branding"]//a[@class="legacy-site-logo"]/img');
    $logo_url = \Drupal::service(ThemeSettingsProvider::class)->getSetting('logo.url');
    $this->assertSession()->elementAttributeContains('xpath', '//div[@id="block-site-branding"]//a[@class="legacy-site-logo"]/img', 'src', $logo_url);
  }

}
