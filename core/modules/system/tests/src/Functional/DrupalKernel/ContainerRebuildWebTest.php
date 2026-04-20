<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\DrupalKernel;

use Drupal\Component\DependencyInjection\Container;
use Drupal\Core\DependencyInjection\DumpedContainer;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Ensures that the container rebuild works as expected.
 */
#[Group('DrupalKernel')]
#[RunTestsInSeparateProcesses]
class ContainerRebuildWebTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['service_provider_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Sets a different deployment identifier.
   */
  public function testSetContainerRebuildWithDifferentDeploymentIdentifier(): void {
    $assert = $this->assertSession();

    // Ensure the parameter is not set.
    $this->drupalGet('<front>');
    $assert->responseHeaderEquals('container_rebuild_indicator', NULL);

    $this->writeSettings([
      'settings' => [
        'deployment_identifier' => (object) [
          'value' => 'new-identifier',
          'required' => TRUE,
        ],
      ],
    ]);

    $this->drupalGet('<front>');

    $assert->responseHeaderEquals('container_rebuild_indicator', 'new-identifier');
  }

  /**
   * Tests container invalidation.
   */
  public function testContainerInvalidation(): void {
    $assert = $this->assertSession();

    // Verify that the default installation uses the PhpDumper-based container
    // and that the compiled container file exists on disk.
    $container = \Drupal::getContainer();
    $this->assertInstanceOf(DumpedContainer::class, $container);
    $container_file = (new \ReflectionClass($container))->getFileName();
    $this->assertFileExists($container_file);

    // Ensure that parameter is not set.
    $this->drupalGet('<front>');
    $assert->responseHeaderEquals('container_rebuild_test_parameter', NULL);

    // Ensure that after setting the parameter, without a container rebuild the
    // parameter is still not set.
    $this->writeSettings([
      'settings' => [
        'container_rebuild_test_parameter' => (object) [
          'value' => 'rebuild_me',
          'required' => TRUE,
        ],
      ],
    ]);

    $this->drupalGet('<front>');
    $assert->responseHeaderEquals('container_rebuild_test_parameter', NULL);

    // Ensure that after container invalidation the parameter is set.
    \Drupal::service('kernel')->invalidateContainer();
    $this->drupalGet('<front>');
    $assert->responseHeaderEquals('container_rebuild_test_parameter', 'rebuild_me');

    $container_file = (new \ReflectionClass(\Drupal::getContainer()))->getFileName();
    $this->assertFileExists($container_file);
  }

  /**
   * Tests that the status report page renders the opcache container check.
   *
   * This covers the SystemRequirementsHooks opcache check which inspects the
   * compiled container file. It should render without errors regardless of
   * whether opcache is enabled or the container file is cached.
   *
   * It also verifies that when the legacy array container is used (no PHP file
   * on disk), the OPcode container check is not shown.
   */
  #[RequiresPhpExtension('Zend OPcache')]
  public function testOpcacheStatusReportCheck(): void {
    $admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->drupalLogin($admin_user);

    // Ensure the container is a DumpedContainer with a file on disk.
    $container = \Drupal::getContainer();
    $this->assertInstanceOf(DumpedContainer::class, $container);
    $container_file = (new \ReflectionClass($container))->getFileName();
    $this->assertFileExists($container_file);

    // Visit the status report page and verify it renders without errors.
    $this->drupalGet(Url::fromRoute('system.status'));
    $this->assertSession()->statusCodeEquals(200);
    // The page should contain the PHP OPcode caching section.
    $this->assertSession()->pageTextContains('PHP OPcode caching');
    // The OPcode container check should be present for DumpedContainer.
    $this->assertSession()->pageTextContains('PHP OPcode container');

    // Switch to the legacy array container.
    $this->writeSettings([
      'settings' => [
        'container_base_class' => (object) [
          'value' => Container::class,
          'required' => TRUE,
        ],
      ],
    ]);

    // Rebuild the container via rebuild.php so the new setting takes effect.
    $this->drupalGet(Url::fromUri('base:core/rebuild.php'));
    $this->assertSession()->statusCodeEquals(200);

    // Visit the status report page again.
    $this->drupalGet(Url::fromRoute('system.status'));
    $this->assertSession()->statusCodeEquals(200);
    // The OPcode caching section should still be present.
    $this->assertSession()->pageTextContains('PHP OPcode caching');
    // But the OPcode container check should NOT be present because the legacy
    // array container has no PHP file on disk to cache in OPcache.
    $this->assertSession()->pageTextNotContains('PHP OPcode container');
  }

}
