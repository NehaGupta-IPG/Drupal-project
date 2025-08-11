<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Serialization;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Yaml serialization implementation.
 */
#[CoversClass(Yaml::class)]
#[Group('Drupal')]
#[Group('Serialization')]
class YamlTest extends YamlTestBase {

  /**
   * Tests encoding and decoding basic data structures.
   *
   * @legacy-covers ::encode
   * @legacy-covers ::decode
   */
  #[DataProvider('providerEncodeDecodeTests')]
  public function testEncodeDecode(array $data): void {
    $this->assertSame($data, Yaml::decode(Yaml::encode($data)));
  }

  /**
   * Tests decoding YAML node anchors.
   */
  #[DataProvider('providerDecodeTests')]
  public function testDecode($string, $data): void {
    $this->assertSame($data, Yaml::decode($string));
  }

  /**
   * Tests our encode settings.
   */
  public function testEncode(): void {
    // cSpell:disable
    $this->assertSame('foo:
  bar: \'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus sapien ex, venenatis vitae nisi eu, posuere luctus dolor. Nullam convallis\'
', Yaml::encode(['foo' => ['bar' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vivamus sapien ex, venenatis vitae nisi eu, posuere luctus dolor. Nullam convallis']]));
    // cSpell:enable
  }

  /**
   * Tests get file extension.
   */
  public function testGetFileExtension(): void {
    $this->assertSame('yml', Yaml::getFileExtension());
  }

  /**
   * Tests that invalid YAML throws an exception.
   *
   * @legacy-covers ::decode
   */
  public function testError(): void {
    $this->expectException(InvalidDataTypeException::class);
    Yaml::decode('foo: [ads');
  }

  /**
   * Ensures that php object support is disabled.
   */
  public function testEncodeObjectSupportDisabled(): void {
    $this->expectException(InvalidDataTypeException::class);
    $this->expectExceptionMessage('Object support when dumping a YAML file has been disabled.');
    $object = new \stdClass();
    $object->foo = 'bar';
    Yaml::encode([$object]);
  }

  /**
   * Ensures that decoding PHP objects does not work in Symfony.
   */
  public function testDecodeObjectSupportDisabled(): void {
    $this->expectException(InvalidDataTypeException::class);
    $this->expectExceptionMessageMatches('/^Object support when parsing a YAML file has been disabled/');
    $yaml = <<<YAML
    obj: !php/object "O:8:\"stdClass\":1:{s:3:\"foo\";s:3:\"bar\";}"
    YAML;

    Yaml::decode($yaml);
  }

  /**
   * Tests decoding tags with no tag classes.
   *
   * @covers ::decodeTags
   */
  public function testDecodeTagsNoTagClasses(): void {
    $yaml = <<<YAML
    test: !test test
    message: !test {string: 'Your message has been sent.', options: {context: 'Contact form'}}
    YAML;

    $data = Yaml::decodeTags(Yaml::decode($yaml));
    $this->assertSame('test', $data['test']);
    $this->assertSame(['string' => 'Your message has been sent.', 'options' => ['context' => 'Contact form']], $data['message']);
  }

  /**
   * Tests decoding tags with tag classes.
   *
   * @covers ::decodeTags
   */
  public function testDecodeTagsWithTagClasses(): void {
    $test_class = new class {

      public function __construct(public readonly string $string = '', public readonly array $options = []) {
      }

    };

    $yaml = <<<YAML
    test: !test test
    message: !test {string: 'Your message has been sent.', options: {context: 'Contact form'}}
    YAML;

    $data = Yaml::decodeTags(Yaml::decode($yaml), ['test' => get_class($test_class)]);
    $this->assertInstanceOf(get_class($test_class), $data['test']);
    $this->assertInstanceOf(get_class($test_class), $data['message']);
    $this->assertSame('test', $data['test']->string);
    $this->assertSame([], $data['test']->options);
    $this->assertSame('Your message has been sent.', $data['message']->string);
    $this->assertSame('Contact form', $data['message']->options['context']);
  }

}
