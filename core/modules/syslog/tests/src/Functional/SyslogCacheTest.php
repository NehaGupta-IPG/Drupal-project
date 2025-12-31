<?php

declare(strict_types=1);

namespace Drupal\Tests\syslog\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests syslog settings.
 */
#[Group('syslog')]
#[RunTestsInSeparateProcesses]
class SyslogCacheTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['syslog_cache'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests a cache backend with logging.
   */
  public function testLoggingCacheBackend(): void {
    $settings['settings']['cache']['default'] = (object) [
      'value' => 'cache.backend.syslog_cache',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
    $this->rebuildContainer();
    // Test logger serialization.
    serialize($this->container->get('logger.syslog'));
  }

}
