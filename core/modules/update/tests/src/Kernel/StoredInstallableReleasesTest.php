<?php

namespace Drupal\Tests\update\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\update\UpdateManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;

/**
 * Tests the releases stored in update_calculate_project_data().
 *
 * @group update
 */
class StoredInstallableReleasesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'update', 'update_test'];

  /**
   * The mocked HTTP client that returns metadata about available updates.
   *
   * We need to preserve this as a class property so that we can re-inject it
   * into the container when a rebuild is triggered by module installation.
   *
   * @var \GuzzleHttp\Client
   *
   * @see ::register()
   */
  private $client;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The Update module's default configuration must be installed for our
    // fake release metadata to be fetched.
    $this->installConfig('update');
    $this->installConfig('update_test');
    $this->setReleaseMetadata(__DIR__ . '/../../fixtures/release-history/drupal_8.2_8.1_8.0.xml');

  }

  /**
   * Sets the current (running) version of core, as known to the Update module.
   *
   * @param string $version
   *   The current version of core.
   */
  protected function setCoreVersion(string $version): void {
    $this->config('update_test.settings')
      ->set('system_info.#all.version', $version)
      ->save();
  }

  /**
   * Sets the release metadata file to use when fetching available updates.
   *
   * @param string $file
   *   The path of the XML metadata file to use.
   */
  protected function setReleaseMetadata(string $file): void {
    $metadata = Utils::tryFopen($file, 'r');
    $response = new Response(200, [], Utils::streamFor($metadata));
    $handler = new MockHandler([$response]);
    $this->client = new Client([
      'handler' => HandlerStack::create($handler),
    ]);
    $this->container->set('http_client', $this->client);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    // If we previously set up a mock HTTP client in ::setReleaseMetadata(),
    // re-inject it into the container.
    if ($this->client) {
      $container->set('http_client', $this->client);
    }
  }

  /**
   * Data provider for testStoredReleases().
   *
   * All installed versions are from the XML except '8.1.4', to test for the
   * case if the currently installed version of Drupal core is not present in
   * the XML.
   *
   * @return array[]
   *   Test cases.
   */
  public function providerStoredReleases(): array {
    return [
      '8.0.0 installed' => [
        'installed_version' => '8.0.0',
        'expected_project_status' => UpdateManagerInterface::NOT_SECURE,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.1',
          '8.0.0',
        ],
      ],
      '8.0.1 installed' => [
        'installed_version' => '8.0.1',
        'expected_project_status' => UpdateManagerInterface::NOT_SUPPORTED,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.1',
          '8.0.1',
        ],
      ],
      '8.0.2 installed' => [
        'installed_version' => '8.0.2',
        'expected_project_status' => UpdateManagerInterface::NOT_SUPPORTED,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.1',
          '8.0.2',
        ],
      ],
      '8.1.0 installed' => [
        'installed_version' => '8.1.0',
        'expected_project_status' => UpdateManagerInterface::NOT_SECURE,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.1',
          '8.1.0',
        ],
      ],
      '8.1.1 installed' => [
        'installed_version' => '8.1.1',
        'expected_project_status' => UpdateManagerInterface::NOT_CURRENT,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.1',
        ],
      ],
      '8.1.2 installed, revoked' => [
        'installed_version' => '8.1.2',
        'expected_project_status' => UpdateManagerInterface::REVOKED,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.2',
        ],
      ],
      '8.1.3 installed' => [
        'installed_version' => '8.1.3',
        'expected_project_status' => UpdateManagerInterface::NOT_CURRENT,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
        ],
      ],
      // Test a case where the installed version is not in the update XML.
      '8.1.4 installed, not in XML' => [
        'installed_version' => '8.1.4',
        'expected_project_status' => UpdateManagerInterface::NOT_CURRENT,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.1.3',
          '8.1.1',
        ],
      ],
      '8.2.0 installed' => [
        'installed_version' => '8.2.0',
        'expected_project_status' => UpdateManagerInterface::NOT_SECURE,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
          '8.2.0',
        ],
      ],
      '8.2.1 installed' => [
        'installed_version' => '8.2.1',
        'expected_project_status' => UpdateManagerInterface::NOT_CURRENT,
        'expected_releases' => [
          '8.2.3',
          '8.2.1',
        ],
      ],
      '8.2.2 installed' => [
        'installed_version' => '8.2.2',
        'expected_project_status' => UpdateManagerInterface::REVOKED,
        'expected_releases' => [
          '8.2.3',
          '8.2.2',
        ],
      ],
      '8.2.3 installed' => [
        'installed_version' => '8.2.3',
        'expected_project_status' => UpdateManagerInterface::CURRENT,
        'expected_releases' => [
          '8.2.3',
        ],
      ],
    ];
  }

  /**
   * Tests that all installable releases are revealed by the update system.
   *
   * @param string $installed_version
   *   The installed version of Drupal core.
   * @param int $expected_project_status
   *   The expected project status as set by 'update_calculate_project_data()'.
   * @param array $expected_releases
   *   The expected project releases as set by
   *   'update_calculate_project_data()'.
   *
   * @dataProvider providerStoredReleases
   */
  public function testStoredReleases(string $installed_version, int $expected_project_status, array $expected_releases): void {
    $this->setCoreVersion($installed_version);
    $available = update_get_available(TRUE);
    $project_data = update_calculate_project_data($available);
    $this->assertSame($project_data['drupal']['status'], $expected_project_status);
    foreach ($expected_releases as $version) {
      $this->assertArrayHasKey($version, $project_data['drupal']['releases']);
      // The only case where an unpublished release should be in the stored in
      // releases is if it is the installed version.
      if ($expected_project_status === UpdateManagerInterface::REVOKED && $project_data['drupal']['releases'][$version]['version'] === $installed_version) {
        $this->assertSame($project_data['drupal']['releases'][$version]['status'], 'unpublished');
      }
      else {
        $this->assertSame($project_data['drupal']['releases'][$version]['status'], 'published');
      }
      $this->assertSame($project_data['drupal']['releases'][$version]['version'], $version);
    }
  }

}
