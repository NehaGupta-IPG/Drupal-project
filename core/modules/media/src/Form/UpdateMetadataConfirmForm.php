<?php

declare(strict_types=1);

namespace Drupal\media\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\media\MediaInterface;
use Drupal\media\MediaMetadataRefresher;
use Drupal\media\MediaSourceStatus;

/**
 * Confirms refreshing a media item whose source is missing or unavailable.
 *
 * @internal
 *   Form classes are internal.
 */
final class UpdateMetadataConfirmForm extends ConfirmFormBase {

  /**
   * The media item being refreshed.
   */
  protected MediaInterface $media;

  /**
   * The status of the media item's source when the form was built.
   */
  protected MediaSourceStatus $status;

  /**
   * Whether the thumbnail of a media item with a missing source is removed.
   */
  protected bool $thumbnailRemoved = FALSE;

  public function __construct(
    protected readonly MediaMetadataRefresher $refresher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'media_update_metadata_confirm_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?MediaInterface $media = NULL): array {
    $this->media = $media;
    $this->status = $this->refresher->check($media);
    $this->thumbnailRemoved = $this->status === MediaSourceStatus::Missing && $this->refresher->hasIconThumbnail($media);

    $form = parent::buildForm($form, $form_state);
    // With the thumbnail already removed there is nothing left to do.
    if ($this->thumbnailRemoved) {
      unset($form['actions']['submit']);
    }
    // Cancelling must leave a status message, so it is a button, not a link.
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value' => $this->t('Cancel'),
      '#submit' => ['::cancelForm'],
      '#limit_validation_errors' => [],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Refresh media item %label from its source?', ['%label' => $this->media->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): TranslatableMarkup {
    return match ($this->status) {
      MediaSourceStatus::Missing => $this->thumbnailRemoved
        ? $this->t('The source file for media item %label is missing, unable to refresh. It already shows a placeholder icon.', ['%label' => $this->media->label()])
        : $this->t('The source file for media item %label is missing, unable to refresh.', ['%label' => $this->media->label()]),
      MediaSourceStatus::Unavailable => $this->t('The remote source for media item %label is unavailable.', ['%label' => $this->media->label()]),
      MediaSourceStatus::Available => $this->t('The source for media item %label is available again.', ['%label' => $this->media->label()]),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): TranslatableMarkup {
    return match ($this->status) {
      MediaSourceStatus::Missing => $this->t('Remove thumbnail'),
      MediaSourceStatus::Unavailable => $this->t('Retry'),
      MediaSourceStatus::Available => $this->t('Refresh'),
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('entity.media.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $label = $this->media->label();
    // The form is built again on submission, so the status reflects a fresh
    // check. Retry only refreshes once the source can be reached; until then
    // the form is shown again and the media item is left unchanged.
    if ($this->status === MediaSourceStatus::Unavailable) {
      $form_state->setRebuild();
      return;
    }

    $this->refresher->refresh($this->media);
    if ($this->status === MediaSourceStatus::Missing) {
      $this->messenger()->addStatus($this->t('Thumbnail removed for media item %label.', ['%label' => $label]));
    }
    else {
      $this->messenger()->addStatus($this->t('Updated metadata on media item %label.', ['%label' => $label]));
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Form submission handler for the 'Cancel' button.
   */
  public function cancelForm(array &$form, FormStateInterface $form_state): void {
    $this->messenger()->addStatus($this->t('Canceled. Media item %label remains unchanged.', ['%label' => $this->media->label()]));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
