<?php

namespace Drupal\image\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\FileInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\image\ImageStyleStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class for entity-based actions.
 */
abstract class FileImageStyleActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FileSystemInterface $fileSystem,
    protected ImageStyleStorageInterface $imageStyleStorage,
    protected LoggerInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('entity_type.manager')->getStorage('image_style'),
      $container->get('logger.factory')->get('image'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    if (!($object instanceof FileInterface)) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    // Only process image files.
    $mime_type = $object->getMimeType();
    if (strpos($mime_type, 'image/') !== 0) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    $access = $object->access('update', $account, TRUE)
      ->andIf($object->access('delete', $account, TRUE));
    return $return_as_object ? $access : $access->isAllowed();
  }

}
