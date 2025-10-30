<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\Core\Config\LazyConfigFactory' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\ProxyClass\Config {

    /**
     * Provides a proxy class for \Drupal\Core\Config\LazyConfigFactory.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class LazyConfigFactory implements \Drupal\Core\Config\ConfigFactoryInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The id of the original proxied service.
         *
         * @var string
         */
        protected $drupalProxyOriginalServiceId;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\Config\LazyConfigFactory
         */
        protected $service;

        /**
         * The service container.
         *
         * @var \Symfony\Component\DependencyInjection\ContainerInterface
         */
        protected $container;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupal_proxy_original_service_id
         *   The service ID of the original service.
         */
        public function __construct(\Symfony\Component\DependencyInjection\ContainerInterface $container, $drupal_proxy_original_service_id)
        {
            $this->container = $container;
            $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
        }

        /**
         * Lazy loads the real service from the container.
         *
         * @return object
         *   Returns the constructed real service.
         */
        protected function lazyLoadItself()
        {
            if (!isset($this->service)) {
                $this->service = $this->container->get($this->drupalProxyOriginalServiceId);
            }

            return $this->service;
        }

        /**
         * {@inheritdoc}
         */
        public function get($name): \Drupal\Core\Config\ImmutableConfig
        {
            return $this->lazyLoadItself()->get($name);
        }

        /**
         * {@inheritdoc}
         */
        public function getEditable($name): \Drupal\Core\Config\Config
        {
            return $this->lazyLoadItself()->getEditable($name);
        }

        /**
         * {@inheritdoc}
         */
        public function loadMultiple(array $names): array
        {
            return $this->lazyLoadItself()->loadMultiple($names);
        }

        /**
         * {@inheritdoc}
         */
        public function reset($name = NULL): static
        {
            $this->lazyLoadItself()->reset($name);
            return $this;
        }

        /**
         * {@inheritdoc}
         */
        public function rename($old_name, $new_name): static
        {
            $this->lazyLoadItself()->rename($old_name, $new_name);
            return $this;
        }

        /**
         * {@inheritdoc}
         */
        public function getCacheKeys(): array
        {
            return $this->lazyLoadItself()->getCacheKeys();
        }

        /**
         * {@inheritdoc}
         */
        public function clearStaticCache(): static
        {
            $this->lazyLoadItself()->clearStaticCache();
            return $this;
        }

        /**
         * {@inheritdoc}
         */
        public function listAll($prefix = ''): array
        {
            return $this->lazyLoadItself()->listAll($prefix);
        }

        /**
         * {@inheritdoc}
         */
        public function addOverride(\Drupal\Core\Config\ConfigFactoryOverrideInterface $config_factory_override): void
        {
            $this->lazyLoadItself()->addOverride($config_factory_override);
        }

    }

}
