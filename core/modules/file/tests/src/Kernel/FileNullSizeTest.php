<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel;

use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\file\Entity\File;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests various behaviors for null sized files.
 */
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileNullSizeTest extends FieldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['file'];

  /**
   * Created file entity.
   *
   * @var \Drupal\file\Entity\File
   */
  protected $file;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->file = File::create([
      'uri' => 'public://example.txt',
      'filesize' => NULL,
    ]);
    $this->file->save();
  }

  /**
   * Tests behavior of null files in ByteSizeMarkup.
   */
  public function testByteSizeMarkup(): void {
    $this->expectException(\TypeError::class);
    ByteSizeMarkup::create($this->file->getSize());
  }

}
