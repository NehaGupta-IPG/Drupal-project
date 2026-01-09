<?php

declare(strict_types=1);

namespace Drupal\Tests\media\Kernel;

use Drupal\media\OEmbed\ResourceFetcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the oEmbed resource fetcher service.
 */
#[CoversClass(ResourceFetcher::class)]
#[Group('media')]
#[RunTestsInSeparateProcesses]
class ResourceFetcherKernelTest extends MediaKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['media_test_oembed'];

  /**
   * Tests that hook_oembed_resource_data_alter() is invoked.
   */
  public function testResourceDataAlter(): void {
    // Get the resource.
    $resource_url = $this->container->get('media.oembed.resource_fetcher')
      ->fetchResource('https://publish.twitter.com/oembed?url=https://twitter.com/Dries/status/999985431595880448');

    // Check media_test_oembed_oembed_resource_data_alter
    // to see the hook implementation.
    $this->assertEquals(600, $resource_url->getWidth());
  }

}
