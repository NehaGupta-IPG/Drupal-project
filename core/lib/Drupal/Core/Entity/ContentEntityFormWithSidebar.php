<?php

namespace Drupal\Core\Entity;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Entity form variant for content entity types.
 *
 * @see \Drupal\Core\ContentEntityBase
 */
class ContentEntityFormWithSidebar extends ContentEntityForm {

  /**
   * The current user object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a ContentEntityFormWithSidebar object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, ?AccountInterface $current_user = NULL, ?DateFormatterInterface $date_formatter = NULL) {
    $this->entityRepository = $entity_repository;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->time = $time;

    if (is_null($current_user)) {
      $current_user = \Drupal::currentUser();
    }
    $this->currentUser = $current_user;

    if (is_null($date_formatter)) {
      $date_formatter = \Drupal::service('date.formatter');
    }
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): void {

    $form['#theme'] = ['content_entity_form'];

    // Advanced tab must be the first, because other fields rely on that.
    if (!isset($form['advanced'])) {
      $form['advanced'] = [
        '#type' => 'container',
        '#weight' => 99,
        '#accordion' => TRUE,
      ];
    }

    $form = parent::form($form, $form_state);
    $form['meta'] = [
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -10,
      '#title' => $this->t('Status'),
      '#attributes' => ['class' => ['entity-meta__header']],
      '#tree' => TRUE,
    ];

    $form['advanced']['#attributes']['class'][] = 'entity-meta';

    if ($this->entity instanceof EntityPublishedInterface) {
      $form['meta']['published'] = [
        '#type' => 'item',
        '#markup' => $this->entity->isPublished() ? $this->t('Published') : $this->t('Not published'),
        '#access' => !$this->entity->isNew(),
        '#wrapper_attributes' => ['class' => ['entity-meta__title']],
      ];
    }
    if ($this->entity instanceof EntityChangedInterface) {
      $form['meta']['changed'] = [
        '#type' => 'item',
        '#title' => $this->t('Last saved'),
        '#markup' => !$this->entity->isNew() ? $this->dateFormatter->format($this->entity->getChangedTime(), 'short') : $this->t('Not saved yet'),
        '#wrapper_attributes' => ['class' => ['entity-meta__last-saved']],
      ];
    }
    if ($this->entity instanceof EntityOwnerInterface && $this->entity->getOwner()) {
      $form['meta']['author'] = [
        '#type' => 'item',
        '#title' => $this->t('Author'),
        '#markup' => $this->entity->getOwner()->getAccountName(),
        '#wrapper_attributes' => ['class' => ['entity-meta__author']],
      ];
    }

    $form['status']['#group'] = 'footer';

    // Author information for administrators.
    $owner_key = $this->entity->getEntityType()->getKey('owner');
    if (($this->entity instanceof EntityOwnerInterface && isset($form[$owner_key])) || isset($form['created'])) {
      $form['author'] = [
        '#type' => 'details',
        '#title' => $this->t('Authoring information'),
        '#group' => 'advanced',
        '#weight' => 90,
        '#optional' => TRUE,
      ];

      if (isset($form[$owner_key])) {
        $form[$owner_key]['#group'] = 'author';
      }

      if (isset($form['created'])) {
        $form['created']['#group'] = 'author';
      }
    }

    // Content entity forms do not use the parent's #after_build callback
    // because they only need to rebuild the entity in the validation and the
    // submit handler because Field API uses its own #after_build callback for
    // its widgets.
    unset($form['#after_build']);

    $this->getFormDisplay($form_state)->buildForm($this->entity, $form, $form_state);
    // Allow modules to act before and after form language is updated.
    $form['#entity_builders']['update_form_langcode'] = '::updateFormLangcode';

    if ($this->showRevisionUi()) {
      $this->addRevisionableFormFields($form);
    }

    $form['footer'] = [
      '#type' => 'container',
      '#weight' => 99,
      '#attributes' => [
        'class' => ['entity-content-form-footer'],
      ],
      '#optional' => TRUE,
    ];

    return $form;
  }

  /**
   * Add revision form fields if the entity enabled the UI.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @return void
   */
  protected function addRevisionableFormFields(array &$form): void {
    /** @var ContentEntityTypeInterface $entity_type */
    $entity_type = $this->entity->getEntityType();

    $new_revision_default = $this->getNewRevisionDefault();

    // Add a log field if the "Create new revision" option is checked, or if the
    // current user has the ability to check that option.
    $form['revision_information'] = [
      '#type' => 'container',
      '#title' => $this->t('Revision information'),
      // Open by default when "Create new revision" is checked.
      '#open' => $new_revision_default,
      '#group' => 'meta',
      '#weight' => 20,
      '#optional' => TRUE,
      '#attributes' => [
        'class' => ['entity-content-form-revision-information'],
      ],
      '#attached' => [
        'library' => ['core/drupal.entity-form'],
      ],
    ];

    $form['revision'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Create new revision'),
      '#default_value' => $new_revision_default,
      '#access' => !$this->entity->isNew(),
      '#group' => 'revision_information',
    ];
    // Get log message field's key from definition.
    $log_message_field = $entity_type->getRevisionMetadataKey('revision_log_message');
    if ($log_message_field && isset($form[$log_message_field])) {
      $form[$log_message_field] += [
        '#group' => 'revision_information',
        '#states' => [
          'visible' => [
            ':input[name="revision"]' => ['checked' => TRUE],
          ],
        ],
      ];
    }
  }

}
