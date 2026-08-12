<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\DependencyInjection\Dumper;

use Drupal\Component\DependencyInjection\Dumper\PhpArrayDumper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tests Drupal\Component\DependencyInjection\Dumper\PhpArrayDumper.
 */
#[CoversClass(PhpArrayDumper::class)]
#[Group('DependencyInjection')]
class PhpArrayDumperTest extends OptimizedPhpArrayDumperTest {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->machineFormat = FALSE;
    $this->dumperClass = '\Drupal\Component\DependencyInjection\Dumper\PhpArrayDumper';
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  protected static function serializeDefinition(array $service_definition): string|array {
    return $service_definition;
  }

  /**
   * {@inheritdoc}
   */
  protected static function getServiceCall($id, $invalid_behavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): string {
    if ($invalid_behavior !== ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE) {
      return sprintf('@?%s', $id);
    }

    return sprintf('@%s', $id);
  }

  /**
   * {@inheritdoc}
   */
  protected static function getParameterCall($name): string {
    return '%' . $name . '%';
  }

  /**
   * {@inheritdoc}
   */
  protected static function getRawValue($value): string|object {
    // In the human-readable format, strings starting with @ are escaped
    // by prepending another @.
    if ($value !== '' && $value[0] === '@') {
      return '@' . $value;
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function percentsEscapeProvider(): array {
    return [
      ['%foo%', '%%foo%%'],
      ['foo%bar%', 'foo%%bar%%'],
      ['%foo%bar', '%%foo%%bar'],
      ['%', '%'],
      ['%', '%%'],
      ['%%', '%%%'],
      ['%%', '%%%%'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function atSignEscapeProvider(): array {
    return [
      'at-prefixed string is escaped' => ['@@admin', '@admin'],
      'at-question string is escaped' => ['@@?foo', '@?foo'],
      'double at is escaped' => ['@@@bar', '@@bar'],
      'plain string passes through' => ['plain', 'plain'],
      'empty string passes through' => ['', ''],
    ];
  }

}
