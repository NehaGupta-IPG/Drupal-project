<?php

declare(strict_types=1);

namespace Drupal\KernelTests\Core\Extension;

use Drupal\Core\Extension\ThemeSettingsProvider;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests theme settings resolution for favicon defaults.
 */
#[Group('extension')]
#[RunTestsInSeparateProcesses]
final class ThemeSettingsProviderTest extends KernelTestBase {

  /**
   * Modules to enable for this kernel test.
   *
   * @var string[]
   */
  protected static $modules = [
    'system',
  ];

  /**
   * Kernel settings applied during setup (before container build).
   *
   * @var array<string, mixed>
   */
  protected $settings = [
    // Allow discovery of test fixtures (themes/modules under /core/tests/...).
    'extension_discovery_scan_tests' => TRUE,
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure relative paths used by file_exists() resolve from Drupal root.
    chdir($this->root);

    // Provides system.theme.global defaults used by ThemeSettingsProvider.
    $this->installConfig(['system']);

    // Ensure the code path under test is active.
    $this->config('system.theme.global')
      ->set('features.favicon', TRUE)
      ->set('favicon.use_default', TRUE)
      ->save();

    // Install the fixture themes (discovery alone is not enough).
    $this->container->get('theme_installer')->install([
      'test_base_theme',
      'test_subtheme',
    ]);

    // Resolve fixture theme paths via extension discovery (Kernel-safe).
    $themes = $this->container->get('extension.list.theme')->getList();

    $this->assertArrayHasKey('test_base_theme', $themes, 'Base theme fixture was not discovered.');
    $this->assertArrayHasKey('test_subtheme', $themes, 'Subtheme fixture was not discovered.');

    // Create base theme favicon using the same relative path.
    $base_favicon_rel = $themes['test_base_theme']->getPath() . '/favicon.ico';

    if (!is_dir(dirname($base_favicon_rel))) {
      $this->fail('Base theme directory not found: ' . dirname($base_favicon_rel));
    }

    // Empty file is fine: ThemeSettingsProvider only checks file_exists().
    file_put_contents($base_favicon_rel, '');

    // Ensure subtheme does NOT have its own favicon.ico.
    $sub_favicon_rel = $themes['test_subtheme']->getPath() . '/favicon.ico';
    @unlink($sub_favicon_rel);

    // Clear provider memory-cache entry if kernel reuse happens.
    $this->container->get('cache.memory')->delete('theme_settings:test_subtheme');

    // Force extension discovery to rebuild its theme list in this kernel.
    $this->container->get('extension.list.theme')->reset();
  }

  /**
   * Ensures a subtheme uses its base theme favicon when configured to defaults.
   */
  public function testFaviconDefaultsToBaseTheme(): void {
    // Ensure relative paths resolve correctly here too.
    chdir($this->root);

    $themes = $this->container->get('extension.list.theme')->getList();

    $this->assertArrayHasKey('test_base_theme', $themes, 'Base theme fixture was not discovered.');
    $this->assertArrayHasKey('test_subtheme', $themes, 'Subtheme fixture was not discovered.');

    /** @var \Drupal\Core\Extension\ThemeSettingsProvider $provider */
    $provider = $this->container->get(ThemeSettingsProvider::class);

    $actual = $provider->getSetting('favicon.url', 'test_subtheme');

    $base_favicon_rel = $themes['test_base_theme']->getPath() . '/favicon.ico';
    $expected = $this->container
      ->get('file_url_generator')
      ->generateString($base_favicon_rel);

    $this->assertSame($expected, $actual);
  }

}
