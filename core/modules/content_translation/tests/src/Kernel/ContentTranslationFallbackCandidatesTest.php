<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Kernel;

use Drupal\content_translation\Hook\ContentTranslationHooks;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the access checks of the entity_view language fallback candidates.
 */
#[CoversMethod(ContentTranslationHooks::class, 'languageFallbackCandidatesEntityViewAlter')]
#[Group('content_translation')]
#[RunTestsInSeparateProcesses]
class ContentTranslationFallbackCandidatesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_translation',
    'entity_test',
    'language',
    'system',
    'user',
  ];

  /**
   * The language codes whose translation had its view access checked.
   *
   * @var string[]
   */
  protected array $checkedLangcodes = [];

  /**
   * The test entity, published in en and de, unpublished in fr and it.
   */
  protected EntityTestMulRevPub $entity;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);
    $this->installEntitySchema('user');

    // Candidates for "fr" are ordered by weight after the requested language:
    // fr, de, it, en, und.
    foreach (['de' => 1, 'it' => 2, 'fr' => 3] as $langcode => $weight) {
      ConfigurableLanguage::createFromLangcode($langcode)->setWeight($weight)->save();
    }
    ConfigurableLanguage::load('en')->setWeight(4)->save();

    $this->installEntitySchema('entity_test_mulrevpub');
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mulrevpub', 'entity_test_mulrevpub', TRUE);

    $entity = EntityTestMulRevPub::create([
      'name' => 'en',
      'langcode' => 'en',
      'status' => TRUE,
    ]);
    $entity->addTranslation('fr', ['name' => 'fr', 'status' => FALSE]);
    $entity->addTranslation('de', ['name' => 'de', 'status' => TRUE]);
    $entity->addTranslation('it', ['name' => 'it', 'status' => FALSE]);
    $entity->save();
    $this->entity = $entity;
  }

  /**
   * Implements hook_ENTITY_TYPE_access() for entity_test_mulrevpub.
   */
  #[Hook('entity_test_mulrevpub_access')]
  public function entityTestMulRevPubAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation === 'view') {
      $this->checkedLangcodes[] = $entity->language()->getId();
    }
    return AccessResult::neutral();
  }

  /**
   * Returns the entity_view fallback candidates for "fr".
   *
   * @param array $context
   *   Additional context for the fallback candidates.
   *
   * @return string[]
   *   The candidate language codes.
   */
  protected function getCandidates(array $context = []): array {
    $this->checkedLangcodes = [];
    $this->container->get('entity_type.manager')->getAccessControlHandler('entity_test_mulrevpub')->resetCache();
    $context += [
      'data' => $this->entity,
      'operation' => 'entity_view',
      'langcode' => 'fr',
    ];
    return array_keys($this->container->get('language_manager')->getFallbackCandidates($context));
  }

  /**
   * Tests that every inaccessible candidate is removed by default.
   */
  public function testAllCandidatesFiltered(): void {
    $this->assertSame(['de', 'en', 'und'], $this->getCandidates());
    $this->assertEqualsCanonicalizing(['fr', 'it'], $this->checkedLangcodes);
  }

  /**
   * Tests that checks stop at the first usable candidate when requested.
   */
  public function testFirstCandidateOnly(): void {
    // "it" comes after "de", the first usable candidate, so it is not checked.
    $this->assertSame(['de', 'it', 'en', 'und'], $this->getCandidates(['first_candidate_only' => TRUE]));
    $this->assertSame(['fr'], $this->checkedLangcodes);
  }

  /**
   * Tests that translations that are not candidates are not checked.
   */
  public function testNonCandidatesNotChecked(): void {
    $this->checkedLangcodes = [];
    $candidates = ['de' => 'de', 'it' => 'it'];
    $context = [
      'data' => $this->entity,
      'operation' => 'entity_view',
      'langcode' => 'de',
    ];
    $this->container->get(ContentTranslationHooks::class)->languageFallbackCandidatesEntityViewAlter($candidates, $context);
    $this->assertSame(['de'], array_keys($candidates));
    // The unpublished "fr" translation is not a candidate.
    $this->assertSame(['it'], $this->checkedLangcodes);
  }

  /**
   * Tests that the negotiated translation does not depend on the early stop.
   */
  public function testTranslationFromContext(): void {
    $repository = $this->container->get('entity.repository');
    $this->assertSame('de', $repository->getTranslationFromContext($this->entity, 'fr')->language()->getId());
    $this->assertSame(['fr'], $this->checkedLangcodes);

    $this->checkedLangcodes = [];
    $this->container->get('entity_type.manager')->getAccessControlHandler('entity_test_mulrevpub')->resetCache();
    $translation = $repository->getTranslationFromContext($this->entity, 'fr', ['first_candidate_only' => FALSE]);
    $this->assertSame('de', $translation->language()->getId());
    $this->assertEqualsCanonicalizing(['fr', 'it'], $this->checkedLangcodes);
  }

}
