<?php

namespace Drupal\migrate\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrate messages form.
 *
 * @internal
 */
class MessageForm extends ContentEntityForm {

  /**
   * The message being used by this form.
   *
   * @var \Drupal\contact\MessageInterface
   */
  protected $entity;

  /**
   * The flood control mechanism.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The contact mail handler service.
   *
   * @var \Drupal\contact\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a MessageForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control mechanism.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager service.
   * @param \Drupal\contact\MailHandlerInterface $mail_handler
   *   The contact mail handler service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $widget_plugin_manager
   *   The widget plugin manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, FloodInterface $flood, LanguageManagerInterface $language_manager, MailHandlerInterface $mail_handler, DateFormatterInterface $date_formatter, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, PluginManagerInterface $widget_plugin_manager = NULL) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time, $widget_plugin_manager);
    $this->flood = $flood;
    $this->languageManager = $language_manager;
    $this->mailHandler = $mail_handler;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('flood'),
      $container->get('language_manager'),
      $container->get('contact.mail_handler'),
      $container->get('date.formatter'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('plugin.manager.field.widget')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_messages_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $session_filters = $this->getRequest()->getSession()->get('migration_messages_overview_filter', []);
    $form['filters'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Filter messages'),
      '#weight' => 0,
    ];
    $form['filters']['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $session_filters['message']['value'] ?? '',
    ];
    $form['filters']['severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Severity level'),
      '#default_value' => $session_filters['severity']['value'] ?? [],
      '#options' => [
        MigrationInterface::MESSAGE_ERROR => $this->t('Error'),
        MigrationInterface::MESSAGE_WARNING => $this->t('Warning'),
        MigrationInterface::MESSAGE_NOTICE => $this->t('Notice'),
        MigrationInterface::MESSAGE_INFORMATIONAL => $this->t('Info'),
      ],
      '#multiple' => TRUE,
      '#size' => 4,
    ];
    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    $form['filters']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $filters['message'] = [
      'title' => $this->t('message'),
      'field' => 'msg.message',
      'type' => 'string',
    ];
    $filters['severity'] = [
      'title' => $this->t('Severity'),
      'field' => 'msg.level',
      'type' => 'array',
    ];
    $session_filters = $this->getRequest()->getSession()->get('migration_messages_overview_filter', []);
    foreach ($filters as $name => $filter) {
      if ($form_state->hasValue($name)) {
        $session_filters[$name] = [
          'field' => $filter['field'],
          'value' => $form_state->getValue($name),
          'type' => $filter['type'],
        ];
      }
    }
    $this->getRequest()->getSession()->set('migration_messages_overview_filter', $session_filters);
  }

  /**
   * Resets the filter form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetForm(array $form, FormStateInterface $form_state): void {
    $this->getRequest()->getSession()->remove('migration_messages_overview_filter');
  }

}
