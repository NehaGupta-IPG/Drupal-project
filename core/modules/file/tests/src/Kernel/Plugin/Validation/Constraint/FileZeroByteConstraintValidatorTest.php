<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel\Plugin\Validation\Constraint;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\Tests\file\Kernel\Validation\FileValidatorTestBase;

/**
 * Tests the FileZeroByteConstraintValidator.
 *
 * @group file
 * @coversDefaultClass \Drupal\file\Plugin\Validation\Constraint\FileZeroByteConstraintValidator
 */
class FileZeroByteConstraintValidatorTest extends FileValidatorTestBase {

  /**
   * The zero byte file.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $fileZeroByte;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $uri = 'public://file_zero_byte.txt';
    $this->fileZeroByte = File::create([
      'uid' => 1,
      'uri' => $uri,
      'filename' => 'file_zero_byte.txt',
      'filemime' => 'text/plain',
      'filesize' => 0,
    ]);
    $this->fileZeroByte->setPermanent();
  }

  /**
   * @covers ::validate
   */
  public function testFileZeroByteValidate() {
    $validators = ['FileZeroByte' => []];
    $violations = $this->validator->validate($this->file, $validators);
    $this->assertCount(0, $violations, 'No limits means no errors.');

    $violations = $this->validator->validate($this->fileZeroByte, $validators);
    $this->assertCount(1, $violations, 'Error for the file with zero byte size.');
  }

}
