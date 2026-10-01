<?php

declare(strict_types=1);

namespace Drupal\Tests\newsroom\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\newsroom\ReviewPolicy;
use Drupal\Tests\newsroom\TestArticle;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the article review policy.
 */
#[CoversClass(ReviewPolicy::class)]
#[Group('newsroom')]
class ReviewPolicyTest extends UnitTestCase {

  /**
   * The current time.
   */
  protected const int NOW = 1_790_000_000;

  /**
   * The review policy under test.
   */
  protected ReviewPolicy $policy;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn(static::NOW);
    $this->policy = new ReviewPolicy($time->reveal());
  }

  /**
   * Tests that a never reviewed article is due based on its publication time.
   */
  public function testNeverReviewedArticle(): void {
    $fresh = new TestArticle(published: static::NOW - ReviewPolicy::REVIEW_INTERVAL + 1);
    $this->assertFalse($this->policy->needsReview($fresh));

    $stale = new TestArticle(published: static::NOW - ReviewPolicy::REVIEW_INTERVAL);
    $this->assertTrue($this->policy->needsReview($stale));
  }

  /**
   * Tests that the last review time takes precedence over publication time.
   */
  public function testReviewedArticle(): void {
    $article = new TestArticle(
      published: static::NOW - 2 * ReviewPolicy::REVIEW_INTERVAL,
      reviewed: static::NOW - 86400,
    );
    $this->assertFalse($this->policy->needsReview($article));
  }

  /**
   * Tests that evergreen articles are reviewed less often.
   */
  public function testEvergreenArticle(): void {
    $published = static::NOW - ReviewPolicy::REVIEW_INTERVAL;
    $this->assertTrue($this->policy->needsReview(new TestArticle(published: $published)));
    $this->assertFalse($this->policy->needsReview(new TestArticle(published: $published, evergreen: TRUE)));
  }

  /**
   * Tests that the review queue lists only due articles, most overdue first.
   */
  public function testReviewQueue(): void {
    $overdue = new TestArticle(published: static::NOW - 2 * ReviewPolicy::REVIEW_INTERVAL);
    $due = new TestArticle(published: static::NOW - ReviewPolicy::REVIEW_INTERVAL);
    $fresh = new TestArticle(published: static::NOW);

    $queue = $this->policy->getReviewQueue([$due, $fresh, $overdue]);
    $this->assertSame([$overdue, $due], $queue);
  }

}
