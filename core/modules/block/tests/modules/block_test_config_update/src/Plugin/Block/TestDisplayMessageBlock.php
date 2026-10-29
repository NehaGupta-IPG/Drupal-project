<?php

declare(strict_types=1);

namespace Drupal\block_test_config_update\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides a block that displays a message.
 */
#[Block(
  id: 'test_block_display_message',
  admin_label: new TranslatableMarkup('Display message'),
  category: new TranslatableMarkup('Test config update'),
)]
class TestDisplayMessageBlock extends BlockBase implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'display_message' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['display_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Display message'),
      '#default_value' => $this->configuration['display_message'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['display_message'] = $form_state->getValue('display_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return [
      'message' => [
        '#markup' => $this->configuration['display_message'],
      ],
      'form' => \Drupal::formBuilder()->getForm($this),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'test_block_display_message';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $schema = $this->configuration['schema'] ?? 0;
    if ($schema === 0) {
      $form['actions']['update_1'] = [
        '#type' => 'submit',
        '#name' => 'update_1',
        '#value' => 'Update block config schema',
      ];
    }
    elseif ($schema === 11400) {
      $form['actions']['update_2'] = [
        '#type' => 'submit',
        '#name' => 'update_2',
        '#value' => 'Update block config schema again',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pluginClass = match ($form_state->getTriggeringElement()['#name']) {
      'update_1' => TestDisplayMessageConfigUpdateBlock::class,
      'update_2' => TestDisplayMessageConfigUpdate2Block::class,
      default => NULL,
    };
    if ($pluginClass) {
      \Drupal::keyValue('block_test')->set('config_update_test_class', $pluginClass);
      drupal_flush_all_caches();
    }
  }

}
