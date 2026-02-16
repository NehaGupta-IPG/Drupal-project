<?php

declare(strict_types=1);

namespace Drupal\Tests\editor\Kernel;

use Drupal\editor\EditorXssFilter\Standard;
use Drupal\editor\EditorXssFilterInterface;
use Drupal\editor_test\EditorXssFilter\Insecure;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the editor XSS filter service.
 */
#[Group('editor')]
class EditorXssFilterServiceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['filter', 'editor', 'editor_test'];

  /**
   * Tests that the XSS filter service is properly registered.
   */
  public function testXssFilterServiceRegistration(): void {
    $service = $this->container->get('editor.xss_filter');

    // Verify it implements the interface.
    $this->assertInstanceOf(EditorXssFilterInterface::class, $service);

    // Verify the default implementation is Standard.
    $this->assertInstanceOf(Standard::class, $service);
  }

  /**
   * Tests that the XSS filter service can be overridden.
   */
  public function testXssFilterServiceOverride(): void {
    // Verify that Insecure filter also implements the interface.
    $insecure = new Insecure();
    $this->assertInstanceOf(EditorXssFilterInterface::class, $insecure);
  }

}