<?php

namespace Drupal\Core\Template;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Provides non-deprecated spaceless filter.
 */
class SpacelessBCExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('spaceless', [self::class, 'spaceless'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Removes whitespaces between HTML tags.
   *
   * @param string|null $content
   *   The content to remove whitespaces from.
   *
   * @internal
   */
  public static function spaceless(?string $content): string {
    return trim(preg_replace('/>\s+</', '><', $content ?? ''));
  }

}
