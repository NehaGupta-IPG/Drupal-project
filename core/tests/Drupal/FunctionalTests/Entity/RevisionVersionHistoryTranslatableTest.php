<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Entity;

use Drupal\Core\Entity\Controller\VersionHistoryController;
use Drupal\entity_test_revlog\Entity\EntityTestMulWithRevisionLog;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests version history page with translations.
 */
#[CoversClass(VersionHistoryController::class)]
#[Group('Entity')]
#[RunTestsInSeparateProcesses]
final class RevisionVersionHistoryTranslatableTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_test_revlog',
    'content_translation',
    'language',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ConfigurableLanguage::createFromLangcode('es')->save();

    // Rebuild the container so that the new languages are picked up by services
    // that hold a list of languages.
    $this->rebuildContainer();
  }

  /**
   * Tests the version history page for translations.
   *
   * @param bool $bundleTranslatable
   *   Whether the entity bundle is translatable.
   */
  #[DataProvider('providerVersionHistoryTranslations')]
  public function testVersionHistoryTranslations(bool $bundleTranslatable): void {
    if ($bundleTranslatable) {
      $this->container->get('content_translation.manager')->setEnabled('entity_test_mul_revlog', 'entity_test_mul_revlog', TRUE);
    }

    $label = 'view all revisions,revert,delete revision';
    $entity = EntityTestMulWithRevisionLog::create([
      'name' => $label,
      'type' => 'entity_test_mul_revlog',
    ]);
    $this->assertSame($bundleTranslatable, $entity->isTranslatable());
    $entity->addTranslation('es', ['label' => 'version history test translations es']);
    $entity->save();

    $firstRevisionId = $entity->getRevisionId();

    $entity->setNewRevision();
    $entity->setName($label . ',2')
      ->save();

    $this->drupalGet($entity->toUrl('version-history'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementsCount('css', 'table tbody tr', 2);

    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage($entity->getEntityTypeId());
    $firstRevision = $storage->loadRevision($firstRevisionId);

    $this->assertSession()->linkByHrefExists($firstRevision->toUrl('revision-revert-form')->toString());
    $this->assertSession()->linkByHrefExists($firstRevision->toUrl('revision-delete-form')->toString());
    $this->assertSession()->linkByHrefNotExists($firstRevision->getTranslation('es')->toUrl('revision-revert-form')->toString());
    $this->assertSession()->linkByHrefNotExists($firstRevision->getTranslation('es')->toUrl('revision-delete-form')->toString());

    $this->drupalGet($entity->getTranslation('es')->toUrl('version-history'));
    $this->assertSession()->linkByHrefNotExistsExact($firstRevision->toUrl('revision-revert-form')->toString());
    $this->assertSession()->linkByHrefNotExistsExact($firstRevision->toUrl('revision-delete-form')->toString());
    $this->assertSession()->linkByHrefExists($firstRevision->getTranslation('es')->toUrl('revision-revert-form')->toString());
    $this->assertSession()->linkByHrefExists($firstRevision->getTranslation('es')->toUrl('revision-delete-form')->toString());
  }

  /**
   * Tests the current revision when the default revision has no changes.
   *
   * Revisions without translation changes are not listed, so the latest listed
   * revision that was a default revision is shown as the current revision.
   */
  public function testCurrentRevisionFilteredOut(): void {
    $this->container->get('content_translation.manager')->setEnabled('entity_test_mul_revlog', 'entity_test_mul_revlog', TRUE);

    // Changes to the name affect all translations, as the field is not
    // translatable.
    // Revision 1.
    $entity = EntityTestMulWithRevisionLog::create([
      'name' => 'view all revisions,revert,delete revision',
      'type' => 'entity_test_mul_revlog',
      'revision_log_message' => 'Revision 1',
    ]);
    $entity->addTranslation('es');
    $entity->save();
    $revision1 = $entity->getRevisionId();

    // Revision 2.
    $entity->setName('view all revisions,revert,delete revision,2');
    $entity->setRevisionLogMessage('Revision 2');
    $entity->setNewRevision();
    $entity->save();
    $revision2 = $entity->getRevisionId();

    // Revision 3 is the default revision, without translation changes.
    $entity->setRevisionLogMessage('Revision 3');
    $entity->setNewRevision();
    $entity->save();

    // Revision 4 is not a default revision.
    $entity->setName('view all revisions,revert,delete revision,4');
    $entity->setRevisionLogMessage('Revision 4');
    $entity->setNewRevision();
    $entity->isDefaultRevision(FALSE);
    $entity->save();

    $storage = $this->container->get('entity_type.manager')->getStorage($entity->getEntityTypeId());
    $assert = $this->assertSession();
    $currentRow = '//tr[contains(@class, "revision-current")]';

    foreach (['en', 'es'] as $langcode) {
      $this->drupalGet($entity->getTranslation($langcode)->toUrl('version-history'));
      // Revision 3 is filtered out.
      $assert->elementsCount('css', 'table tbody tr', 3);
      $assert->pageTextNotContains('Revision 3');

      // Revision 2 is the latest listed revision that was a default revision,
      // so it is shown as the current revision.
      $assert->elementsCount('xpath', $currentRow, 1);
      $assert->elementTextContains('xpath', $currentRow, 'Revision 2');
      $assert->elementTextContains('xpath', $currentRow, 'Current revision');
      $revision = $storage->loadRevision($revision2)->getTranslation($langcode);
      $assert->linkByHrefNotExists($revision->toUrl('revision-revert-form')->toString());
      $assert->linkByHrefNotExists($revision->toUrl('revision-delete-form')->toString());

      // Revisions 1 and 4 are not shown as the current revision.
      $assert->linkByHrefExists($storage->loadRevision($revision1)->getTranslation($langcode)->toUrl('revision-revert-form')->toString());
      $assert->pageTextContains('Revision 4');
    }
  }

  /**
   * Data provider for ::testVersionHistoryTranslations().
   *
   * @return array<string, array{bool}>
   *   Test cases.
   */
  public static function providerVersionHistoryTranslations(): array {
    return [
      'translatable bundle' => [TRUE],
      'bundle not translatable, translation exists' => [FALSE],
    ];
  }

}
