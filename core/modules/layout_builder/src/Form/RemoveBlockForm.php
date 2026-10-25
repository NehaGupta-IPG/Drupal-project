<?php

namespace Drupal\layout_builder\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\layout_builder\SectionStorageInterface;

/**
 * Provides a form to confirm the removal of a block.
 *
 * @internal
 *   Form classes are internal.
 */
class RemoveBlockForm extends LayoutRebuildConfirmFormBase {

  /**
   * The current region.
   *
   * @var string
   */
  protected $region;

  /**
   * The UUID of the block being removed.
   *
   * @var string
   */
  protected $uuid;

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $label = $this->sectionStorage
      ->getSection($this->delta)
      ->getComponent($this->uuid)
      ->getPlugin()
      ->label();

    return $this->t('Are you sure you want to remove the %label block?', ['%label' => $label]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Remove');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'layout_builder_remove_block';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?SectionStorageInterface $section_storage = NULL, $delta = NULL, $region = NULL, $uuid = NULL) {
    $this->region = $region;
    $this->uuid = $uuid;
    return parent::buildForm($form, $form_state, $section_storage, $delta);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleSectionStorage(SectionStorageInterface $section_storage, FormStateInterface $form_state) {
    $section_storage->getSection($this->delta)->removeComponent($this->uuid);
  }

  /**
   * {@inheritdoc}
   */
  protected function rebuildAndClose(SectionStorageInterface $section_storage): AjaxResponse {
    $response = $this->removeBlock($this->uuid);
    $response->addCommand(new CloseDialogCommand('#drupal-off-canvas'));
    return $response;
  }

  /**
   * Removes the block.
   *
   * @param string $uuid
   *   The block UUID.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   */
  protected function removeBlock($uuid): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand("[data-layout-block-uuid=$uuid]"));
    return $response;
  }

}
