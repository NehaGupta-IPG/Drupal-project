<?php

declare(strict_types=1);

namespace Drupal\menu_tree_ui\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\menu_tree_ui\Service\MenuTreeWorkingCopyLock;
use Drupal\system\MenuInterface;
use Drupal\treegrid_reorder\TreegridWorkingCopy;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirms breaking an abandoned lock on a menu's tree working copy.
 *
 * Mirrors views_ui's BreakLockForm for the same purpose: a working copy left
 * locked by a user who never returned to Save or Cancel (a closed tab, a
 * crashed browser) otherwise has no way to be released.
 *
 * @internal
 */
final class MenuTreeBreakLockForm extends ConfirmFormBase {

  /**
   * The menu whose working-copy lock is being broken.
   */
  protected ?MenuInterface $menu = NULL;

  public function __construct(
    protected readonly TreegridWorkingCopy $workingCopy,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly DateFormatterInterface $dateFormatter,
    protected readonly MenuTreeWorkingCopyLock $workingCopyLock,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('menu_tree_ui.working_copy'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('menu_tree_ui.working_copy_lock'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'menu_tree_ui_break_lock_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?MenuInterface $menu = NULL): array {
    $this->menu = $menu;
    if ($menu === NULL || $this->workingCopy->getLockMetadata($menu->id()) === NULL) {
      $form['message']['#markup'] = $this->t('There is no lock on %menu to break.', [
        '%menu' => $menu?->label() ?? '',
      ]);
      return $form;
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Do you want to break the lock on %menu?', ['%menu' => $this->menu->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    $lock = $this->workingCopy->getLockMetadata($this->menu->id());
    $owner = $this->entityTypeManager->getStorage('user')->load($lock->getOwnerId());
    $username = $owner ? $owner->getDisplayName() : (string) $lock->getOwnerId();
    return $this->t('By breaking this lock, unsaved changes made by @user (last saved @time ago) will be lost.', [
      '@user' => $username,
      '@time' => $this->dateFormatter->formatTimeDiffSince($lock->getUpdated()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('menu_tree_ui.edit', ['menu' => $this->menu->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return $this->t('Break lock');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirectUrl($this->getCancelUrl());

    if (!$this->workingCopyLock->acquire($this->menu->id())) {
      $this->messenger()->addError($this->t('Another change to this menu is being processed. Try again.'));
      return;
    }
    try {
      $this->workingCopy->breakLock($this->menu->id());
    }
    finally {
      $this->workingCopyLock->release($this->menu->id());
    }
    $this->messenger()->addStatus($this->t('The lock has been broken. You may now edit %menu.', [
      '%menu' => $this->menu->label(),
    ]));
  }

}
