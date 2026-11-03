<?php

declare(strict_types=1);

namespace Drupal\Tests\update\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\update\UpdateFetcher;
use Drupal\update\UpdateFetcherInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the shared site data is sent once per refresh, for core only.
 */
#[Group('update')]
#[RunTestsInSeparateProcesses]
class UpdateProcessorDataSharingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['update'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig('update');
  }

  /**
   * Tests the site data sent while processing fetch tasks.
   */
  public function testSendSiteData(): void {
    $GLOBALS['base_url'] = 'https://example.com/subdirectory';
    $this->config('update.settings')
      ->set('data_sharing.domain', TRUE)
      ->set('data_sharing.site_mail', FALSE)
      ->save();
    $fetcher = $this->createMock(UpdateFetcherInterface::class);
    $fetcher->method('getFetchBaseUrl')->willReturn(UpdateFetcher::UPDATE_DEFAULT_URL);
    $fetcher->expects($this->once())
      ->method('sendSiteData')
      ->with($this->anything(), $this->anything(), 'example.com', NULL);
    $this->container->set('update.fetcher', $fetcher);

    $processor = $this->container->get('update.processor');
    $processor->processFetchTask(['name' => 'drupal']);
    $processor->processFetchTask(['name' => 'views']);
  }

}
