<?php

declare(strict_types=1);

namespace Drupal\Tests\field\Kernel\KernelString;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\StringFormatter;
use Drupal\Core\Form\FormState;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\entity_test\Entity\EntityTestLabel;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Tests the creation of text fields.
 */
#[Group('field')]
#[RunTestsInSeparateProcesses]
class StringFormatterTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'entity_test',
    'system',
    'user',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var string
   */
  protected $entityType;

  /**
   * @var string
   */
  protected $bundle;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   */
  protected $display;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Configure the theme system.
    $this->installConfig(['system', 'field']);
    $this->installEntitySchema('entity_test_rev');
    $this->setUpCurrentUser(permissions: [
      'view test entity',
      'administer entity_test content',
    ]);

    $this->entityType = 'entity_test_rev';
    $this->bundle = $this->entityType;
    $this->fieldName = $this->randomMachineName();

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'type' => 'string',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->bundle,
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    $this->display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($this->entityType, $this->bundle)
      ->setComponent($this->fieldName, [
        'type' => 'string',
        'settings' => [],
      ]);
    $this->display->save();

    $this->entityTypeManager = \Drupal::entityTypeManager();
  }

  /**
   * Renders fields of a given entity with a given display.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity object with attached fields to render.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The display to render the fields in.
   *
   * @return string
   *   The rendered entity fields.
   */
  protected function renderEntityFields(FieldableEntityInterface $entity, EntityViewDisplayInterface $display): string {
    $content = $display->build($entity);
    return $this->render($content);
  }

  /**
   * Tests string formatter output.
   */
  public function testStringFormatter(): void {
    $value = $this->randomString();
    $value .= "\n\n<strong>" . $this->randomString() . '</strong>';
    $value .= "\n\n" . $this->randomString();

    $entity = EntityTestRev::create(['name' => 'view revision']);
    $entity->{$this->fieldName}->value = $value;

    // Verify that all HTML is escaped and newlines are retained.
    $this->renderEntityFields($entity, $this->display);
    $this->assertNoRaw($value);
    $this->assertRaw(nl2br(Html::escape($value)));

    // Verify the cache tags.
    $build = $entity->{$this->fieldName}->view();
    $this->assertTrue(!isset($build[0]['#cache']), 'The string formatter has no cache tags.');

    $value = $this->randomMachineName();
    $entity->{$this->fieldName}->value = $value;
    $entity->save();

    // Set the formatter to link to the entity.
    $this->display->setComponent($this->fieldName, [
      'type' => 'string',
      'settings' => [
        'link_to_entity' => TRUE,
      ],
    ]);
    $this->display->save();

    $this->renderEntityFields($entity, $this->display);
    $this->assertLink($value, 0);
    $this->assertLinkByHref($entity->toUrl()->toString());

    // $entity->toUrl('revision') falls back to the canonical URL if this is no
    // revision.
    $this->assertLinkByHref($entity->toUrl('revision')->toString());

    // Make the entity a new revision.
    $old_revision_id = $entity->getRevisionId();
    $entity->setNewRevision(TRUE);
    $value2 = $this->randomMachineName();
    $entity->{$this->fieldName}->value = $value2;
    $entity->save();
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('entity_test_rev');
    $entity_new_revision = $storage->loadRevision($old_revision_id);
    $this->assertInstanceOf(EntityTestRev::class, $entity_new_revision);

    $this->renderEntityFields($entity, $this->display);
    $this->assertLink($value2, 0);
    $this->assertLinkByHref($entity->toUrl('revision')->toString());

    $this->renderEntityFields($entity_new_revision, $this->display);
    $this->assertLink($value, 0);
    $this->assertLinkByHref('/entity_test_rev/' . $entity_new_revision->id() . '/revision/' . $entity_new_revision->getRevisionId() . '/view');

    // Check that linking to a revisionable entity works if the entity type does
    // not specify a 'revision' link template.
    $entity_type = clone $this->entityTypeManager->getDefinition('entity_test_rev');
    $link_templates = $entity_type->getLinkTemplates();
    unset($link_templates['revision']);
    $entity_type->set('links', $link_templates);
    \Drupal::state()->set('entity_test_rev.entity_type', $entity_type);
    $this->entityTypeManager->clearCachedDefinitions();

    $this->renderEntityFields($entity_new_revision, $this->display);
    $this->assertLink($value, 0);
    $this->assertLinkByHref($entity->toUrl('canonical')->toString());
  }

  /**
   * Tests the default settings and the tag selector.
   */
  public function testTagSettings(): void {
    $this->assertSame('', StringFormatter::defaultSettings()['tag']);
    $formatter = $this->display->getRenderer($this->fieldName);
    $form = $formatter->settingsForm([], new FormState());
    $this->assertSame('Display as', (string) $form['tag']['#title']);
    $this->assertInstanceOf(TranslatableMarkup::class, $form['tag']['#options']['']);
    $this->assertSame('- None -', (string) $form['tag']['#options']['']);
    $this->assertSame(['', 'span', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], array_keys($form['tag']['#options']));
    $this->assertSame([], $formatter->settingsSummary());
    $formatter->setSetting('tag', 'h2');
    $this->assertSame('Displayed as h2', (string) $formatter->settingsSummary()[0]);
  }

  /**
   * Tests escaping and wrapping each value, with and without a link.
   */
  #[TestWith([''])]
  #[TestWith(['span'])]
  #[TestWith(['div'])]
  #[TestWith(['h1'])]
  #[TestWith(['h2'])]
  #[TestWith(['h3'])]
  #[TestWith(['h4'])]
  #[TestWith(['h5'])]
  #[TestWith(['h6'])]
  public function testTags(string $tag): void {
    $values = ['<strong>First & "value"</strong>', "Second <script>alert('test')</script>\nline"];
    $entity = EntityTestRev::create([
      'name' => 'Tagged values',
      $this->fieldName => $values,
    ]);
    $entity->save();
    foreach ([FALSE, TRUE] as $linked) {
      $this->display->setComponent($this->fieldName, [
        'type' => 'string',
        'settings' => ['tag' => $tag, 'link_to_entity' => $linked],
      ]);
      $formatter = $this->display->getRenderer($this->fieldName);
      $elements = $formatter->viewElements($entity->{$this->fieldName}, 'en');
      foreach ($values as $delta => $value) {
        $element = $elements[$delta];
        $output = (string) $this->container->get('renderer')->renderInIsolation($element);
        $expected = nl2br(Html::escape($value));
        if ($linked) {
          $expected = '<a href="' . $entity->toUrl()->toString() . '" hreflang="en">' . $expected . '</a>';
        }
        if ($tag !== '') {
          $expected = "<$tag>$expected</$tag>";
        }
        $this->assertSame($expected, $output);
      }
      $settings = $formatter->getSettings();
      $typed_config = $this->container->get('config.typed')->createFromNameAndData('field.formatter.settings.string', $settings);
      $this->assertCount(0, $typed_config->validate());
    }
    foreach (['p', 'script', '<h1>'] as $invalid_tag) {
      $settings['tag'] = $invalid_tag;
      $typed_config = $this->container->get('config.typed')->createFromNameAndData('field.formatter.settings.string', $settings);
      $this->assertGreaterThan(0, $typed_config->validate()->count());
    }
  }

  /**
   * Tests wrapping the inherited language formatter's plain text output.
   */
  public function testLanguageTag(): void {
    $field_name = 'field_language';
    $storage = FieldStorageConfig::create([
      'entity_type' => $this->entityType,
      'field_name' => $field_name,
      'type' => 'language',
    ]);
    $storage->save();
    FieldConfig::create([
      'field_storage' => $storage,
      'bundle' => $this->bundle,
    ])->save();
    // Reload the display so its cached field definitions include the new field.
    $this->display = $this->entityTypeManager->getStorage('entity_view_display')->loadUnchanged($this->display->id());
    $entity = EntityTestRev::create([$field_name => 'en']);
    $this->display->setComponent($field_name, ['type' => 'language']);
    $formatter = $this->display->getRenderer($field_name);
    $this->assertSame('', $formatter->getSetting('tag'));
    $elements = $formatter->viewElements($entity->{$field_name}, 'en');
    $this->assertSame('English', (string) $this->container->get('renderer')->renderInIsolation($elements[0]));
    $formatter->setSetting('tag', 'span');
    $elements = $formatter->viewElements($entity->{$field_name}, 'en');
    $this->assertSame('<span>English</span>', (string) $this->container->get('renderer')->renderInIsolation($elements[0]));
    $typed_config = $this->container->get('config.typed')->createFromNameAndData('field.formatter.settings.language', $formatter->getSettings());
    $this->assertCount(0, $typed_config->validate());
  }

  /**
   * Tests that wrapping preserves link access cacheability and denied output.
   */
  public function testTagLinkAccess(): void {
    $entity = EntityTestRev::create([
      'name' => 'Access test',
      $this->fieldName => '<strong>Value</strong>',
    ]);
    $entity->save();
    foreach (['canonical', 'edit-form'] as $link_rel) {
      $this->display->setComponent($this->fieldName, [
        'type' => 'string',
        'settings' => ['link_to_entity' => TRUE, 'link_rel' => $link_rel],
      ]);
      $formatter = $this->display->getRenderer($this->fieldName);
      $plain = $formatter->viewElements($entity->{$this->fieldName}, 'en');
      $formatter->setSetting('tag', 'div');
      $wrapped = $formatter->viewElements($entity->{$this->fieldName}, 'en');
      $this->assertSame($plain['#cache'], $wrapped['#cache']);
      $this->assertSame('link', $wrapped[0]['#type']);
      $this->assertSame($entity->toUrl($link_rel)->toString(), $wrapped[0]['#url']->toString());
    }

    // A user without edit permission gets wrapped text instead of a link.
    $this->setUpCurrentUser(permissions: ['view test entity']);
    $access = $entity->toUrl('edit-form')->access(return_as_object: TRUE);
    $this->assertFalse($access->isAllowed());
    $elements = $formatter->viewElements($entity->{$this->fieldName}, 'en');
    $this->assertSame('inline_template', $elements[0]['#type']);
    $this->assertSame($access->getCacheContexts(), $elements['#cache']['contexts']);
    $this->assertSame('<div>&lt;strong&gt;Value&lt;/strong&gt;</div>', (string) $this->container->get('renderer')->renderInIsolation($elements[0]));
  }

  /**
   * Test "link_to_entity" feature on fields which are added to config entity.
   */
  #[TestWith(['canonical'])]
  #[TestWith(['edit-form'])]
  public function testLinkToContentForEntitiesWithMissingLinkTemplate(string $link_rel): void {
    $this->enableModules(['entity_test']);
    $this->installEntitySchema('entity_test_label');
    $field_name = 'test_field_name';
    $entity_type = $bundle = 'entity_test_label';

    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'string',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $entity_type,
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'type' => 'string',
        'settings' => [
          'link_to_entity' => TRUE,
          'link_rel' => $link_rel,
        ],
        'region' => 'content',
      ]);
    $display->save();

    $value = $this->randomMachineName();
    $entity = EntityTestLabel::create(['name' => 'test']);
    // To prevent this test from falsely passing, ensure that there is, in fact,
    // no link template.
    $this->assertFalse($entity->getEntityType()->hasLinkTemplate($link_rel));
    $entity->{$field_name}->value = $value;
    $entity->save();

    $this->renderEntityFields($entity, $display);
    $this->assertRaw($value);
    // No links should appear in the main content, since this entity type has no
    // link templates.
    $crawler = new Crawler($this->getRawContent());
    $this->assertCount(0, $crawler->filter('main a'));
  }

  /**
   * Test linking to an entity's edit form.
   */
  public function testLinkToEntityEditForm(): void {
    $this->installEntitySchema('entity_test');
    $field_name = 'test_field_name';
    $entity_type = $bundle = 'entity_test';

    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'type' => 'string',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $entity_type,
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay($entity_type, $bundle)
      ->setComponent($field_name, [
        'type' => 'string',
        'settings' => [
          'link_to_entity' => TRUE,
          'link_rel' => 'edit-form',
        ],
        'region' => 'content',
      ]);
    $display->save();

    $value = $this->randomMachineName();
    $entity = EntityTest::create([
      'name' => 'test',
      $field_name => $value,
    ]);
    $entity->save();

    $this->assertStringContainsString(
      (string) $entity->toLink($value, 'edit-form')->toString(),
      $this->renderEntityFields($entity, $display),
    );
  }

}
