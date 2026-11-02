<?php

declare(strict_types=1);

namespace Drupal\Tests\newsroom\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\newsroom\ArticleInterface;
use Drupal\newsroom\Repository\ArticleRepository;
use Drupal\newsroom\Repository\ArticleRepositoryInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that articles can be stored and retrieved via the repository.
 */
#[CoversClass(ArticleRepository::class)]
#[Group('newsroom')]
#[RunTestsInSeparateProcesses]
class ArticleRepositoryTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['field', 'newsroom', 'node', 'system', 'text', 'user'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $fields = [
      'field_publication_date' => 'timestamp',
      'field_last_review_date' => 'timestamp',
      'field_evergreen' => 'boolean',
    ];
    foreach ($fields as $field_name => $type) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => $type,
      ]);
      $field_storage->save();
      FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => 'article',
      ])->save();
    }
  }

  /**
   * Tests that a stored article can be retrieved via the repository.
   */
  public function testStoreAndRetrieve(): void {
    Node::create([
      'type' => 'article',
      'title' => 'Headline',
      'field_publication_date' => 1_790_000_000,
      'field_last_review_date' => 1_790_003_600,
      'field_evergreen' => TRUE,
    ])->save();

    $articles = $this->container->get(ArticleRepositoryInterface::class)->getLatest(1);
    $this->assertCount(1, $articles);
    $article = reset($articles);
    $this->assertInstanceOf(ArticleInterface::class, $article);
    $this->assertSame('Headline', $article->getHeadline());
    $this->assertSame(1_790_000_000, $article->getPublicationTime());
    $this->assertSame(1_790_003_600, $article->getLastReviewTime());
    $this->assertTrue($article->isEvergreen());
  }

}
