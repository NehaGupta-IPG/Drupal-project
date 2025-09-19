<?php

namespace Drupal\path_alias\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\path_alias\PathAliasInterface;
use Drupal\path_alias\PathAliasStorage;
use Drupal\path_alias\PathAliasStorageSchema;

/**
 * Defines the path_alias entity class.
 */
#[ContentEntityType(
  id: 'path_alias',
  label: new TranslatableMarkup('URL alias'),
  label_collection: new TranslatableMarkup('URL aliases'),
  label_singular: new TranslatableMarkup('URL alias'),
  label_plural: new TranslatableMarkup('URL aliases'),
  entity_keys: [
    'id' => 'id',
    'revision' => 'revision_id',
    'langcode' => 'langcode',
    'uuid' => 'uuid',
    'status' => 'status',
    'published' => 'status',
  ],
  handlers: [
    'storage' => PathAliasStorage::class,
    'storage_schema' => PathAliasStorageSchema::class,
  ],
  admin_permission: 'administer url aliases',
  base_table: 'path_alias',
  show_revision_ui: TRUE,
  revision_table: 'path_alias_revision',
  revision_data_table: 'path_alias_field_revision',
  label_count: [
    'singular' => '@count URL alias',
    'plural' => '@count URL aliases',
  ],
  list_cache_tags: ['route_match'],
  constraints: [
    'UniquePathAlias' => [],
  ],
  revision_metadata_keys: [
    'revision_default' => 'revision_default',
    'revision_user' => 'revision_uid',
    'revision_created' => 'revision_timestamp',
    'revision_log_message' => 'revision_log',
  ],
)]
class PathAlias extends EditorialContentEntityBase implements PathAliasInterface {

  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['path'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('System path'))
      ->setDescription(new TranslatableMarkup('The path that this alias belongs to.'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->addPropertyConstraints('value', [
        'Regex' => [
          'pattern' => '/^\//i',
          'message' => new TranslatableMarkup('The source path has to start with a slash.'),
        ],
      ])
      ->addPropertyConstraints('value', ['ValidPath' => []]);

    $fields['alias'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('URL alias'))
      ->setDescription(new TranslatableMarkup('An alias used with this path.'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->addPropertyConstraints('value', [
        'Regex' => [
          'pattern' => '/^\//i',
          'message' => new TranslatableMarkup('The alias path has to start with a slash.'),
        ],
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when alias was last edited.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    $fields['langcode']->setRevisionable(TRUE)->setDefaultValue(LanguageInterface::LANGCODE_NOT_SPECIFIED);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);
    $fields['status']->setTranslatable(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    // Trim the alias value of whitespace and slashes. Ensure to not trim the
    // slash on the left side.
    $alias = rtrim(trim($this->getAlias()), "\\/");
    $original_alias = isset($this->original) ?
    rtrim(trim($this->original->getAlias()), "\\/") : $alias;
    // If alias is changed create a new revision.
    $route_content_entity = $this->getRouteEntity();
    $time = \Drupal::service('datetime.time')->getRequestTime();
    if ($route_content_entity && $original_alias !== $alias) {
      $this->setNewRevision(TRUE);
      $current_user = \Drupal::currentUser();
      $this->setRevisionUserId($current_user->id());
      $this->setRevisionCreationTime($time);
    }
    $this->setChangedTime($time);
    $this->setAlias($alias);
  }

  /**
   * {@inheritdoc}
   */
  public function preSaveRevision(EntityStorageInterface $storage, \stdClass $record): void {
    parent::preSaveRevision($storage, $record);

    if (!$this->isNewRevision() && isset($this->original) && (!isset($record->revision_log) || $record->revision_log === '')) {
      // If we are updating an existing node without adding a new revision, we
      // need to make sure $entity->revision_log is reset whenever it is empty.
      // Therefore, this code allows us to avoid clobbering an existing log
      // entry with an empty one.
      $record->revision_log = $this->original->revision_log->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    $alias_manager = \Drupal::service('path_alias.manager');
    $alias_manager->cacheClear($this->getPath());
    if ($update) {
      $alias_manager->cacheClear($this->getOriginal()->getPath());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    $alias_manager = \Drupal::service('path_alias.manager');
    foreach ($entities as $entity) {
      $alias_manager->cacheClear($entity->getPath());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPath() {
    return $this->get('path')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setPath($path) {
    $this->set('path', $path);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAlias() {
    return $this->get('alias')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAlias($alias) {
    $this->set('alias', $alias);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getAlias();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    return ['route_match'];
  }

  /**
   * Gets the content entity from the current route.
   */
  public function getRouteEntity(): mixed {
    $route_match = \Drupal::routeMatch();
    // Entity will be found in the route parameters.
    if (($route = $route_match->getRouteObject())
    && ($parameters = $route->getOption('parameters'))) {
      // Determine if the current route represents an entity.
      foreach ($parameters as $name => $options) {
        if (isset($options['type'])
        && strpos($options['type'], 'entity:') === 0) {
          $entity = $route_match->getParameter($name);
          if ($entity instanceof ContentEntityInterface && $entity->hasLinkTemplate('canonical')) {
            return $entity->getEntityTypeId();
          }
        }
      }
    }
    return FALSE;
  }

}
