<?php

namespace Drupal\block\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides hooks for block implementing with block content module.
 */
class BlockBlockContentHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new BlockBlockContentHooks.
   */
  public function __construct(
    private AccountInterface $currentUser,
    private ConfigFactoryInterface $configFactory,
    private RequestStack $requestStack,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for the block content form.
   */
  #[Hook('form_block_content_form_alter')]
  public function formBlockContentFormAlter(&$form, FormStateInterface $form_state): void {
    /** @var \Drupal\block_content\BlockContentForm $formObject */
    $formObject = $form_state->getFormObject();

    if (!isset($form['actions']['submit'])) {
      // If submit button was removed.
      return;
    }

    $originalSubmitHandlers = $form['actions']['submit']['#submit'] ?? [];

    // Add a before handler to track if the entity is new. The `configure_block`
    // button will get this also.
    if ($formObject->getEntity()->isNew()) {
      $form['actions']['submit']['#submit'] = [
        ...$originalSubmitHandlers,
        [$this, 'blockContentSave'],
      ];
    }

    // Customize the Block Content entity form by providing a button to redirect
    // the user to a Block add form for the newly created Block Content entity.
    if ($this->currentUser->hasPermission('administer blocks') && $formObject->getEntity()->isNew() && !\Drupal::request()->query->has('theme')) {
      // Add a new button:
      $form['actions']['configure_block'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save and configure'),
        '#weight' => 20,
        '#submit' => [
          ...$originalSubmitHandlers,
          [$this, 'blockContentConfigureBlock'],
        ],
      ];
    }
  }

  /**
   * Form submission handler for the 'Save' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\block\Hook\BlockBlockContentHooks::formBlockContentFormAlter
   */
  public function blockContentSave(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\block_content\BlockContentForm $formObject */
    $formObject = $form_state->getFormObject();
    $blockContent = $formObject->getEntity();

    $theme = $blockContent->getTheme();
    if ($theme === NULL) {
      return;
    }

    $url = Url::fromRoute('block.admin_add', [
      'plugin_id' => 'block_content:' . $blockContent->uuid(),
      'theme' => $theme,
      'region' => $this->requestStack->getCurrentRequest()->query->getString('region'),
    ]);
    if ($url->access()) {
      $form_state->setRedirectUrl($url);
    }
  }

  /**
   * Form submission handler for the 'Save and configure' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @see \Drupal\block\Hook\BlockBlockContentHooks::formBlockContentFormAlter
   */
  public function blockContentConfigureBlock(array $form, FormStateInterface $form_state): void {
    /** @var \Drupal\block_content\BlockContentForm $formObject */
    $formObject = $form_state->getFormObject();
    $blockContent = $formObject->getEntity();

    $theme = $blockContent->getTheme() ?? $this->configFactory->get('system.theme')->get('default');
    $url = Url::fromRoute('block.admin_add', [
      'plugin_id' => sprintf('block_content:%s', $blockContent->uuid()),
      'theme' => $theme,
    ]);
    if ($url->access()) {
      $form_state
        ->setRedirectUrl($url)
        ->setIgnoreDestination();
    }
  }

}
