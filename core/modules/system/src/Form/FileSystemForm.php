<?php

namespace Drupal\system\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\RedundantEditableConfigNamesTrait;
use Drupal\Core\StreamWrapper\AssetsStream;
use Drupal\Core\StreamWrapper\PrivateStream;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\StreamWrapper\StreamWrapperInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure file system settings for this site.
 *
 * @internal
 */
class FileSystemForm extends ConfigFormBase {
  use RedundantEditableConfigNamesTrait;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface
   */
  protected $streamWrapperManager;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a FileSystemForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $stream_wrapper_manager
   *   The stream wrapper manager.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   * @param string|null $root
   *   Application root folder.
   */
  public function __construct(ConfigFactoryInterface $config_factory, TypedConfigManagerInterface $typedConfigManager, DateFormatterInterface $date_formatter, StreamWrapperManagerInterface $stream_wrapper_manager, FileSystemInterface $file_system, protected ?string $root = NULL) {
    parent::__construct($config_factory, $typedConfigManager);
    $this->dateFormatter = $date_formatter;
    $this->streamWrapperManager = $stream_wrapper_manager;
    $this->fileSystem = $file_system;
    if ($this->root === NULL) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $root argument is deprecated in drupal:10.1.0 and it will be required in drupal:11.0.0. See https://www.drupal.org/node/3341696', E_USER_DEPRECATED);
      $this->root = \Drupal::root();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('date.formatter'),
      $container->get('stream_wrapper_manager'),
      $container->get('file_system'),
      $container->getParameter('app.root')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'system_file_system_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['drupal_root'] = [
      '#type' => 'item',
      '#title' => $this->t('Drupal root directory'),
      '#markup' => $this->root,
      '#description' => $this->t('The top level directory of the Drupal installation. Relative paths shown below are relative to this location.'),
    ];

    $path = PublicStream::basePath();
    $form['file_public_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Public file system path'),
      '#markup' => $path,
      '#description' => $this->getAbsolutePathDescription($path) .
      $this->t('A local file system path where public files will be stored. This directory must exist and be writable by Drupal. This directory must be relative to the Drupal installation directory and be accessible over the web. This must be changed in settings.php'),
    ];

    $form['file_public_base_url'] = [
      '#type' => 'item',
      '#title' => $this->t('Public file base URL'),
      '#markup' => PublicStream::baseUrl(),
      '#description' => $this->t('The base URL that will be used for public file URLs. This can be changed in settings.php'),
    ];

    $path = AssetsStream::basePath();
    $form['file_assets_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Optimized assets file system path'),
      '#markup' => $this->getAbsolutePathDescription($path),
      '#description' => $this->t('A local file system path where optimized assets files will be stored. This directory must exist and be writable by Drupal. This directory must be relative to the Drupal installation directory and be accessible over the web. This must be changed in settings.php'),
    ];

    $path = PrivateStream::basePath();
    $form['file_private_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Private file system path'),
      '#markup' => (PrivateStream::basePath() ?: $this->t('Not set')),
      '#description' => $this->getAbsolutePathDescription($path) .
      $this->t('An existing local file system path for storing private files. It should be writable by Drupal and not accessible over the web. This must be changed in settings.php'),
    ];

    $path = $this->fileSystem->getTempDirectory();
    $form['file_temporary_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Temporary directory'),
      '#markup' => $path,
      '#description' => $this->getAbsolutePathDescription($path) .
      $this->t('A local file system path where temporary files will be stored. This directory should not be accessible over the web. This must be changed in settings.php.'),
    ];
    // Any visible, writable wrapper can potentially be used for the files
    // directory, including a remote file system that integrates with a CDN.
    $options = $this->streamWrapperManager->getDescriptions(StreamWrapperInterface::WRITE_VISIBLE);

    if (!empty($options)) {
      $form['file_default_scheme'] = [
        '#type' => 'radios',
        '#title' => $this->t('Default download method'),
        '#config_target' => 'system.file:default_scheme',
        '#options' => $options,
        '#description' => $this->t('This setting is used as the preferred download method. The use of public files is more efficient, but does not provide any access control.'),
      ];
    }

    $intervals = [0, 21600, 43200, 86400, 604800, 2419200, 7776000];
    $period = array_combine($intervals, array_map([$this->dateFormatter, 'formatInterval'], $intervals));
    $period[0] = $this->t('Never');
    $form['temporary_maximum_age'] = [
      '#type' => 'select',
      '#title' => $this->t('Delete temporary files after'),
      '#config_target' => 'system.file:temporary_maximum_age',
      '#options' => $period,
      '#description' => $this->t('Temporary files are not referenced, but are in the file system and therefore may show up in administrative lists. <strong>Warning:</strong> If enabled, temporary files will be permanently deleted and may not be recoverable.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Helper method to prepare description for the given path.
   *
   * @param string|null $path
   *   A given path.
   *
   * @return string
   *   Description will contain a sentence which describes the relative path
   *   as absolute path, and will contain empty string otherwise.
   */
  private function getAbsolutePathDescription(?string $path): string {
    if (!$path || str_starts_with($path, '/') || str_contains($path, '://')) {
      return '';
    }
    return $this->t('It is resolving to the absolute value - "@absolute"', [
      '@absolute' => $this->root . '/' . $path,
    ]) . '<br>';
  }

}
