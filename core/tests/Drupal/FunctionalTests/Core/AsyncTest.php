<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Core;

use Drupal\Core\State\StateInterface;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that async tasks can go beyond the request.
 */
#[Group('async')]
#[RunTestsInSeparateProcesses]
class AsyncTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'test_async',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A task runs beyond the request.
   */
  public function testTaskRunsBeyondRequest(): void {
    $state = $this->container->get(StateInterface::class);

    $state->set("async", FALSE);

    $this->drupalGet('/test/async/task');

    $this->assertTrue($state->get("async"));
  }

}
