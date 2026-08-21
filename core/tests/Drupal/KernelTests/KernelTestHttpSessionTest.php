<?php

declare(strict_types=1);

namespace Drupal\KernelTests;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Tests\HttpKernelUiHelperTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests use of sessions with HTTP requests in a kernel test.
 *
 * Note that this doesn't use the session test routes from session_test module,
 * as that test module's session services clash with use of sessions in a Kernel
 * test.
 */
#[CoversTrait(HttpKernelUiHelperTrait::class)]
#[Group('PHPUnit')]
#[Group('Test')]
#[Group('KernelTests')]
#[RunTestsInSeparateProcesses]
class KernelTestHttpSessionTest extends KernelTestBase implements ServiceModifierInterface {

  use SessionTestRoutesTrait;

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $this->alterSessionServices($container);
  }

  /**
   * Tests data can be stored in the sesssion.
   */
  public function testSession(): void {
    $this->drupalGet('/session-test/set/cake');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('cake');

    // Check that the session data was stored in the sessions table.
    $query = $this->container->get('database')->select('sessions')->countQuery();
    $result = $query->execute()->fetchField();
    $this->assertEquals(1, $result);

    // Check that a new request loads the same session.
    $this->drupalGet('/session-test/get');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextContains('cake');

    // Reset the browser cookies so we get a new session: a new request does not
    // get shown the session data.
    $this->getSession()->reset();
    $this->drupalGet('/session-test/get');
    $this->assertSession()->statusCodeEquals(Response::HTTP_OK);
    $this->assertSession()->pageTextNotContains('cake');
  }

}
