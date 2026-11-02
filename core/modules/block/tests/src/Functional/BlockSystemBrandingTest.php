<?php

declare(strict_types=1);

namespace Drupal\Tests\block\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\block\Entity\Block;

/**
 * Tests branding block display.
 */
#[Group('block')]
#[RunTestsInSeparateProcesses]
class BlockSystemBrandingTest extends BlockTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'system'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Set a site slogan.
    $this->config('system.site')
      ->set('slogan', 'Community plumbing')
      ->save();
    // Add the system branding block to the page.
    $this->drupalPlaceBlock('system_branding_block', ['region' => 'header', 'id' => 'site_branding']);
  }

  /**
   * Tests system branding block configuration.
   */
  public function testSystemBrandingSettings(): void {
    $theme_path = \Drupal::service('extension.list.theme')->getPath($this->defaultTheme);
    $site_logo_xpath = file_exists($theme_path . '/logo.svg') ? '//div[@id="block-site-branding"]/a/svg' : '//div[@id="block-site-branding"]/a/img';
    $site_name_xpath = '//div[@id="block-site-branding"]/a[text() = "Drupal"]';
    $site_slogan_xpath = '//div[@id="block-site-branding"]/descendant::text()[last()]';

    // Set default block settings.
    $this->drupalGet('');

    // Test that all branding elements are displayed.
    $this->assertSession()->elementExists('xpath', $site_logo_xpath);
    $this->assertSession()->elementExists('xpath', $site_name_xpath);
    $this->assertSession()->elementExists('xpath', $site_slogan_xpath);
    $this->assertSession()->elementTextContains('xpath', $site_slogan_xpath, 'Community plumbing');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:system.site');

    // Be sure the slogan is XSS-filtered.
    $this->config('system.site')
      ->set('slogan', '<script>alert("Community carpentry");</script>')
      ->save();
    $this->drupalGet('');
    $this->assertSession()->elementTextEquals('xpath', $site_slogan_xpath, 'alert("Community carpentry");');
    $this->assertSession()->responseNotContains('<script>alert("Community carpentry");</script>');

    // Turn just the logo off.
    $block = Block::load('site_branding');
    $block->getPlugin()->setConfigurationValue('use_site_logo', 0);
    $block->save();
    $this->drupalGet('');

    // Re-test all branding elements.
    $this->assertSession()->elementNotExists('xpath', $site_logo_xpath);
    $this->assertSession()->elementExists('xpath', $site_name_xpath);
    $this->assertSession()->elementTextContains('xpath', $site_slogan_xpath, 'alert("Community carpentry");');
    $this->assertSession()->responseNotContains('<script>alert("Community carpentry");</script>');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:system.site');

    // Turn just the site name off.
    $block->getPlugin()->setConfigurationValue('use_site_logo', 1);
    $block->getPlugin()->setConfigurationValue('use_site_name', 0);
    $block->save();
    $this->drupalGet('');

    // Re-test all branding elements.
    $this->assertSession()->elementExists('xpath', $site_logo_xpath);
    $this->assertSession()->elementNotExists('xpath', $site_name_xpath);
    $this->assertSession()->elementTextContains('xpath', $site_slogan_xpath, 'alert("Community carpentry");');
    $this->assertSession()->responseNotContains('<script>alert("Community carpentry");</script>');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:system.site');

    // Turn just the site slogan off.
    $block->getPlugin()->setConfigurationValue('use_site_name', 1);
    $block->getPlugin()->setConfigurationValue('use_site_slogan', 0);
    $block->save();
    $this->drupalGet('');

    // Re-test all branding elements.
    $this->assertSession()->elementExists('xpath', $site_logo_xpath);
    $this->assertSession()->elementExists('xpath', $site_name_xpath);
    $this->assertSession()->elementTextNotContains('xpath', $site_slogan_xpath, 'Community carpentry');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:system.site');

    // Turn the site name and the site slogan off.
    $block->getPlugin()->setConfigurationValue('use_site_name', 0);
    $block->getPlugin()->setConfigurationValue('use_site_slogan', 0);
    $block->save();
    $this->drupalGet('');

    // Re-test all branding elements.
    $this->assertSession()->elementExists('xpath', $site_logo_xpath);
    $this->assertSession()->elementNotExists('xpath', $site_name_xpath);
    $this->assertSession()->elementTextNotContains('xpath', $site_slogan_xpath, 'Community carpentry');
    $this->assertSession()->responseHeaderContains('X-Drupal-Cache-Tags', 'config:system.site');
  }

  /**
   * Tests SVG logo containing Twig syntax is not compiled as Twig template.
   */
  public function testSvgLogoWithTwigSyntax(): void {
    $svg_content = '<svg viewBox="0 0 10 10"><text>{{ 1 + 1 }}</text><text>{% if true %}twig_tag{% endif %}</text></svg>';
    $logo_path = 'public://twig_test_logo.svg';
    \Drupal::service('file_system')->saveData($svg_content, $logo_path);

    $this->config('stark.settings')
      ->set('logo.use_default', FALSE)
      ->set('logo.path', $logo_path)
      ->save();

    $this->drupalGet('');
    $this->assertSession()->responseContains('<svg aria-label="Home" viewBox="0 0 10 10"><text>{{ 1 + 1 }}</text><text>{% if true %}twig_tag{% endif %}</text></svg>');
    $this->assertSession()->responseNotContains('<text>2</text>');
  }

  /**
   * Tests logo file names with spaces for both SVG and raster images.
   */
  public function testLogoWithSpacesInFilename(): void {
    // 1. Test SVG logo with space in the filename.
    $svg_content = '<svg viewBox="0 0 10 10"><circle cx="5" cy="5" r="5"/></svg>';
    $svg_logo_path = 'public://logo with spaces.svg';
    \Drupal::service('file_system')->saveData($svg_content, $svg_logo_path);

    $this->config('stark.settings')
      ->set('logo.use_default', FALSE)
      ->set('logo.path', $svg_logo_path)
      ->save();

    $this->drupalGet('');
    $this->assertSession()->responseContains('<svg aria-label="Home" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5"/></svg>');

    // 2. Test PNG logo with space in the filename.
    $png_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    $png_logo_path = 'public://logo with spaces.png';
    \Drupal::service('file_system')->saveData($png_data, $png_logo_path);

    $this->config('stark.settings')
      ->set('logo.use_default', FALSE)
      ->set('logo.path', $png_logo_path)
      ->save();

    $this->drupalGet('');
    $this->assertSession()->elementExists('xpath', '//div[@id="block-site-branding"]//img[contains(@src, "logo%20with%20spaces.png")]');
  }

  /**
   * Tests SVG logo accessible name and invalid logo handling.
   */
  public function testLogoAccessibilityAndInvalidImage(): void {
    // 1. Default SVG logo has aria-label="Home".
    $this->drupalGet('');
    $this->assertSession()->elementAttributeContains('xpath', '//div[@id="block-site-branding"]//a[@rel="home"]/svg', 'aria-label', 'Home');

    // 2. Custom SVG logo without aria-label gets aria-label="Home".
    $svg_content = '<svg viewBox="0 0 10 10"><circle cx="5" cy="5" r="5"/></svg>';
    $svg_logo_path = 'public://accessible_logo.svg';
    \Drupal::service('file_system')->saveData($svg_content, $svg_logo_path);

    $this->config('stark.settings')
      ->set('logo.use_default', FALSE)
      ->set('logo.path', $svg_logo_path)
      ->save();

    $this->drupalGet('');
    $this->assertSession()->elementAttributeContains('xpath', '//div[@id="block-site-branding"]//a[@rel="home"]/svg', 'aria-label', 'Home');

    // 3. Custom SVG logo with existing aria-label preserves its label.
    $svg_custom_label = '<svg aria-label="Custom site logo" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5"/></svg>';
    $svg_custom_path = 'public://custom_label_logo.svg';
    \Drupal::service('file_system')->saveData($svg_custom_label, $svg_custom_path);

    $this->config('stark.settings')
      ->set('logo.path', $svg_custom_path)
      ->save();

    $this->drupalGet('');
    $this->assertSession()->elementAttributeContains('xpath', '//div[@id="block-site-branding"]//a[@rel="home"]/svg', 'aria-label', 'Custom site logo');

    // 4. Invalid/non-existent SVG path does not render an empty <a rel="home">.
    $this->config('stark.settings')
      ->set('logo.path', 'public://non_existent.svg')
      ->save();

    $this->drupalGet('');
    $this->assertSession()->elementNotExists('xpath', '//div[@id="block-site-branding"]//a[@rel="home"]/svg');
    $this->assertSession()->elementNotExists('xpath', '//div[@id="block-site-branding"]//a[@rel="home" and not(text())]');

    // 5. Invalid/non-existent raster image path does not render an empty <a rel="home">.
    $this->config('stark.settings')
      ->set('logo.path', 'public://non_existent.png')
      ->save();

    $this->drupalGet('');
    $this->assertSession()->elementNotExists('xpath', '//div[@id="block-site-branding"]//a[@rel="home"]/img');
    $this->assertSession()->elementNotExists('xpath', '//div[@id="block-site-branding"]//a[@rel="home" and not(text())]');

    // 6. When the site name is disabled and the logo is invalid, no <a rel="home"> at all is rendered.
    $block = Block::load('site_branding');
    $block->getPlugin()->setConfigurationValue('use_site_name', 0);
    $block->save();

    $this->drupalGet('');
    $this->assertSession()->elementNotExists('xpath', '//div[@id="block-site-branding"]//a[@rel="home"]');
  }

}
