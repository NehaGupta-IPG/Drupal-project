<?php

namespace Drupal\media\Entity;

use Drupal\Core\Config\Action\Attribute\ActionMethod;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\media\Form\MediaTypeDeleteConfirmForm;
use Drupal\media\MediaTypeAccessControlHandler;
use Drupal\media\MediaTypeForm;
use Drupal\media\MediaTypeInterface;
use Drupal\media\MediaTypeListBuilder;
use Drupal\user\Entity\EntityPermissionsRouteProvider;

/**
 * Defines the Media type configuration entity.
 */
#[ConfigEntityType(
  id: 'media_type',
  label: new TranslatableMarkup('Media type'),
  label_collection: new TranslatableMarkup('Media types'),
  label_singular: new TranslatableMarkup('media type'),
  label_plural: new TranslatableMarkup('media types'),
  config_prefix: 'type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'status' => 'status',
  ],
  handlers: [
    'access' => MediaTypeAccessControlHandler::class,
    'form' => [
      'add' => MediaTypeForm::class,
      'edit' => MediaTypeForm::class,
      'delete' => MediaTypeDeleteConfirmForm::class,
    ],
    'list_builder' => MediaTypeListBuilder::class,
    'route_provider' => [
      'html' => DefaultHtmlRouteProvider::class,
      'permissions' => EntityPermissionsRouteProvider::class,
    ],
  ],
  links: [
    'add-form' => '/admin/structure/media/add',
    'edit-form' => '/admin/structure/media/manage/{media_type}',
    'delete-form' => '/admin/structure/media/manage/{media_type}/delete',
    'entity-permissions-form' => '/admin/structure/media/manage/{media_type}/permissions',
    'collection' => '/admin/structure/media',
  ],
  admin_permission: 'administer media types',
  bundle_of: 'media',
  label_count: [
    'singular' => '@count media type',
    'plural' => '@count media types',
  ],
  constraints: [
    'ImmutableProperties' => [
      'properties' => [
        'id',
        'source',
      ],
    ],
    'MediaMappingsConstraint' => [],
  ],
  config_export: [
    'id',
    'label',
    'description',
    'source',
    'queue_thumbnail_downloads',
    'new_revision',
    'source_configuration',
    'field_map',
    'status',
  ],
  )]
class MediaType extends ConfigEntityBundleBase implements MediaTypeInterface, EntityWithPluginCollectionInterface {

  /**
   * The machine name of this media type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the media type.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of this media type.
   *
   * @var string
   */
  protected $description;

  /**
   * The media source ID.
   *
   * @var string
   */
  protected $source;

  /**
   * Whether media items should be published by default.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * Whether thumbnail downloads are queued.
   *
   * @var bool
   *
   * @see \Drupal\media\MediaTypeInterface::thumbnailDownloadsAreQueued()
   */
  protected $queue_thumbnail_downloads = FALSE;

  /**
   * Default value of the 'Create new revision' checkbox of this media type.
   *
   * @var bool
   */
  protected $new_revision = FALSE;

  /**
   * The media source configuration.
   *
   * A media source can provide a configuration form with source plugin-specific
   * configuration settings, which must at least include a source_field element
   * containing a the name of the source field for the media type. The source
   * configuration is defined by, and used to load, the source plugin. See
   * \Drupal\media\MediaTypeInterface for an explanation of media sources.
   *
   * @var array
   *
   * @see \Drupal\media\MediaTypeInterface::getSource()
   */
  protected $source_configuration = [];

  /**
   * Lazy collection for the media source.
   *
   * @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection
   */
  protected $sourcePluginCollection;

  /**
   * The metadata field map.
   *
   * @var array
   *
   * @see \Drupal\media\MediaTypeInterface::getFieldMap()
   */
  protected $field_map = [];

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'source_configuration' => $this->sourcePluginCollection(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Set description'), pluralize: FALSE)]
  public function setDescription($description) {
    return $this->set('description', $description);
  }

  /**
   * {@inheritdoc}
   */
  public function thumbnailDownloadsAreQueued() {
    return $this->queue_thumbnail_downloads;
  }

  /**
   * {@inheritdoc}
   */
  public function setQueueThumbnailDownloadsStatus($queue_thumbnail_downloads) {
    return $this->set('queue_thumbnail_downloads', $queue_thumbnail_downloads);
  }

  /**
   * {@inheritdoc}
   */
  public function getSource() {
    return $this->sourcePluginCollection()->get($this->source);
  }

  /**
   * Returns media source lazy plugin collection.
   *
   * @return \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection|null
   *   The tag plugin collection or NULL if the plugin ID was not set yet.
   */
  protected function sourcePluginCollection() {
    if (!$this->sourcePluginCollection && $this->source) {
      $this->sourcePluginCollection = new DefaultSingleLazyPluginCollection(\Drupal::service('plugin.manager.media.source'), $this->source, $this->source_configuration);
    }
    return $this->sourcePluginCollection;
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function shouldCreateNewRevision() {
    return $this->new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function setNewRevision($new_revision) {
    return $this->set('new_revision', $new_revision);
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMap() {
    return $this->field_map;
  }

  /**
   * {@inheritdoc}
   */
  #[ActionMethod(adminLabel: new TranslatableMarkup('Set field mapping'), pluralize: FALSE)]
  public function setFieldMap(array $map) {
    return $this->set('field_map', $map);
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   *   This method does not return a value.
   */
  public function preSave(EntityStorageInterface $storage) {
    $this->prepareSourceFieldConfiguration();
    parent::preSave($storage);
  }

  /**
   * Sets the source field name before the config is saved.
   */
  protected function prepareSourceFieldConfiguration(): void {
    if ($this->isSyncing() || !$this->source) {
      return;
    }

    $source = $this->getSource();
    $configuration = $source->getConfiguration();
    if (!empty($configuration['source_field'])) {
      return;
    }

    $configuration['source_field'] = $source->createSourceField($this)->getName();
    $source->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if ($this->isSyncing() || !$this->source) {
      return;
    }

    // Ensure the source field exists for this media type.
    $source = $this->getSource();
    $source_field = $source->getSourceFieldDefinition($this);

    if (!$source_field) {
      $source_field = $source->createSourceField($this);

      /** @var \Drupal\field\FieldStorageConfigInterface $source_field_storage */
      $source_field_storage = $source_field->getFieldStorageDefinition();
      if ($source_field_storage->isNew()) {
        $source_field_storage->save();
      }

      $source_field->save();

      $display_repository = \Drupal::service('entity_display.repository');

      if ($source_field->isDisplayConfigurable('form')) {
        $form_display = $display_repository->getFormDisplay('media', $this->id());
        $source->prepareFormDisplay($this, $form_display);
        $form_display->save();
      }

      if ($source_field->isDisplayConfigurable('view')) {
        $view_display = $display_repository->getViewDisplay('media', $this->id());

        foreach (array_keys($view_display->getComponents()) as $name) {
          $view_display->removeComponent($name);
        }

        $source->prepareViewDisplay($this, $view_display);
        $view_display->save();
      }
    }
  }

}
