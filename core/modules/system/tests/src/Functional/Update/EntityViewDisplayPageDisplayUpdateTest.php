<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Functional\Update;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the upgrade path to set the page display flag on entity view displays.
 *
 * @see system_post_update_set_entity_view_display_page_display()
 */
#[Group('Update')]
#[RunTestsInSeparateProcesses]
class EntityViewDisplayPageDisplayUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../system/tests/fixtures/update/drupal-10.3.0.filled.standard.php.gz',
    ];
  }

  /**
   * Tests that entity view display update works.
   */
  public function testEntityViewDisplaySetPageDisplay(): void {
    $entityDisplayRepository = \Drupal::service(EntityDisplayRepositoryInterface::class);
    $defaultPageDisplay = $entityDisplayRepository->getViewDisplay('node', 'page', 'default');
    $fullPageDisplay = $entityDisplayRepository->getViewDisplay('node', 'page', 'full');
    $fullArticleDisplay = $entityDisplayRepository->getViewDisplay('node', 'article', 'full');

    // The full display does not exist in the standard profile.
    self::assertTrue($fullPageDisplay->isNew());
    self::assertTrue($fullArticleDisplay->isNew());
    // But inherits from the default display.
    self::assertFalse($defaultPageDisplay->isNew());

    // Check the state before the update.
    self::assertFalse($defaultPageDisplay->hasPageDisplay());
    // These should trigger deprecation errors and be set by
    // \Drupal\Core\Entity\Entity\EntityViewDisplay::__construct
    // @see \Drupal\Core\Entity\Entity\EntityViewDisplay::__construct
    self::assertTrue($fullArticleDisplay->hasPageDisplay());
    self::assertTrue($fullPageDisplay->hasPageDisplay());

    // We save the full article, so we can test the update path for it.
    $fullArticleDisplay->save();

    $this->runUpdates();

    // Reload all of these.
    $defaultPageDisplay = $entityDisplayRepository->getViewDisplay('node', 'page', 'default');
    $fullPageDisplay = $entityDisplayRepository->getViewDisplay('node', 'page', 'full');
    $fullArticleDisplay = $entityDisplayRepository->getViewDisplay('node', 'article', 'full');

    // Validate the update worked.
    self::assertFalse($defaultPageDisplay->hasPageDisplay());
    self::assertTrue($fullPageDisplay->hasPageDisplay());

    // The full page display should still not exist.
    self::assertTrue($fullPageDisplay->isNew());

    // But the article one should.
    self::assertFalse($fullArticleDisplay->isNew());
    // And should have the page display value set.
    self::assertTrue($fullArticleDisplay->hasPageDisplay());
  }

}
