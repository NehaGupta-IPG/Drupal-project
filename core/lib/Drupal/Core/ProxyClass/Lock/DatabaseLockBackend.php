<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\Core\Lock\DatabaseLockBackend' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\ProxyClass\Lock {

    /**
     * Provides a proxy class for \Drupal\Core\Lock\DatabaseLockBackend.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class DatabaseLockBackend implements \Drupal\Core\Lock\LockBackendInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\Lock\DatabaseLockBackend
         */
        protected $service;

        /**
         * Constructs a ProxyClass Drupal proxy object.
         *
         * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
         *   The container.
         * @param string $drupalProxyOriginalServiceId
         *   The service ID of the original service.
         */
        public function __construct(protected \Symfony\Component\DependencyInjection\ContainerInterface $container, protected $drupalProxyOriginalServiceId)
        {
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
        public function acquire($name, $timeout = 30.0)
        {
            return $this->lazyLoadItself()->acquire($name, $timeout);
        }

        /**
         * {@inheritdoc}
         */
        public function lockMayBeAvailable($name)
        {
            return $this->lazyLoadItself()->lockMayBeAvailable($name);
        }

        /**
         * {@inheritdoc}
         */
        public function release($name)
        {
            return $this->lazyLoadItself()->release($name);
        }

        /**
         * {@inheritdoc}
         */
        public function releaseAll($lock_id = NULL)
        {
            return $this->lazyLoadItself()->releaseAll($lock_id);
        }

        /**
         * {@inheritdoc}
         */
        public function schemaDefinition()
        {
            return $this->lazyLoadItself()->schemaDefinition();
        }

        /**
         * {@inheritdoc}
         */
        public function wait($name, $delay = 30)
        {
            return $this->lazyLoadItself()->wait($name, $delay);
        }

        /**
         * {@inheritdoc}
         */
        public function getLockId()
        {
            return $this->lazyLoadItself()->getLockId();
        }

    }

}
