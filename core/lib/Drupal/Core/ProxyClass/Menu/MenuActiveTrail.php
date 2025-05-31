<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\Core\Menu\MenuActiveTrail' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\ProxyClass\Menu {

    /**
     * Provides a proxy class for \Drupal\Core\Menu\MenuActiveTrail.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class MenuActiveTrail implements \Drupal\Core\Cache\CacheCollectorInterface, \Drupal\Core\DestructableInterface, \Drupal\Core\Menu\MenuActiveTrailInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\Menu\MenuActiveTrail
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
        public function getActiveTrailIds($menu_name)
        {
            return $this->lazyLoadItself()->getActiveTrailIds($menu_name);
        }

        /**
         * {@inheritdoc}
         */
        public function getActiveLink($menu_name = NULL)
        {
            return $this->lazyLoadItself()->getActiveLink($menu_name);
        }

        /**
         * {@inheritdoc}
         */
        public function has($key)
        {
            return $this->lazyLoadItself()->has($key);
        }

        /**
         * {@inheritdoc}
         */
        public function get($key)
        {
            return $this->lazyLoadItself()->get($key);
        }

        /**
         * {@inheritdoc}
         */
        public function set($key, $value)
        {
            return $this->lazyLoadItself()->set($key, $value);
        }

        /**
         * {@inheritdoc}
         */
        public function delete($key)
        {
            return $this->lazyLoadItself()->delete($key);
        }

        /**
         * {@inheritdoc}
         */
        public function reset()
        {
            return $this->lazyLoadItself()->reset();
        }

        /**
         * {@inheritdoc}
         */
        public function clear()
        {
            return $this->lazyLoadItself()->clear();
        }

        /**
         * {@inheritdoc}
         */
        public function destruct()
        {
            return $this->lazyLoadItself()->destruct();
        }

    }

}
