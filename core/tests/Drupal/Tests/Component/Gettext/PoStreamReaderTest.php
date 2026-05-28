<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Gettext;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Component\Gettext\PoHeader;
use Drupal\Component\Gettext\PoStreamReader;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamWrapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Drupal\Component\Gettext\PoStreamReader
 */
#[Group('Gettext')]
class PoStreamReaderTest extends TestCase {

  /**
   * Content of the localization file.
   *
   * The last few empty lines at the end of the string are important; they are
   * used to trigger an edge case.
   *
   * @const string
   */
  const PO_CONTENT = <<<'EOS'
msgid ""
msgstr ""
"Project-Id-Version: Drupal core (9.2.7)\n"
"POT-Creation-Date: 2021-10-27 10:29+0000\n"
"PO-Revision-Date: YYYY-mm-DD HH:MM+ZZZZ\n"
"Language-Team: Hungarian\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=utf-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Plural-Forms: nplurals=2; plural=(n!=1);\n"

msgid "id-01"
msgstr "str-01"
msgid "id-02"
msgstr "str-02"
msgid "id-03"
msgstr "str-03"
#: a/b/c.yml:0; d/e/f.yml:0
msgid "id-04"
msgstr "str-04"
#: a/b/c.yml:0; d/e/f.yml:0
msgid "id-05"
msgstr "str-05"
msgid "id-06"
msgstr "str-06"
msgid "id-07"
msgstr ""
"str-07 line-01\n"
"str-07 line-02"

msgid "id-08 1"
msgid_plural "id-08 @count"
msgstr[0] "str-08-0 1"
msgstr[1] "str-08-1 @count"
msgctxt "ctx-01"
msgid "id-09"
msgstr "str-09"



EOS;

  /**
   * Validates that calling open with an invalid URI throws an exception.
   */
  public function testOpeningFileError(): void {
    $reader = new PoStreamReader();
    $reader->setURI('fake');
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Cannot open stream for uri fake');
    $reader->open();
  }

  public function testSetHeader(): void {
    $reader = new PoStreamReader();
    $this->assertNull($reader->getHeader(), 'without ::open() the header is not initialized');
    $header = new PoHeader('en');
    $reader->setHeader($header);
    $this->assertNull($reader->getHeader(), '::setHeader() has no effect');
  }

  public function testOpenFail(): void {
    $reader = new PoStreamReader();
    $this->expectException(\Exception::class);
    $this->expectExceptionCode(0);
    $reader->open();
  }

  public function testCloseFail(): void {
    $reader = new PoStreamReader();
    $this->expectException(\Exception::class);
    $this->expectExceptionCode(0);
    $reader->setURI('data://text/plain;base64,' . base64_encode('# Nothing.'));
    $reader->close();
  }

  public function testReadHeader(): void {
    $reader = new PoStreamReader();
    $reader->setURI('data://text/plain;base64,' . base64_encode('# Nothing.'));
    $reader->open();
    $this->assertNull($reader->getHeader());
  }

