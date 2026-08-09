<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\pgsql\Batch\BatchStorage' "core/modules/pgsql/src".
 */

namespace Drupal\pgsql\ProxyClass\Batch {

    /**
     * Provides a proxy class for \Drupal\pgsql\Batch\BatchStorage.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class BatchStorage implements \Drupal\Core\Batch\BatchStorageInterface
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
         * @var \Drupal\pgsql\Batch\BatchStorage
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
        public function load($id)
        {
            return $this->lazyLoadItself()->load($id);
        }

        /**
         * {@inheritdoc}
         */
        public function delete($id): void
        {
            $this->lazyLoadItself()->delete($id);
        }

        /**
         * {@inheritdoc}
         */
        public function update(array $batch): void
        {
            $this->lazyLoadItself()->update($batch);
        }

        /**
         * {@inheritdoc}
         */
        public function cleanup(): void
        {
            $this->lazyLoadItself()->cleanup();
        }

        /**
         * {@inheritdoc}
         */
        public function getId(): int
        {
            return $this->lazyLoadItself()->getId();
        }

        /**
         * {@inheritdoc}
         */
        public function create(array $batch)
        {
            return $this->lazyLoadItself()->create($batch);
        }

        /**
         * {@inheritdoc}
         */
        public function schemaDefinition(): \Drupal\Core\Database\SchemaDefinition\Schema
        {
            return $this->lazyLoadItself()->schemaDefinition();
        }

    }

}
