<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\layout_builder\SectionListTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Drupal\layout_builder\SectionListTrait.
 */
#[CoversTrait(SectionListTrait::class)]
#[Group('layout_builder')]
#[RunTestsInSeparateProcesses]
class SectionListTraitTest extends SectionListTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getSectionList(array $section_data): TestSectionList {
    return new TestSectionList($section_data);
  }

  /**
   * Tests add blank section.
   */
  public function testAddBlankSection(): void {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessageIs('A blank section must only be added to an empty list');
    $this->sectionList->addBlankSection();
  }

}
