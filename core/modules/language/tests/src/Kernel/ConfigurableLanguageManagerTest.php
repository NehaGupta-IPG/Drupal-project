<?php

declare(strict_types=1);

namespace Drupal\Tests\language\Kernel;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\entity_test\Entity\EntityTestMulRev;
use Drupal\language\ConfigurableLanguageManager;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationContentEntity;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationSession;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the ConfigurableLanguage entity.
 */
#[CoversClass(ConfigurableLanguageManager::class)]
#[Group('language')]
#[RunTestsInSeparateProcesses]
class ConfigurableLanguageManagerTest extends LanguageTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'user'];

  /**
   * The language negotiator.
   *
   * @var \Drupal\language\LanguageNegotiatorInterface
   */
  protected $languageNegotiator;

  /**
   * The language manager.
   *
   * @var \Drupal\language\ConfigurableLanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Tracks the access result return from custom access callback.
   *
   * This is keyed by langcode and has string values of either "allowed",
   * "neutral", or "forbidden".
   *
   * @var array<string, string>
   */
  protected array $customAccessInvocations = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test_mulrev');
    $this->installEntitySchema('configurable_language');
    $this->installEntitySchema('user');

    // In order to reflect the changes for a multilingual site in the container
    // we have to rebuild it.
    ConfigurableLanguage::createFromLangcode('es')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $config = $this->config('language.negotiation');
    $config->set('url.prefixes', ['en' => 'en', 'es' => 'es', 'fr' => 'fr'])
      ->save();

    // Use language-content-entity negotiation.
    $config = $this->config('language.types');
    $config->set('configurable', [LanguageInterface::TYPE_INTERFACE, LanguageInterface::TYPE_CONTENT])
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $routeSubscriber = new class extends RouteSubscriberBase {

      /**
       * {@inheritdoc}
       */
      protected function alterRoutes(RouteCollection $collection): void {
        // Add custom access check to the entity canonical route.
        if ($route = $collection->get('entity.entity_test_mulrev.canonical')) {
          $route->setRequirement('_custom_access', ConfigurableLanguageManagerTest::class . ':customEntityTestMulRevAccess');
        }

        // The entity_test_mulrev revision route is set to "_access: TRUE" in
        // entity_test.routing.yml, so override that with the usual entity test
        // access handling and add the custom access check.
        if ($route = $collection->get('entity.entity_test_mulrev.revision')) {
          $route->setRequirements([
            '_entity_access' => 'entity_test_mulrev_revision.view revision',
            '_custom_access' => ConfigurableLanguageManagerTest::class . ':customEntityTestMulRevAccess',
          ]);
        }
      }

    };
    $this->container->register('test_switch_links_access_subscriber', $routeSubscriber::class)
      ->addTag('event_subscriber');
  }

  /**
   * Tests switch links using all methods implementing getLanguageSwitchLinks().
   *
   * @legacy-covers ::getLanguageSwitchLinks
   */
  #[TestWith([LanguageNegotiationContentEntity::METHOD_ID])]
  #[TestWith([LanguageNegotiationUrl::METHOD_ID])]
  #[TestWith([LanguageNegotiationSession::METHOD_ID])]
  public function testLanguageSwitchLinks(string $methodId): void {
    $config = $this->config('language.types');
    $config->set('negotiation.language_content.enabled', [
      $methodId => 0,
    ]);
    $config->save();
    \Drupal::service('kernel')->rebuildContainer();
    $this->languageNegotiator = $this->container->get('language_negotiator');
    $this->languageNegotiator->setCurrentUser(\Drupal::currentUser());
    $this->languageManager = $this->container->get('language_manager');

    // Create revisionable, translatable entity and allow English and Spanish
    // revisions to be viewed with inclusion "view revision" in the name.
    $entity = EntityTestMulRev::create(['name' => 'view revision,English']);
    $entity->addTranslation('es', ['name' => 'view revision,Spanish']);
    $entity->addTranslation('fr', ['name' => 'no,French']);
    $entity->save();

    // Confirm the current languages before testing the switch links.
    $this->assertSame('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
    $this->assertSame('en', $this->languageManager->getCurrentLanguage()->getId());

    // Get the switch links result from the language manager. Confirm during
    // links generation that the entity parameter converter upcasts to the
    // correct translation per link and that the access is controlled correctly
    // by ::entityTestMulRevAccess() and , where English and French are allowed, but not
    // Spanish.
    $links = $this->languageManager->getLanguageSwitchLinks(LanguageInterface::TYPE_CONTENT, new Url('entity.entity_test_mulrev.canonical', ['entity_test_mulrev' => $entity->id()]));
    $this->assertArrayHasKey('en', $links->links);
    $this->assertSame('/en/entity_test_mulrev/manage/1', $links->links['en']['url']->toString());
    $this->assertArrayHasKey('fr', $links->links);
    $this->assertSame('/fr/entity_test_mulrev/manage/1', $links->links['fr']['url']->toString());
    $this->assertArrayNotHasKey('es', $links->links);
    $this->assertSame('allowed', $this->customAccessInvocations['en']);
    $this->assertSame('allowed', $this->customAccessInvocations['es']);
    $this->assertSame('allowed', $this->customAccessInvocations['fr']);

    // Create a new revision for the entity.
    $originalRevisionId = $entity->getRevisionId();
    $entity->set('name', 'name English revision');
    $entity->getTranslation('es')->set('name', 'name Spanish revision');
    $entity->getTranslation('fr')->set('name', 'name French revision');
    $entity->setNewRevision();
    $entity->save();

    // Test that the language content entity revision parameter converter
    // upcasts to the correct entity translation. The entity access check allows
    // 'en' and 'es', but forbids 'fr'. The custom access check allows 'es' and
    // 'fr', but forbids 'en'. The access results are merged by "AND", so the
    // only allowed link is 'es'.
    $this->customAccessInvocations = [];
    $links = $this->languageManager->getLanguageSwitchLinks(LanguageInterface::TYPE_CONTENT, new Url('entity.entity_test_mulrev.revision', [
      'entity_test_mulrev' => $entity->id(),
      'entity_test_mulrev_revision' => $originalRevisionId,
    ]));
    $this->assertArrayNotHasKey('en', $links->links);
    $this->assertArrayHasKey('es', $links->links);
    $this->assertSame('/es/entity_test_mulrev/1/revision/1/view', $links->links['es']['url']->toString());
    $this->assertArrayNotHasKey('fr', $links->links);
    $this->assertSame('forbidden', $this->customAccessInvocations['en']);
    $this->assertSame('allowed', $this->customAccessInvocations['es']);
    $this->assertSame('allowed', $this->customAccessInvocations['fr']);
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('entity_test_mulrev_access')]
  public function entityTestMulRevAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'view') {
      return AccessResult::neutral();
    }
    return match ($entity->language()->getId()) {
      'es' => AccessResult::forbidden(),
      default => AccessResult::allowed(),
    };
  }

  /**
   * Custom route access callback.
   */
  public function customEntityTestMulRevAccess(
    ?LanguageInterface $languageContent = NULL,
    ?LanguageInterface $languageInterface = NULL,
    ?EntityTestMulRev $entity_test_mulrev = NULL,
    ?EntityTestMulRev $entity_test_mulrev_revision = NULL,
  ): AccessResultInterface {
    // Check the language from the revision route parameter first, because it
    // might not be set, but the entity route parameter is on both the canonical
    // and revision route.
    $entityLanguage = $entity_test_mulrev_revision?->language() ?? $entity_test_mulrev?->language();
    // The 'language' option in the Url object for each switcher link sets all
    // language parameters to the same value.
    $this->assertSame($languageContent, $entityLanguage);
    $this->assertSame($languageInterface, $entityLanguage);

    // Confirm that the language manager's current language has not changed
    // while checking access to each translation's route.
    $this->assertSame('en', $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId());
    $this->assertSame('en', $this->languageManager->getCurrentLanguage()->getId());

    // On the revision route, forbid 'en' and allow the others.
    if (isset($entity_test_mulrev_revision)) {
      $access = match ($entityLanguage->getId()) {
        'en' => AccessResult::forbidden(),
        default => AccessResult::allowed(),
      };
      $this->customAccessInvocations[$entityLanguage->getId()] = $access->isAllowed() ? 'allowed' : 'forbidden';
      return $access;
    }

    // On the canonical route, allow all.
    if (isset($entity_test_mulrev)) {
      $this->customAccessInvocations[$entityLanguage->getId()] = 'allowed';
      return AccessResult::allowed();
    }

    return AccessResult::neutral();
  }

}
