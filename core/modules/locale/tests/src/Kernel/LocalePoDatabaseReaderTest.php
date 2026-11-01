<?php

declare(strict_types=1);

namespace Drupal\Tests\locale\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\locale\PoDatabaseReader;
use Drupal\locale\StringInterface;
use Drupal\locale\StringStorageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\locale\PoDatabaseReader.
 */
#[Group('locale')]
#[RunTestsInSeparateProcesses]
class LocalePoDatabaseReaderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
  ];

  /**
   * The locale storage.
   */
  protected StringStorageInterface $storage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->storage = $this->container->get('locale.storage');
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->installSchema('locale', [
      'locales_location',
      'locales_source',
      'locales_target',
    ]);
  }

  /**
   * Tests that the reader returns strings sorted by source and context.
   */
  public function testReadItemSortsBySourceAndContext(): void {
    $strings = [
      ['source' => 'zebra', 'context' => ''],
      ['source' => 'apple', 'context' => 'context_x'],
      ['source' => 'apple', 'context' => 'context_a'],
      ['source' => 'mango', 'context' => ''],
    ];
    foreach ($strings as $values) {
      $string = $this->createString($values);
      $this->createTranslation($string);
    }

    $expected = [
      ['apple', 'context_a'],
      ['apple', 'context_x'],
      ['mango', ''],
      ['zebra', ''],
    ];

    // Test without a langcode.
    $reader = new PoDatabaseReader();
    $this->assertSortedItems($expected, $reader);

    // Test with a langcode.
    $reader = new PoDatabaseReader();
    $reader->setLangcode('fr');
    $reader->setOptions([
      'not_customized' => TRUE,
    ]);
    $this->assertSortedItems($expected, $reader);
  }

  /**
   * Asserts that the reader returns the items in the expected order.
   *
   * @param array $expected
   *   The expected source and context pairs.
   * @param \Drupal\locale\PoDatabaseReader $reader
   *   The reader to check.
   */
  private function assertSortedItems(array $expected, PoDatabaseReader $reader): void {
    $items = [];
    while ($item = $reader->readItem()) {
      $items[] = [$item->getSource(), $item->getContext()];
    }
    $this->assertSame($expected, $items);
  }

  /**
   * Creates a source string.
   *
   * @param array $values
   *   The values array.
   *
   * @return \Drupal\locale\StringInterface
   *   The source string object.
   */
  private function createString(array $values = []): StringInterface {
    return $this->storage->createString($values + ['source' => $this->randomMachineName(100)])->save();
  }

  /**
   * Creates a translation string.
   *
   * @param \Drupal\locale\StringInterface $source
   *   The source string.
   *
   * @return void
   *   The translated string object.
   */
  private function createTranslation(StringInterface $source): void {
    $this->storage->createTranslation([
      'lid' => $source->lid,
      'language' => 'fr',
      'translation' => $this->randomMachineName(100),
    ])->save();
  }

}
