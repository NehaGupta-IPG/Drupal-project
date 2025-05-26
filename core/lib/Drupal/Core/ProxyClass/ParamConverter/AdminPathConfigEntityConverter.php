<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\Core\ParamConverter\AdminPathConfigEntityConverter' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\ProxyClass\ParamConverter {

    /**
     * Provides a proxy class for \Drupal\Core\ParamConverter\AdminPathConfigEntityConverter.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class AdminPathConfigEntityConverter implements \Drupal\Core\ParamConverter\ParamConverterInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\ParamConverter\AdminPathConfigEntityConverter
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
        public function convert($value, $definition, $name, array $defaults)
        {
            return $this->lazyLoadItself()->convert($value, $definition, $name, $defaults);
        }

        /**
         * {@inheritdoc}
         */
        public function applies($definition, $name, \Symfony\Component\Routing\Route $route)
        {
            return $this->lazyLoadItself()->applies($definition, $name, $route);
        }

    }

}
