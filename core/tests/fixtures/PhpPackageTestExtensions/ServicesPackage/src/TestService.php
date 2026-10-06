<?php

namespace ServicesPackage;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\serialization\Serializer\JsonSchemaProviderSerializerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A test service.
 */
class TestService {

  /**
   * The constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory is a core service.
   * @param \Drupal\serialization\Serializer\JsonSchemaProviderSerializerInterface|null $serializer
   *   The serialization module may not be installed.
   */
  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    #[Autowire('@serializer')]
    protected ?JsonSchemaProviderSerializerInterface $serializer = NULL,
  ) {}

  /**
   * Get the configured service dependencies.
   *
   * @return array
   *   The services so that we can check if they were set up.
   */
  public function getConfiguredDependencies(): array {
    return [
      'config.factory' => $this->configFactory,
      'serializer' => $this->serializer,
    ];
  }

}
