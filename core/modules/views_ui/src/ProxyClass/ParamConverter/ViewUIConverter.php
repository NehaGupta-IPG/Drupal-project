<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\views_ui\ParamConverter\ViewUIConverter' "core/modules/views_ui/src".
 */

namespace Drupal\views_ui\ProxyClass\ParamConverter {

    use Drupal\Core\ParamConverter\ParamConverterInterface;
    use Drupal\Core\DependencyInjection\DependencySerializationTrait;
    use Symfony\Component\DependencyInjection\ContainerInterface;
    use Symfony\Component\Routing\Route;

    /**
     * Provides a proxy class for \Drupal\views_ui\ParamConverter\ViewUIConverter.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class ViewUIConverter implements ParamConverterInterface
    {

        use DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\views_ui\ParamConverter\ViewUIConverter
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
        public function __construct(protected ContainerInterface $container, protected $drupalProxyOriginalServiceId)
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
        public function applies($definition, $name, Route $route)
        {
            return $this->lazyLoadItself()->applies($definition, $name, $route);
        }

    }

}
