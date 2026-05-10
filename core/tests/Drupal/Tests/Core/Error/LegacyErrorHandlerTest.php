<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that deprecated error handler functions trigger deprecation notices.
 */
#[Group('Error')]
#[Group('legacy')]
#[RunTestsInSeparateProcesses]
class LegacyErrorHandlerTest extends UnitTestCase {

  protected function setUp(): void {
    parent::setUp();
    require_once $this->getDrupalRoot() . '/core/includes/errors.inc';
  }

  /**
   * Tests that _drupal_error_handler() is deprecated.
   */
  public function testDrupalErrorHandlerDeprecation(): void {
    $this->expectUserDeprecationMessage('_drupal_error_handler() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Error handling is now managed by \Drupal\Core\Error\DrupalErrorHandler. See https://www.drupal.org/project/drupal/issues/3000229');
    // @phpstan-ignore-next-line
    _drupal_error_handler(E_USER_NOTICE, 'Test notice', __FILE__, __LINE__);
  }

  /**
   * Tests that _drupal_exception_handler_additional() is deprecated.
   */
  public function testDrupalExceptionHandlerAdditionalDeprecation(): void {
    $this->expectUserDeprecationMessage('_drupal_exception_handler_additional() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\DrupalExceptionRenderer::renderException() instead. See https://www.drupal.org/project/drupal/issues/3000229');
    // Suppress any output from this function.
    ob_start();
    // @phpstan-ignore-next-line
    _drupal_exception_handler_additional(
      new \Exception('Original'),
      new \Exception('Additional'),
    );
    ob_end_clean();
  }

}
