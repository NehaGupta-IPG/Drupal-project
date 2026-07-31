<?php

namespace Drupal\locale\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\locale\LocaleConfigBatch;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for changing the site default language in locale.
 */
class DefaultLanguageConfirmForm extends ConfirmFormBase {

  /**
   * The configuration factory.
   *
   * Kept separate from FormBase::$configFactory to avoid typed property
   * redeclaration conflicts.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $localeConfigFactory;

  /**
   * The key-value expirable storage for pending default language changes.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $pendingStorage;

  /**
   * Constructs a confirmation form.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    protected readonly LanguageManagerInterface $languageManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LocaleConfigBatch $localeConfigBatch,
    protected readonly AccountProxyInterface $currentUser,
    KeyValueStoreExpirableInterface $pending_storage,
  ) {
    $this->localeConfigFactory = $config_factory;
    $this->pendingStorage = $pending_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get(LocaleConfigBatch::class),
      $container->get('current_user'),
      $container->get('keyvalue.expirable')->get('locale.default_language_change'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'locale_default_language_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to change the default language for this site?');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Configuration language metadata and translated configuration values will be updated to match the new default language. No data will be lost.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Proceed');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.configurable_language.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->getPendingChange()) {
      return $this->redirect('entity.configurable_language.collection');
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pending = $this->getPendingChange();
    $this->pendingStorage->delete((string) $this->currentUser->id());
    if (!$pending || empty($pending['langcode'])) {
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $new_default_langcode = $pending['langcode'];
    $language_storage = $this->entityTypeManager->getStorage('configurable_language');
    $language = $language_storage->load($new_default_langcode);
    if (!$language) {
      $this->messenger()->addError($this->t('Selected default language no longer exists.'));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $this->localeConfigFactory
      ->getEditable('system.site')
      ->set('default_langcode', $new_default_langcode)
      ->save();

    $this->languageManager->reset();
    if ($this->languageManager instanceof ConfigurableLanguageManagerInterface) {
      $this->languageManager->updateLockedLanguageWeights();
    }

    if (!empty($pending['weights']) && is_array($pending['weights'])) {
      $languages = $language_storage->loadMultiple(array_keys($pending['weights']));
      foreach ($pending['weights'] as $id => $weight) {
        if (isset($languages[$id]) && $languages[$id]->getWeight() !== (int) $weight) {
          $languages[$id]->setWeight((int) $weight);
          $languages[$id]->save();
        }
      }
    }

    if ($batch = $this->localeConfigBatch->buildBatch([
      'previous_default_langcode' => $pending['old_langcode'] ?? NULL,
    ], [], [], TRUE)) {
      batch_set($batch);
    }

    $this->messenger()->addStatus($this->t('Configuration saved.'));
    $form_state->setRedirectUrl($language->toUrl('collection', ['language' => $language]));
  }

  /**
   * Gets pending default language change data.
   */
  protected function getPendingChange(): ?array {
    return $this->pendingStorage->get((string) $this->currentUser->id());
  }

}
