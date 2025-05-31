<?php
// phpcs:ignoreFile

/**
 * This file was generated via php core/scripts/generate-proxy-class.php 'Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser' "core/lib/Drupal/Core".
 */

namespace Drupal\Core\ProxyClass\File\MimeType {

    /**
     * Provides a proxy class for \Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser.
     *
     * @see \Drupal\Component\ProxyBuilder
     */
    class ExtensionMimeTypeGuesser implements \Symfony\Component\Mime\MimeTypeGuesserInterface
    {

        use \Drupal\Core\DependencyInjection\DependencySerializationTrait;

        /**
         * The real proxied service, after it was lazy loaded.
         *
         * @var \Drupal\Core\File\MimeType\ExtensionMimeTypeGuesser
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
        public function guessMimeType($path): ?string
        {
            return $this->lazyLoadItself()->guessMimeType($path);
        }

        /**
         * {@inheritdoc}
         */
        public function setMapping(?array $mapping = NULL): void
        {
            $this->lazyLoadItself()->setMapping($mapping);
        }

        /**
         * {@inheritdoc}
         */
        public function isGuesserSupported(): bool
        {
            return $this->lazyLoadItself()->isGuesserSupported();
        }

    }

}
