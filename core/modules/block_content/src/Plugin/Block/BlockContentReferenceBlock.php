<?php

declare(strict_types=1);

namespace Drupal\block_content\Plugin\Block;

use Drupal\block_content\BlockContentInterface;
use Drupal\block_content\BlockContentUuidLookup;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

// cspell:ignore autocompleteclose

/**
 * Defines a block to render a block_content entity by reference.
 */
#[Block(
  id: "block_content_reference",
  admin_label: new TranslatableMarkup("Content block reference"),
  category: new TranslatableMarkup("Content block"),
)]
class BlockContentReferenceBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use DependencySerializationTrait;

  /**
   * The block content entity.
   *
   * @var \Drupal\block_content\BlockContentInterface|null
   */
  protected ?BlockContentInterface $blockContent = NULL;

  /**
   * Constructs a new BlockContentReferenceBlock.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly BlockContentUuidLookup $uuidLookup,
    protected readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('block_content.uuid_lookup'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'block_content_uuid' => '',
      'view_mode' => 'full',
    ];
  }

  /**
   * Overrides \Drupal\Core\Block\BlockBase::blockForm().
   *
   * Adds body and description fields to the block configuration form.
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $block = $this->blockContent;

    if (!$block && isset($this->configuration['block_content_uuid'])) {
      $block = $this->getEntity();
    }

    // Adding an entity_autocomplete element to select the block content.
    $form['block_content_selection'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'block_content',
      '#tags' => TRUE,
      '#required' => TRUE,
      '#default_value' => $this->blockContent,
      '#selection_handler' => 'default',
      '#title' => $this->t('Block content'),
      '#description' => $this->t('Search by Block Content title.'),
      '#ajax' => [
        'callback' => [$this, 'loadBlockContent'],
        'wrapper' => 'view-mode-container',
        'event' => 'autocompleteclose',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Processing'),
        ],
      ],
    ];

    $form['view_mode_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'view-mode-container'],
    ];

    if ($block) {
      $options = $this->entityDisplayRepository->getViewModeOptionsByBundle('block_content', $block->bundle());

      $form['view_mode_container']['view_mode'] = [
        '#type' => 'select',
        '#options' => $options,
        '#title' => $this->t('View mode'),
        '#description' => $this->t('Output the block in this view mode.'),
        '#default_value' => $this->configuration['view_mode'],
        '#access' => count($options) > 1,
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    if (!isset($this->blockContent)) {
      $id = $form_state->getValue('block_content_selection');
      $id = $id[0]['target_id'];
      $block = $this->entityTypeManager->getStorage('block_content')->load($id);
    }
    else {
      $block = $this->blockContent;
    }
    $this->configuration['block_content_uuid'] = $block->uuid();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($this->getEntity()) {
      return $this->getEntity()->access('view', $account, TRUE);
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $block = $this->getEntity();
    if ($block instanceof BlockContentInterface) {
      return $this->entityTypeManager->getViewBuilder($block->getEntityTypeId())->view($block, $this->configuration['view_mode']);
    }
    return [];
  }

  /**
   * Loads the block content entity of the block.
   *
   * @return \Drupal\block_content\BlockContentInterface|null
   *   The block content entity.
   */
  protected function getEntity(): ?BlockContentInterface {
    if (!isset($this->blockContent)) {
      if (!empty($this->configuration['block_content_uuid'])) {
        if ($id = $this->uuidLookup->get($this->configuration['block_content_uuid'])) {
          $this->blockContent = $this->entityTypeManager->getStorage('block_content')->load($id);
        }
      }
    }
    return $this->blockContent;
  }

  /**
   * Ajax callback for the block content entity_autocomplete.
   */
  public function loadBlockContent(array $form, FormStateInterface $form_state): array {
    $id = $form_state->getValue(['settings', 'block_content_selection']);
    if ($id) {
      $id = $id[0]['target_id'];
      $this->blockContent = $this->entityTypeManager->getStorage('block_content')->load($id);
    }
    $form_state->setRebuild();
    return $form['settings']['view_mode_container'];
  }

}
