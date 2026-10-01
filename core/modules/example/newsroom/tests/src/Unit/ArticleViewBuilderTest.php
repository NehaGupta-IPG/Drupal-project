<?php

declare(strict_types=1);

namespace Drupal\Tests\newsroom\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\newsroom\Render\ArticleViewBuilder;
use Drupal\newsroom\Repository\ArticleRepositoryInterface;
use Drupal\newsroom\ReviewPolicy;
use Drupal\Tests\newsroom\TestArticle;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * Tests the article view builder.
 */
#[CoversClass(ArticleViewBuilder::class)]
#[Group('newsroom')]
class ArticleViewBuilderTest extends UnitTestCase {

  /**
   * The current time.
   */
  protected const int NOW = 1_790_000_000;

  /**
   * The article repository prophecy.
   */
  protected ObjectProphecy $articleRepository;

  /**
   * The review policy prophecy.
   */
  protected ObjectProphecy $reviewPolicy;

  /**
   * The view builder under test.
   */
  protected ArticleViewBuilder $viewBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->articleRepository = $this->prophesize(ArticleRepositoryInterface::class);
    $this->reviewPolicy = $this->prophesize(ReviewPolicy::class);
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn(static::NOW);
    $this->viewBuilder = new ArticleViewBuilder($this->articleRepository->reveal(), $this->reviewPolicy->reveal(), $time->reveal());
    $this->viewBuilder->setStringTranslation($this->getStringTranslationStub());
  }

  /**
   * Tests that a notice is displayed for articles due for review.
   */
  public function testArticleDueForReview(): void {
    $article = new TestArticle(published: static::NOW);
    $this->reviewPolicy->needsReview($article)->willReturn(TRUE);

    $build = $this->viewBuilder->buildReviewNotice($article);
    $this->assertSame('status_messages', $build['#theme']);
    $this->assertSame('This article is due for review.', (string) $build['#message_list']['warning'][0]);
    $this->assertArrayNotHasKey('#cache', $build);
  }

  /**
   * Tests that no notice is displayed until the article is due for review.
   */
  public function testArticleNotDueForReview(): void {
    $article = new TestArticle(published: static::NOW);
    $this->reviewPolicy->needsReview($article)->willReturn(FALSE);
    $this->reviewPolicy->getReviewDueTime($article)->willReturn(static::NOW + 3600);

    $build = $this->viewBuilder->buildReviewNotice($article);
    $this->assertSame(['#cache' => ['max-age' => 3600]], $build);
  }

  /**
   * Tests that the review queue lists the headlines of articles due for review.
   */
  public function testReviewQueue(): void {
    $due = new TestArticle(published: static::NOW, headline: 'Due');
    $evergreen = new TestArticle(published: static::NOW, evergreen: TRUE, headline: 'Evergreen');
    $this->articleRepository->getNotReviewedSince(static::NOW - ReviewPolicy::REVIEW_INTERVAL)
      ->willReturn([$due, $evergreen]);
    $this->reviewPolicy->getReviewQueue([$due, $evergreen])->willReturn([$due]);

    $build = $this->viewBuilder->buildReviewQueue();
    $this->assertSame('item_list', $build['#theme']);
    $this->assertSame(['Due'], $build['#items']);
    $this->assertSame(0, $build['#cache']['max-age']);
  }

}