  public function testReadItemsInMultipleSequence(): void {
    FileCacheFactory::setPrefix(hash('md5', __METHOD__));
    vfsStreamWrapper::register();
    $translations_dir = new vfsStreamDirectory('translations');
    vfsStreamWrapper::setRoot($translations_dir);
    $url = vfsStream::url('translations');
    $po_filename = $url . '/basic.hu.po';
    file_put_contents($po_filename, self::PO_CONTENT);

    $expected = [
      'items' => [
        implode("\n", [
          '"Last-Translator: NAME <EMAIL@ADDRESS>\n"',
          '"Language-Team: LANGUAGE <EMAIL@ADDRESS>\n"',
          '"MIME-Version: 1.0\n"',
          '"Content-Type: text/plain; charset=utf-8\n"',
          '"Content-Transfer-Encoding: 8bit\n"',
          '"Plural-Forms: nplurals=2; plural=(n!=1);\n"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-01"',
          'msgstr "str-01"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-02"',
          'msgstr "str-02"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-03"',
          'msgstr "str-03"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-04"',
          'msgstr "str-04"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-05"',
          'msgstr "str-05"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-06"',
          'msgstr "str-06"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-07"',
          'msgstr ""',
          '"str-07 line-01\n"',
          '"str-07 line-02"',
          '',
          '',
        ]),
        implode("\n", [
          'msgid "id-08 1"',
          'msgid_plural "id-08 @count"',
          'msgstr[0] "str-08-0 1"',
          'msgstr[1] "str-08-1 @count"',
          '',
          '',
        ]),
        implode("\n", [
          'msgctxt "ctx-01"',
          'msgid "id-09"',
          'msgstr "str-09"',
          '',
          '',
        ]),
      ],
    ];

    $reader = new PoStreamReader();
    $reader->setURI($po_filename);
    $reader->setLangcode('hu');
    $reader->open();

    $header = $reader->getHeader();
    $this->assertStringEndsWith($expected['items'][0], (string) $header);

    $item = $reader->readItem();
    $this->assertSame(
      $expected['items'][1],
      (string) $item,
      'PO item 1',
    );

    $item = $reader->readItem();
    $this->assertSame(
      $expected['items'][2],
      (string) $item,
      'PO item 2',
    );

    $lastItemEndPosition = $reader->getSeekLastItem();
    $reader->close();

    $reader->open();
    $reader->setSeek($lastItemEndPosition);

    $item = $reader->readItem();
    $this->assertSame(
      $expected['items'][3],
      (string) $item,
      'PO item 3',
    );

    $item = $reader->readItem();
    $this->assertSame(
      $expected['items'][4],
      (string) $item,
      'PO item 4',
    );

    $lastItemEndPosition = $reader->getSeekLastItem();
    $this->assertGreaterThan($lastItemEndPosition, $reader->getSeek());
    $reader->close();

    $reader->open();
    $reader->setSeek($lastItemEndPosition);

    for ($i = 5; $i < count($expected['items']); $i++) {
      $item = $reader->readItem();
      $this->assertSame(
        $expected['items'][$i],
        (string) $item,
        "PO item $i",
      );
    }

    $this->assertNull($reader->readItem());
    // Verify reader can reach the end of the file.
    $this->assertSame($reader->getSeekLastItem(), filesize($po_filename));
  }

  /**
   * Provides test cases for static::testParseQuoted().
   *
   * @return array
   *   Test cases, keyed by human readable names. The values are
   *   subarrays with an expected result and a string to parse.
   */
  public static function casesParseQuoted(): array {
    return [
      'different start and end - empty' => [
        FALSE,
        "\"'",
      ],
      'different start and end - not empty' => [
        FALSE,
        "\"'",
      ],
      'double quotes - empty' => [
        '',
        '""',
      ],
      'double quotes - without slash' => [
        'a',
        '"a"',
      ],
      'double quotes - with slash' => [
        "a\n\"c",
        '"a\n\"\c"',
      ],
      'simple quotes - empty' => [
        '',
        "''",
      ],
      'simple quotes - without slash' => [
        'a',
        "'a'",
      ],
      'simple quotes - with slash' => [
        'a\n\\\'\c',
        "'a\\n\'\c'",
      ],
      'unrecognized quote - empty' => [
        FALSE,
        '',
      ],
      'unrecognized quote - start and end same' => [
        FALSE,
        'aa',
      ],
      'unrecognized quote - start and end different' => [
        FALSE,
        'ab',
      ],
    ];
  }

  /**
   * Tests that PoStreamReader::parseQuoted() works as expected.
   */
  #[DataProvider('casesParseQuoted')]
  public function testParseQuoted(string|false $expected, string $string): void {
    $reader = new PoStreamReader();
    static::assertSame($expected, $reader->parseQuoted($string));
  }

}
