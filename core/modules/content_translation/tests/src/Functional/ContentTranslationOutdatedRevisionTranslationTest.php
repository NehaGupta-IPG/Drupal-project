<?php

declare(strict_types=1);

namespace Drupal\Tests\content_translation\Functional;

use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Flag as outdated" functionality with revision translations.
 */
#[Group('content_translation')]
#[RunTestsInSeparateProcesses]
class ContentTranslationOutdatedRevisionTranslationTest extends ContentTranslationPendingRevisionTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->doSetup();
    $this->enableContentModeration();
  }

  /**
   * Tests that outdated revision translations work correctly.
   */
  public function testFlagAsOutdated(): void {
    // Create a test node.
    $values = [
      'title' => 'Test 1.1 EN',
      'moderation_state' => 'published',
    ];
    $id = $this->createEntity($values, 'en');
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage->load($id);

    // Add a published Italian translation.
    $add_translation_url = Url::fromRoute("entity.{$this->entityTypeId}.content_translation_add",
      [
        $entity->getEntityTypeId() => $id,
        'source' => 'en',
        'target' => 'it',
      ],
      [
        'language' => ConfigurableLanguage::load('it'),
        'absolute' => FALSE,
      ]
    );
    $this->drupalGet($add_translation_url);
    $edit = [
      'title[0][value]' => 'Test 1.2 IT',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');

    // Add a published French translation.
    $add_translation_url = Url::fromRoute("entity.{$this->entityTypeId}.content_translation_add",
      [
        $entity->getEntityTypeId() => $id,
        'source' => 'en',
        'target' => 'fr',
      ],
      [
        'language' => ConfigurableLanguage::load('fr'),
        'absolute' => FALSE,
      ]
    );
    $this->drupalGet($add_translation_url);
    $edit = [
      'title[0][value]' => 'Test 1.3 FR',
      'moderation_state[0][state]' => 'published',
    ];
    $this->submitForm($edit, 'Save (this translation)');

    // Create an English draft.
    $edit = [
      'title[0][value]' => 'Test 1.4 EN',
      'moderation_state[0][state]' => 'draft',
    ];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->storage->loadUnchanged($id);
    $en_edit_url = $this->getEditUrl($entity);
    $this->drupalGet($en_edit_url);
    $this->submitForm($edit, 'Save (this translation)');
    // Create an Italian draft.
    $edit = [
      'title[0][value]' => 'Test 1.5 IT',
      'moderation_state[0][state]' => 'draft',
    ];
    $it_edit_url = $this->getEditUrl($entity->getTranslation('it'));
    $this->drupalGet($it_edit_url);
    $this->submitForm($edit, t('Save (this translation)'));

    // Create a French draft.
    $edit = [
      'title[0][value]' => 'Test 1.6 FR',
      'moderation_state[0][state]' => 'draft',
    ];
    $fr_edit_url = $this->getEditUrl($entity->getTranslation('fr'));
    $this->drupalGet($fr_edit_url);
    $this->submitForm($edit, t('Save (this translation)'));

    // Create another English draft and mark the other translations as outdated.
    $edit = [
      'title[0][value]' => 'Test 1.7 EN',
      'moderation_state[0][state]' => 'draft',
      'content_translation[retranslate]' => 1,
    ];
    $this->drupalGet($en_edit_url);
    $this->submitForm($edit, t('Save (this translation)'));

    // Verify that the expected translation drafts are loaded in the related
    // entity forms.
    $this->drupalGet($en_edit_url);
    $this->assertSession()->pageTextContains('Test 1.7 EN');
    $this->drupalGet($it_edit_url);
    $this->assertSession()->pageTextContains('Test 1.5 IT');
    $this->drupalGet($fr_edit_url);
    $this->assertSession()->pageTextContains('Test 1.6 FR');
  }

}
