<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Kernel\Block;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\KernelTests\KernelTestBase;
use Drupal\system\Hook\SystemThemeHooks;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests preprocessing variables for the system branding block.
 */
#[Group('Block')]
#[RunTestsInSeparateProcesses]
class SystemBrandingBlockPreprocessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
    \Drupal::service('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
  }

  /**
   * Tests preprocessBlock for system_branding_block.
   */
  public function testPreprocessBlock(): void {
    $this->config('system.site')
      ->set('name', 'Drupal Test Site')
      ->set('slogan', 'Test Slogan')
      ->save();

    /** @var \Drupal\Core\Block\BlockPluginInterface $plugin */
    $plugin = $this->container->get('plugin.manager.block')->createInstance('system_branding_block', [
      'use_site_logo' => TRUE,
      'use_site_name' => TRUE,
      'use_site_slogan' => TRUE,
    ]);

    $build = $plugin->build();
    $variables = [
      'base_plugin_id' => 'system_branding_block',
      'content' => $build,
    ];

    \Drupal::service(SystemThemeHooks::class)->preprocessBlock($variables);

    $logo_url = \Drupal::service(ThemeSettingsProvider::class)->getSetting('logo.url');
    $this->assertSame($logo_url, $variables['site_logo']);
    $this->assertIsArray($variables['site_logo_element']);
    $this->assertSame("'site_logo' is deprecated in drupal:12.1.0 and is removed in drupal:13.0.0. Use 'site_logo_element' instead. See https://www.drupal.org/node/3626551", $variables['deprecations']['site_logo']);
    $this->assertSame('Drupal Test Site', $variables['site_name']);
    $this->assertSame(['#markup' => 'Test Slogan'], $variables['site_slogan']);

    // Test with branding elements disabled.
    $plugin = $this->container->get('plugin.manager.block')->createInstance('system_branding_block', [
      'use_site_logo' => FALSE,
      'use_site_name' => FALSE,
      'use_site_slogan' => FALSE,
    ]);

    $build = $plugin->build();
    $variables = [
      'base_plugin_id' => 'system_branding_block',
      'content' => $build,
    ];

    \Drupal::service(SystemThemeHooks::class)->preprocessBlock($variables);

    $this->assertSame('', $variables['site_logo']);
    $this->assertSame('', $variables['site_logo_element']);
    $this->assertSame('', $variables['site_name']);
    $this->assertSame('', $variables['site_slogan']);
  }

}
