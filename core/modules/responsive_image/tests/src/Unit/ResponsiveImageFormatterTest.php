<?php

declare(strict_types=1);

namespace Drupal\Tests\responsive_image\Unit;

use Drupal\responsive_image\Plugin\Field\FieldFormatter\ResponsiveImageFormatter;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ResponsiveImageFormatter static helpers.
 */
#[CoversClass(ResponsiveImageFormatter::class)]
#[Group('responsive_image')]
class ResponsiveImageFormatterTest extends UnitTestCase {

  /**
   * Tests all three non-exclusive breakpoint patterns and safe edge-cases.
   */
  #[DataProvider('provideNonExclusiveBreakpointCases')]
  public function testHasNonExclusiveBreakpoints(array $queries, bool $expected): void {
    $this->assertSame($expected, ResponsiveImageFormatter::hasNonExclusiveBreakpoints($queries));
  }

  /**
   * Data provider for testHasNonExclusiveBreakpoints().
   */
  public static function provideNonExclusiveBreakpointCases(): array {
    return [
      // ── Safe cases ────────────────────────────────────────────────────────
      'empty array' => [
        [],
        FALSE,
      ],
      'single empty string is filtered' => [
        [''],
        FALSE,
      ],
      'single query is always exclusive' => [
        ['(min-width: 0px)'],
        FALSE,
      ],
      'both min-width and max-width — mutually exclusive' => [
        [
          '(min-width: 0px) and (max-width: 559px)',
          '(min-width: 560px) and (max-width: 850px)',
          '(min-width: 851px) and (max-width: 1200px)',
        ],
        FALSE,
      ],
      'empty strings filtered — single real exclusive query' => [
        ['', '(min-width: 851px) and (max-width: 1200px)'],
        FALSE,
      ],

      // ── Pattern 1: min-width only ─────────────────────────────────────────
      // Cumulative on wider viewports — all screens above the lower bound match,
      // so multiple breakpoints fire simultaneously on larger screens.
      'min-width only, two queries' => [
        ['(min-width: 0px)', '(min-width: 560px)'],
        TRUE,
      ],
      'min-width only, three queries' => [
        ['(min-width: 0px)', '(min-width: 560px)', '(min-width: 851px)'],
        TRUE,
      ],

      // ── Pattern 2: max-width only ─────────────────────────────────────────
      // Cumulative on smaller viewports — all screens below the upper bound match,
      // so multiple breakpoints fire simultaneously on smaller screens.
      'max-width only, two queries' => [
        ['(max-width: 560px)', '(max-width: 851px)'],
        TRUE,
      ],

      // ── Pattern 3: no width constraint ───────────────────────────────────
      // Not bounded by width at all — can overlap with any width-based breakpoint.
      'orientation query mixed with bounded query' => [
        ['(orientation: landscape)', '(min-width: 560px) and (max-width: 851px)'],
        TRUE,
      ],
      'color query mixed with bounded query' => [
        ['(color)', '(min-width: 0px) and (max-width: 559px)'],
        TRUE,
      ],

      // ── Allowed: a single open-ended outermost breakpoint ─────────────────
      // Exactly one min-only query, paired with at least one bounded sibling,
      // represents an open-ended top breakpoint (anything wider than X).
      'single min-only at top with bounded sibling' => [
        ['(min-width: 0px) and (max-width: 559px)', '(min-width: 560px)'],
        FALSE,
      ],
      // Symmetric: exactly one max-only query represents an open-ended bottom.
      'single max-only at bottom with bounded sibling' => [
        ['(max-width: 559px)', '(min-width: 560px) and (max-width: 1200px)'],
        FALSE,
      ],
      // One min-only and one max-only with a gap between them — still
      // mutually exclusive (no viewport matches both).
      'one min-only and one max-only with a gap' => [
        ['(max-width: 559px)', '(min-width: 1000px)'],
        FALSE,
      ],
      // Two min-only queries are still cumulative, even with a bounded sibling.
      'two min-only with bounded sibling' => [
        [
          '(min-width: 0px) and (max-width: 559px)',
          '(min-width: 560px)',
          '(min-width: 800px)',
        ],
        TRUE,
      ],
      // The umami hero responsive image style: two bounded queries flanked by
      // a single open-ended bottom (max-only) and a single open-ended top
      // (min-only) — exclusive under the refined rule.
      'umami hero set with open-ended top and bottom breakpoints' => [
        [
          'all and (max-width: 499px)',
          'all and (min-width: 500px) and (max-width: 800px)',
          'all and (min-width: 800px) and (max-width: 1400px)',
          'all and (min-width: 1400px)',
        ],
        FALSE,
      ],
    ];
  }

}
