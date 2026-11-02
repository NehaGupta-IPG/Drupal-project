<?php

declare(strict_types=1);

namespace Drupal\Tests\layout_builder\Kernel;

use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionListInterface;
use Drupal\layout_builder\SectionListTrait;

/**
 * A test section list for use in kernel tests.
 */
class TestSectionList implements SectionListInterface {

  use SectionListTrait {
    addBlankSection as public;
  }

  /**
   * An array of sections.
   *
   * @var \Drupal\layout_builder\Section[]
   */
  protected array $sections;

  /**
   * TestSectionList constructor.
   */
  public function __construct(array $sections) {
    $this->setSections([]);
    // Loop through each section and reconstruct it to ensure that all default
    // values are present.
    foreach ($sections as $section) {
      $this->sections[] = Section::fromArray($section->toArray());
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function setSections(array $sections): array {
    $this->sections = array_values($sections);
    return $sections;
  }

  /**
   * {@inheritdoc}
   */
  public function getSections(): array {
    return $this->sections;
  }

}
