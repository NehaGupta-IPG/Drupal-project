<?php

declare(strict_types=1);

namespace Drupal\Tests\views\Unit\Plugin\argument_validator;

use Drupal\Tests\UnitTestCase;
use Drupal\views\Plugin\views\argument\Standard;
use Drupal\views\Plugin\views\argument_validator\None;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the None argument validator.
 */
#[CoversClass(None::class)]
#[Group('views')]
class NoneTest extends UnitTestCase {

  /**
   * Tests validation of the None argument.
   */
  #[DataProvider('providerValidateNone')]
  public function testValidateNone($expected, $argument, $argument_handler, $definition, $options): void {
    /** @var \Drupal\views\Plugin\views\argument\Standard $argument_handler */
    $argument_handler->definition = $definition;
    $argument_handler->options = $options;

    $validator = new None([], 'none_test', []);
    $validator->setArgument($argument_handler);
    $this->assertSame($expected, ($validator->validateArgument($argument)));
  }

  /**
   * Provides data for testValidateNone.
   */
  public static function providerValidateNone(): array {
    $argument_handler = new Standard([], 'standard', []);
    return [
      'must_not_be' => [
        FALSE,
        '',
        $argument_handler,
        [],
        ['must_not_be' => TRUE],
      ],
      'NULL' => [
        FALSE,
        NULL,
        $argument_handler,
        [],
        [],
      ],
      'empty string' => [
        FALSE,
        '',
        $argument_handler,
        [],
        [],
      ],
      'numeric, break phase, numeric' => [
        TRUE,
        3,
        $argument_handler,
        ['numeric' => TRUE],
        ['break_phase' => TRUE],
      ],
      'numeric, break phase, not numeric' => [
        FALSE,
        'string',
        $argument_handler,
        ['numeric' => TRUE],
        ['break_phase' => TRUE],
      ],
      'numeric, not break phase, numeric' => [
        TRUE,
        3,
        $argument_handler,
        ['numeric' => TRUE],
        [],
      ],
      'numeric, not break phase, not numeric' => [
        FALSE,
        'string',
        $argument_handler,
        ['numeric' => TRUE],
        [],
      ],
      'not numeric, break phase, numeric' => [
        TRUE,
        3,
        $argument_handler,
        [],
        ['break_phase' => TRUE],
      ],
      'not numeric, break phase, not numeric' => [
        TRUE,
        'string',
        $argument_handler,
        [],
        ['break_phase' => TRUE],
      ],
      'not numeric, not break phase, numeric' => [
        TRUE,
        3,
        $argument_handler,
        [],
        [],
      ],
      'not numeric, not break phase, not numeric' => [
        TRUE,
        'string',
        $argument_handler,
        [],
        [],
      ],
    ];
  }

}
