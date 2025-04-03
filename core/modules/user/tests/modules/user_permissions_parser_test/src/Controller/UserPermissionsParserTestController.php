<?php

declare(strict_types=1);

namespace Drupal\user_permissions_parser_test\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\PermissionHandlerInterface;
use Drupal\user\Service\PermissionsRepositoryFactoryInterface;
use Drupal\user\Service\PermissionsRepositoryReturnStyle;

/**
 * Returns responses for Permissions repository routes.
 */
final class UserPermissionsParserTestController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly PermissionHandlerInterface $legacyPermissionHandler,
    private readonly PermissionHandlerInterface $newPermissionHandler,
    private readonly PermissionsRepositoryFactoryInterface $permissionsRepositoryFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('user.legacy_permissions'),
      $container->get('user.permissions'),
      $container->get('user_permissions_parser.repository_factory'),
    );
  }

  /**
   * Route callback.
   */
  public function testPermissionsParser(): array {
    $legacyPermissions = $this->legacyPermissionHandler->getPermissions();
    $newPermissions = $this->newPermissionHandler->getPermissions();

    ksort($legacyPermissions);
    ksort($newPermissions);

    //echo '<pre>' . json_encode($legacyPermissions, JSON_PRETTY_PRINT) . 'DDDDDDDDDDDDDDDDDDD' . json_encode($newPermissions, JSON_PRETTY_PRINT);

    $yamlDiscovery = new YamlDiscovery('permissions', $this->moduleHandler()->getModuleDirectories());

    $list = $yamlDiscovery->findAll();

    $permissionsRepository = $this->permissionsRepositoryFactory->createPermissionsRepository($list);

    $data = $permissionsRepository->getProviderData('user_permissions_parser_test');
    //$data = $permissionsRepository->getAllPermissions();

    echo '<pre>' . json_encode($data, JSON_PRETTY_PRINT);
    exit;

    $build['content'] = [
      '#type' => 'item',
      '#markup' => 'You could also use this to show something if you want.',
    ];

    return $build;
  }

  /**
   * Returns permissions defined in code.
   *
   * @see user_permissions_parser_test.permissions.yml
   */
  public function getPerms(): array {
    return [
      'do something' => [
        'title' => 'Do something',
        'section' => 'admin',
      ],
    ];
  }

  /**
   * Returns permission sections defined in code.
   *
   * @see user_permissions_parser_test.permissions.yml
   */
  public function getSections(): array {
    return [
      'unused_section' => [
        'title' => 'unused_section',
      ],
    ];
  }

}
