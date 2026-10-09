<?php

declare(strict_types=1);

namespace Drupal\Tests\path\Kernel;

use Drupal\Core\DefaultContent\Finder;
use Drupal\Core\DefaultContent\Importer;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests path alias languages when importing default content.
 */
#[Group('path')]
#[RunTestsInSeparateProcesses]
class DefaultContentLanguageTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'path',
    'path_alias',
    'node',
    'user',
    'system',
    'field',
    'text',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['language']);

    NodeType::create([
      'type' => 'page',
      'name' => 'Page',
    ])->save();
  }

  /**
   * Tests that aliases follow the entity language when it is changed.
   */
  public function testAliasLanguageFollowsEntityLanguage(): void {
    $this->setUpCurrentUser(admin: TRUE);

    $finder = new Finder(__DIR__ . '/../../fixtures/default_content_language');
    $this->container->get(Importer::class)->importContent($finder);

    $entity_repository = $this->container->get(EntityRepositoryInterface::class);
    $alias_repository = $this->container->get('path_alias.repository');

    // The site does not know French, so the node is imported in English. The
    // alias must be moved to English as well.
    $node = $entity_repository->loadEntityByUuid('node', '5a7c9ef3-4d0b-4b6e-9a51-6c1f6a0e4b21');
    $this->assertSame('en', $node->language()->getId());
    $alias = $alias_repository->lookupByAlias('/home', 'en');
    $this->assertSame('/node/' . $node->id(), $alias['path']);
    $this->assertSame('en', $alias['langcode']);

    // Language-neutral aliases are kept.
    $node = $entity_repository->loadEntityByUuid('node', '8d2f4b1e-0c3a-4f7e-b9d6-2e5a1c7f3b90');
    $this->assertSame('en', $node->language()->getId());
    $alias = $alias_repository->lookupByAlias('/about', 'en');
    $this->assertSame('/node/' . $node->id(), $alias['path']);
    $this->assertSame(LanguageInterface::LANGCODE_NOT_SPECIFIED, $alias['langcode']);
  }

}
