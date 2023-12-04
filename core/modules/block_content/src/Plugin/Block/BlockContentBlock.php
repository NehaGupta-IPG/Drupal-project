<?php

namespace Drupal\block_content\Plugin\Block;

use Drupal\block_content\BlockContentUuidLookup;
use Drupal\block_content\MissingBlockContentEntitySubscriber;
use Drupal\block_content\Plugin\Derivative\BlockContent;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a generic block type.
 */
#[Block(
  id: "block_content",
  admin_label: new TranslatableMarkup("Content block"),
  category: new TranslatableMarkup("Content block"),
  deriver: BlockContent::class
)]
class BlockContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The Plugin Block Manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Drupal account to use for checking for access to block.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The block content entity.
   *
   * @var \Drupal\block_content\BlockContentInterface
   */
  protected $blockContent;

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Redirect destination.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The block content UUID lookup service.
   *
   * @var \Drupal\block_content\BlockContentUuidLookup
   */
  protected $uuidLookup;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Missing block content entities repository.
   *
   * @var \Drupal\block_content\MissingBlockContentEntitySubscriber
   */
  protected $missingBlockContentEntitySubscriber;

  /**
   * Constructs a new BlockContentBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The Plugin Block Manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account for which view access should be checked.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   *   Redirect destination.
   * @param \Drupal\block_content\BlockContentUuidLookup $uuid_lookup
   *   UUID lookup.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   Display repository.
   * @param \Drupal\block_content\MissingBlockContentEntitySubscriber $missingBlockContentEntitySubscriber
   *   Missing block content entities subscriber.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BlockManagerInterface $block_manager, EntityTypeManagerInterface $entity_type_manager, AccountInterface $account, StateInterface $state, RedirectDestinationInterface $redirectDestination, BlockContentUuidLookup $uuid_lookup, EntityDisplayRepositoryInterface $entity_display_repository, MissingBlockContentEntitySubscriber $missingBlockContentEntitySubscriber) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->blockManager = $block_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->state = $state;
    $this->redirectDestination = $redirectDestination;
    $this->uuidLookup = $uuid_lookup;
    $this->entityDisplayRepository = $entity_display_repository;
    $this->missingBlockContentEntitySubscriber = $missingBlockContentEntitySubscriber;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.block'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('url_generator'),
      $container->get('block_content.uuid_lookup'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'view_mode' => 'full',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $block = $this->getEntity();
    if (!$block) {
      return $form;
    }
    $options = $this->entityDisplayRepository->getViewModeOptionsByBundle('block_content', $block->bundle());

    $form['view_mode'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('View mode'),
      '#description' => $this->t('Output the block in this view mode.'),
      '#default_value' => $this->configuration['view_mode'],
      '#access' => (count($options) > 1),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    // Invalidate the block cache to update content block-based derivatives.
    $this->configuration['view_mode'] = $form_state->getValue('view_mode');
    $this->blockManager->clearCachedDefinitions();
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if ($this->getEntity()) {
      return $this->getEntity()->access('view', $account, TRUE);
    }
    // Missing items that were found during config import.
    // @see \Drupal\block_content\MissingBlockContentEntitySubscriber::onMissingContent
    // @see \Drupal\block_content\Plugin\Derivative\BlockContent::getDerivativeDefinitions
    if ($this->missingBlockContentEntitySubscriber->isMissing($this->getDerivativeId())) {
      return AccessResult::allowedIfHasPermission($account, 'administer blocks')
        ->addCacheTags(['block_content_list']);
    }
    return AccessResult::forbidden();
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    if ($block = $this->getEntity()) {
      return $this->entityTypeManager->getViewBuilder($block->getEntityTypeId())->view($block, $this->configuration['view_mode']);
    }
    // Missing items that were found during config import.
    // @see \Drupal\block_content\MissingBlockContentEntitySubscriber::onMissingContent
    // @see \Drupal\block_content\Plugin\Derivative\BlockContent::getDerivativeDefinitions
    if ($missing = $this->missingBlockContentEntitySubscriber->isMissing($this->getDerivativeId())) {
      return [
        '#markup' => $this->t('The content of the (%bundle) block is missing. <a href=":url">Add missing content</a>.', [
          '%bundle' => $this->entityTypeManager->getStorage('block_content_type')->load($missing['bundle'])->label(),
          ':url' => Url::fromRoute('block_content.add_missing', [
            'uuid' => $missing['uuid'],
            'block_content_type' => $missing['bundle'],
          ], ['query' => $this->redirectDestination->getAsArray()])->toString(),
        ]),
        '#access' => $this->account->hasPermission('administer blocks'),
      ];
    }
    // Fallback to the broken behaviour that occurs when a block plugin is
    // missing.
    // @see \Drupal\Core\Block\Plugin\Block\Broken::brokenMessage
    return [
      '#markup' => $this->t('This block is broken or missing. You may be missing content or you might need to enable the original module.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function createPlaceholder(): bool {
    return TRUE;
  }

  /**
   * Loads the block content entity of the block.
   *
   * @return \Drupal\block_content\BlockContentInterface|null
   *   The block content entity.
   */
  protected function getEntity() {
    if (!isset($this->blockContent)) {
      $uuid = $this->getDerivativeId();
      if ($id = $this->uuidLookup->get($uuid)) {
        $this->blockContent = $this->entityTypeManager->getStorage('block_content')->load($id);
      }
    }
    return $this->blockContent;
  }

}
