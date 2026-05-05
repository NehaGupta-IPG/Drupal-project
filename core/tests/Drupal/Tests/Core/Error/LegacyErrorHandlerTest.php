<?php

declare(strict_types=1);

namespace Drupal\Tests\Core\Error;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that deprecated error handler functions trigger deprecation notices.
 */
#[Group('Error')]
#[Group('legacy')]
class LegacyErrorHandlerTest extends UnitTestCase {

  /**
   * Tests that _drupal_error_handler() is deprecated.
   */
  public function testDrupalErrorHandlerDeprecation(): void {
    $this->expectUserDeprecationMessage('_drupal_error_handler() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Error handling is now managed by \Drupal\Core\Error\DrupalErrorHandler. See https://www.drupal.org/project/drupal/issues/3000229');
    _drupal_error_handler(E_USER_NOTICE, 'Test notice', __FILE__, __LINE__);
  }

  /**
   * Tests that _drupal_exception_handler_additional() is deprecated.
   */
  public function testDrupalExceptionHandlerAdditionalDeprecation(): void {
    $this->expectUserDeprecationMessage('_drupal_exception_handler_additional() is deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use \Drupal\Core\Error\DrupalExceptionRenderer::renderException() instead. See https://www.drupal.org/project/drupal/issues/3000229');
    // Suppress any output from this function.
    ob_start();
    _drupal_exception_handler_additional(
      new \Exception('Original'),
      new \Exception('Additional'),
    );
    ob_end_clean();
  }

}
