<?php

declare(strict_types=1);

namespace Drupal\Tests\file\Kernel\Plugin\Validation\Constraint;

use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Validation\Constraint\FileSvgXssConstraintValidator;
use Drupal\Tests\file\Kernel\Validation\FileValidatorTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the FileSvgXssConstraintValidator.
 */
#[CoversClass(FileSvgXssConstraintValidator::class)]
#[Group('file')]
#[RunTestsInSeparateProcesses]
class FileSvgXssConstraintValidatorTest extends FileValidatorTestBase {

  /**
   * Dangerous SVG file.
   */
  protected FileInterface $dangerousSvg;

  /**
   * Safe SVG file.
   */
  protected FileInterface $safeSvg;

  /**
   * PNG file.
   */
  protected FileInterface $notSvg;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->dangerousSvg = File::create();
    $this->dangerousSvg->setFileUri('core/modules/file/tests/fixtures/dangerous.svg');
    $this->dangerousSvg->setFilename(basename($this->dangerousSvg->getFileUri()));
    $this->dangerousSvg->setMimeType('image/svg+xml');

    $this->safeSvg = File::create();
    $this->safeSvg->setFileUri('core/modules/file/tests/fixtures/safe.svg');
    $this->safeSvg->setFilename(basename($this->safeSvg->getFileUri()));
    $this->safeSvg->setMimeType('image/svg+xml');

    $this->notSvg = File::create();
    $this->notSvg->setFileUri('core/misc/druplicon.png');
    $this->notSvg->setFilename(basename($this->notSvg->getFileUri()));
    $this->notSvg->setMimeType('image/png');
  }

  /**
   * Tests validate.
   */
  public function testValidate(): void {
    $validators = [
      'FileSvgXss' => [],
    ];

    // Test with dangerous SVG.
    $violations = $this->validator->validate($this->dangerousSvg, $validators);
    $this->assertCount(1, $violations);
    $this->assertEquals('For security reasons, your upload has been rejected.', $violations->get(0)->getMessage());

    // Test with safe SVG.
    $violations = $this->validator->validate($this->safeSvg, $validators);
    $this->assertCount(0, $violations);

    // Test with other MIME type.
    $violations = $this->validator->validate($this->notSvg, $validators);
    $this->assertCount(0, $violations);
  }

}
