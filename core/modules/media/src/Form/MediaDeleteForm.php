<?php

namespace Drupal\media\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\MediaInterface;
use Drupal\media\Plugin\media\Source\FileSourceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form to delete media items.
 */
class MediaDeleteForm extends ContentEntityDeleteForm {

  /**
   * File usage resolver.
   */
  protected FileUsageInterface $usage;

  /**
   * Current user.
   */
  protected AccountInterface $currentUser;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->currentUser = $container->get(AccountInterface::class);
    $instance->usage = $container->get(FileUsageInterface::class);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $media = $this->getEntity();
    $file = $this->getFile($media);
    $build = parent::buildForm($form, $form_state);
    if (!$file) {
      return $build;
    }
    $file_owner = $file->getOwner();
    if (!$file->access('delete', $this->currentUser)) {
      return $build + [
        'cannot_delete' => [
          '#markup' => $this->t('The file attached to this media is owned by %name so will be retained.', ['%name' => $file_owner->getDisplayName()]),
        ],
      ];
    }
    $usages = $this->usage->countUsage($file);
    if ($usages > 1) {
      return $build + [
        'cannot_delete' => [
          '#markup' => $this->formatPlural($usages - 1, 'The file attached to this media is used in 1 other place and will be retained.', 'The file attached to this media is used in @count other places and will be retained.'),
        ],
      ];
    }

    $config = $this->configFactory()->get('media.settings');

    return $build + [
      'also_delete_file' => [
        '#type' => 'checkbox',
        '#default_value' => $config->get('delete_file_default'),
        '#title' => $this->t('Also delete the associated file?'),
        '#description' => $this->t('After deleting the media item, this will also remove the associated file %file from the file system.', [
          '%file' => $file->getFilename(),
        ]),
        '#access' => !$config->get('disable_delete_control'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): MediaInterface {
    // Overridden to provide an accurate return type.
    return parent::getEntity();
  }

  /**
   * Gets the file for the given media.
   *
   * @param \Drupal\media\MediaInterface $media
   *   Media.
   *
   * @return \Drupal\file\FileInterface|null
   *   File.
   */
  protected function getFile(MediaInterface $media) : ?FileInterface {
    $source = $media->getSource();
    return $source instanceof FileSourceInterface ? $source->getFile($media) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    if (($file = $this->getFile($this->getEntity())) && $form_state->getValue('also_delete_file')) {
      $file->delete();
      $this->messenger()->addMessage($this->t('Deleted the associated file %name.', [
        '%name' => $file->getFilename(),
      ]));
    }
  }

}
