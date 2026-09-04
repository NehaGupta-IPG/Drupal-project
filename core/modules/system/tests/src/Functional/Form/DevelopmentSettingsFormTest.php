<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Form;

use Drupal\Core\Cache\NullBackend;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the development settings form without JavaScript.
 */
#[Group('Form')]
#[RunTestsInSeparateProcesses]
class DevelopmentSettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'dynamic_page_cache', 'page_cache'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $admin_user = $this->drupalCreateUser([
      'administer site configuration',
    ]);
    $this->drupalLogin($admin_user);
  }

  /**
   * Tests disabling cache bins which cache markup.
   */
  public function testDisabledRenderedOutputCacheBins(): void {
    self::assertFalse(\Drupal::getContainer()->has('cache.backend.null'));

    $this->drupalGet(Url::fromRoute('system.development_settings'));
    $this->getSession()->getPage()->checkField('Do not cache markup');
    $this->getSession()->getPage()->pressButton('Save settings');

    $this->drupalGet(Url::fromRoute('system.status'));
    $this->assertSession()->pageTextContains('Markup caching disabled');
    $this->assertSession()->linkExists('development settings page');

    $refreshed_container = $this->initKernel(Request::create('/'));
    self::assertTrue($refreshed_container->has('cache.backend.null'));
    $cache_bins = ['page', 'dynamic_page_cache', 'render'];
    foreach ($cache_bins as $cache_bin) {
      self::assertInstanceOf(NullBackend::class, $refreshed_container->get("cache.$cache_bin"));
    }
  }

}
