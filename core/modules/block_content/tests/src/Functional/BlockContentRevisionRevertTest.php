<?php

declare(strict_types=1);

namespace Drupal\Tests\block_content\Functional;

use Drupal\Core\Entity\Form\RevisionRevertForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Block content revision form test.
 */
#[CoversClass(RevisionRevertForm::class)]
#[Group('block_content')]
#[RunTestsInSeparateProcesses]
class BlockContentRevisionRevertTest extends BlockContentTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $permissions = [
    'view any basic block content history',
    'revert any basic block content revisions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests revision revert.
   */
  public function testRevertForm(): void {
    $entity = $this->createBlockContent(save: FALSE)
      ->setRevisionCreationTime((new \DateTimeImmutable('11 January 2009 4pm'))->getTimestamp())
      ->setRevisionTranslationAffected(TRUE);
    $entity->setNewRevision();
    $entity->save();
    $revisionId = $entity->getRevisionId();

    // Cannot revert latest revision.
    $this->drupalGet($entity->toUrl('revision-revert-form'));
    $this->assertSession()->statusCodeEquals(403);

    // Create two new non default revisions.
    $entity
      ->setRevisionCreationTime((new \DateTimeImmutable('11 January 2009 5pm'))->getTimestamp())
      ->setRevisionTranslationAffected(TRUE)
      ->setNewRevision();
    $entity->isDefaultRevision(FALSE);
    $entity->save();
    $firstNonDefaultRevisionId = $entity->getRevisionId();

    $entity
      ->setRevisionCreationTime((new \DateTimeImmutable('11 January 2009 6pm'))->getTimestamp())
      ->setRevisionTranslationAffected(TRUE)
      ->setNewRevision();
    $entity->isDefaultRevision(FALSE);
    $entity->save();
    $latestRevisionId = $entity->getRevisionId();

    // Can't revert the latest revision.
    $latestRevision = \Drupal::entityTypeManager()->getStorage('block_content')
      ->loadRevision($latestRevisionId);
    $this->drupalGet($latestRevision->toUrl('revision-revert-form'));
    $this->assertSession()->statusCodeEquals(403);
    $this->assertFalse($latestRevision->access('revert', $this->adminUser, FALSE));

    // Can revert to the non-latest non-default revision.
    $firstNonDefaultRevision = \Drupal::entityTypeManager()->getStorage('block_content')
      ->loadRevision($firstNonDefaultRevisionId);
    $this->drupalGet($firstNonDefaultRevision->toUrl('revision-revert-form'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertTrue($firstNonDefaultRevision->access('revert', $this->adminUser, FALSE));
    $this->assertSession()->pageTextContains('Are you sure you want to revert to the revision from Sun, 11 Jan 2009 - 17:00?');
    $this->assertSession()->buttonExists('Revert');
    $this->assertSession()->linkExists('Cancel');

    // Can revert to the default revision now that it is not the latest.
    $defaultRevision = \Drupal::entityTypeManager()->getStorage('block_content')
      ->loadRevision($revisionId);
    $this->drupalGet($defaultRevision->toUrl('revision-revert-form'));
    $this->assertSession()->pageTextContains('Are you sure you want to revert to the revision from Sun, 11 Jan 2009 - 16:00?');
    $this->assertSession()->buttonExists('Revert');
    $this->assertSession()->linkExists('Cancel');
    $this->assertTrue($defaultRevision->access('revert', $this->adminUser, FALSE));

    $countRevisions = static function (): int {
      return (int) \Drupal::entityTypeManager()->getStorage('block_content')
        ->getQuery()
        ->accessCheck(FALSE)
        ->allRevisions()
        ->count()
        ->execute();
    };

    $count = $countRevisions();

    // Revert to the non-latest non-default revision.
    $this->drupalGet($firstNonDefaultRevision->toUrl('revision-revert-form'));
    $this->submitForm([], 'Revert');
    $this->assertEquals($count + 1, $countRevisions());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals(sprintf('admin/content/block/%s/revisions', $entity->id()));
    $this->assertSession()->pageTextContains(sprintf('basic %s has been reverted to the revision from Sun, 11 Jan 2009 - 17:00.', $entity->label()));
    // Four rows expected: The new reverted revision, and the three created revisions.
    $this->assertSession()->elementsCount('css', 'table tbody tr', 4);

    // Revert to the default revision.
    $this->drupalGet($defaultRevision->toUrl('revision-revert-form'));
    $this->submitForm([], 'Revert');
    $this->assertEquals($count + 2, $countRevisions());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals(sprintf('admin/content/block/%s/revisions', $entity->id()));
    $this->assertSession()->pageTextContains(sprintf('basic %s has been reverted to the revision from Sun, 11 Jan 2009 - 16:00.', $entity->label()));
    // Five rows expected: The two new reverted revision, and the three created revisions.
    $this->assertSession()->elementsCount('css', 'table tbody tr', 5);
  }

}
