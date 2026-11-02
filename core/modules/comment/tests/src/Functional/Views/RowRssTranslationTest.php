<?php

declare(strict_types=1);

namespace Drupal\Tests\comment\Functional\Views;

use Drupal\comment\Entity\Comment;
use Drupal\Tests\content_translation\Traits\ContentTranslationTestTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the comment rss row plugin renders the current translation.
 *
 * @see \Drupal\comment\Plugin\views\row\Rss
 */
#[Group('comment')]
#[RunTestsInSeparateProcesses]
class RowRssTranslationTest extends CommentTestBase {

  use ContentTranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['test_comment_rss_translation'];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'comment',
    'comment_test_views',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE, $modules = ['comment_test_views']): void {
    parent::setUp($import_test_views, $modules);

    static::createLanguageFromLangcode('es');
    static::enableContentTranslation('comment', 'comment');
    $this->rebuildContainer();
  }

  /**
   * Tests rendered comments on the feed is in the current language.
   */
  public function testRssRowTranslation(): void {
    // Reload the comment so it picks up the language added in setUp().
    $comment = Comment::load($this->comment->id());
    $comment->set('subject', 'Something in English');
    $comment->save();

    $translation = $comment->addTranslation('es');
    $translation->set('subject', 'Algo en Español');
    $translation->save();

    // Visit the feed in English and assert the title is in English.
    $this->drupalGet('test-comment-rss-translation');
    $this->assertSession()->statusCodeEquals(200);
    $items = $this->getSession()->getDriver()->find('//item');
    $this->assertCount(1, $items, 'One rss item for the english request.');
    $title_element = $items[0]->findAll('xpath', 'title');
    $this->assertEquals('Something in English', $title_element[0]->getText());

    // Visit the feed in Spanish and assert the title is in Spanish.
    $this->drupalGet('es/test-comment-rss-translation');
    $this->assertSession()->statusCodeEquals(200);
    $items = $this->getSession()->getDriver()->find('//item');
    $this->assertCount(1, $items, 'One rss item for the spanish request.');
    $title_element = $items[0]->findAll('xpath', 'title');
    $this->assertEquals('Algo en Español', $title_element[0]->getText());
  }

}
