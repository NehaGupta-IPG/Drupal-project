<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\Core\Render\BareHtmlPageRenderer' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\ProxyClass\Render {

    /**
     * Provides a proxy class for \Drupal\Core\Render\BareHtmlPageRenderer.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class BareHtmlPageRenderer implements \Drupal\Core\Render\BareHtmlPageRendererInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\Render\BareHtmlPageRenderer
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
        public function renderBarePage(array $content, $title, $page_theme_property, array $page_additions = array (
        ))
        {
            return $this->lazyLoadItself()->renderBarePage($content, $title, $page_theme_property, $page_additions);
        }

        /**
         * {@inheritdoc}
         */
        public function systemPageAttachments(array &$page): void
        {
            $this->lazyLoadItself()->systemPageAttachments($page);
        }

    }

}
