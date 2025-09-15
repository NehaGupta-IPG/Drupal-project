<?php

declare(strict_types=1);

namespace Drupal\Tests\serialization\Unit\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Drupal\serialization\Normalizer\NormalizerBase.
 */
#[CoversClass(NormalizerBase::class)]
#[Group('serialization')]
class NormalizerBaseTest extends UnitTestCase {

  /**
   * Tests the supportsNormalization method.
   *
   * @param bool $expected_return
   *   The expected boolean return value from supportNormalization.
   * @param mixed $data
   *   The data passed to supportsNormalization.
   * @param string $supported_types
   *   (optional) The supported interface or class to set on the normalizer.
   */
  #[DataProvider('providerTestSupportsNormalization')]
  public function testSupportsNormalization($expected_return, $data, $supported_types = NULL): void {
    $normalizer_base = new TestNormalizerBase();

    if (isset($supported_types)) {
      $normalizer_base->setSupportedTypes($supported_types);
    }

    $this->assertSame($expected_return, $normalizer_base->supportsNormalization($data));
  }

  /**
   * Data provider for testSupportsNormalization.
   *
   * @return array
   *   An array of provider data for testSupportsNormalization.
   */
  public static function providerTestSupportsNormalization() {
    return [
      // Something that is not an object should return FALSE immediately.
      [FALSE, []],
      // An object with no class set should return FALSE.
      [FALSE, new \stdClass()],
      // Set a supported Class.
      [TRUE, new \stdClass(), 'stdClass'],
      // Set a supported interface.
      [TRUE, new \RecursiveArrayIterator(), 'RecursiveIterator'],
      // Set a different class.
      [FALSE, new \stdClass(), 'ArrayIterator'],
      // Set a different interface.
      [FALSE, new \stdClass(), 'RecursiveIterator'],
    ];
  }

  /**
   * Tests the supportsDenormalization method.
   *
   * @param bool $expected_return
   *   The expected boolean return value from supportDenormalization.
   * @param string|int|float $type
   *   An interface, class or value to check for denormalization support.
   * @param string|string[] $format
   *   (optional) An interface, class or value the denormalization is supported
   *   for.
   *
   * @dataProvider providerTestSupportsDenormalization
   */
  public function testSupportsDenormalization($expected_return, $type, $format = NULL) {
    /** @var \Drupal\Tests\serialization\Unit\Normalizer\TestNormalizerBase $normalizer_base */
    $normalizer_base = $this->getMockForAbstractClass(TestNormalizerBase::class);

    if (isset($format)) {
      $normalizer_base->setSupportedInterfaceOrClass($format);
    }

    $this->assertSame($expected_return, $normalizer_base->supportsDenormalization(NULL, $type));
  }

  /**
   * Data provider for testSupportsDenormalization.
   *
   * @return array[]
   *   An array of provider data for testSupportsDenormalization.
   */
  public function providerTestSupportsDenormalization() {
    $formats = [
      1.10,
      '123',
      '1.13',
      'Ns\Of\MissingClass',
      'stdClass',
      NormalizerBase::class,
    ];

    return [
      [FALSE, '1.10', $formats],
      [TRUE, 1.10, $formats],
      [FALSE, 123, $formats],
      [TRUE, '123', $formats],
      [FALSE, 1.13, $formats],
      [TRUE, '1.13', $formats],
      // A non-existent class must fail.
      [FALSE, 'Ns\Of\MissingClass', $formats],
      // Check the same class.
      [TRUE, \stdClass::class, $formats],
      // Check the instance.
      [TRUE, new \stdClass(), $formats],
      // Check the subclass.
      [TRUE, TestNormalizerBase::class, $formats],
    ];
  }
}

/**
 * Testable class for NormalizerBase.
 */
class TestNormalizerBase extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string[]
   */
  protected array $supportedTypes = ['*' => FALSE];

  /**
   * Sets the supported types.
   *
   * @param string $supported_types
   *   The class name to set.
   */
  public function setSupportedTypes($supported_types): void {
    $this->supportedTypes = [$supported_types => FALSE];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return $this->supportedTypes;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|NULL {
    return NULL;
  }

}
