<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\AttributeBridgeDecorator;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mailer\Attribute\Email as EmailAttribute;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\AttributeClassDiscovery;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscoveryDecorator;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Utility\Error;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Defines a plugin manager to deal with emails.
 *
 * Modules can define emails in a MODULE_NAME.emails.yml file contained
 * in the module's base directory. Each email has the following structure:
 *
 * @code
 *   MACHINE_NAME:
 *     label: STRING
 *     description: STRING
 * @endcode
 *
 * @see \Drupal\Core\Mailer\EmailPluginDefault
 * @see \Drupal\Core\Mailer\EmailPluginInterface
 */
class EmailManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    'label' => '',
    'description' => '',
    'class' => EmailPluginDefault::class,
  ];

  /**
   * Constructs EmailPluginManager object.
   */
  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    ModuleHandlerInterface $module_handler,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend,
    protected readonly RendererInterface $renderer,
    /**
     * @var \Closure(): \Symfony\Component\Mailer\MailerInterface
     */
    #[AutowireServiceClosure(MailerInterface::class)]
    protected readonly \Closure $mailer,
    /**
     * @var \Closure(): \Psr\Log\LoggerInterface
     */
    #[AutowireServiceClosure('logger.channel.mail')]
    protected readonly \Closure $logger,
  ) {
    parent::__construct('Plugin/Email', $namespaces, $module_handler, EmailPluginInterface::class, EmailAttribute::class);
    $this->factory = new ContainerFactory($this, EmailPluginInterface::class);
    $this->alterInfo('email_info');
    $this->setCacheBackend($cache_backend, 'email_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface {
    if (!isset($this->discovery)) {
      $this->discovery = new AttributeClassDiscovery($this->subdir, $this->namespaces, $this->pluginDefinitionAttributeName);
      $this->discovery = new YamlDiscoveryDecorator($this->discovery, 'emails', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label')
        ->addTranslatableProperty('description')
        ->addTranslatableProperty('default_subject');
      $this->discovery = new AttributeBridgeDecorator($this->discovery, $this->pluginDefinitionAttributeName);
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * Builds and renders the email body and returns the resulting symfony email.
   *
   * @param \Drupal\Core\Mailer\EmailPluginInterface $email
   *   The email plugin instance defining the email to be built.
   */
  protected function build(EmailPluginInterface $email): Email {
    $this->moduleHandler->alter('email_pre_build', $email);

    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email) {
      // Call preBuild to set defaults from the plugin class.
      $email->preBuild();

      // Build and render the HTML body
      $html = $email->htmlBody();
      if ($html instanceof RenderableInterface) {
        $html = $html->toRenderable();
      }

      // Build and render the text body
      $text = $email->textBody();
      if ($text instanceof RenderableInterface) {
        $text = $text->toRenderable();
      }

      $email_data = [
        'body' => [
          'html' => $html,
          'text' => $text,
        ],
        'headers' => $email->getHeaders(),
        'symfony_email_class' => Email::class,
      ];
      $context = [
        'email' => $email,
      ];
      $this->moduleHandler->alter('email_pre_render', $email_data, $context);
      $symfony_email = new $email_data['symfony_email_class']();

      foreach ($email_data['headers'] as $name => $value) {
        $symfony_email->getHeaders()->addHeader($name, $value);
      }

      $assets = new AttachedAssets();
      if (isset($email_data['body']['html'])) {
        $symfony_email->html((string) $this->renderer->render($email_data['body']['html']));
        $assets = AttachedAssets::createFromRenderArray($email_data['body']['html']);
      }
      if (isset($email_data['body']['text'])) {
        if (is_array($email_data['body']['text'])) {
          $symfony_email->text((string) $this->renderer->render($email_data['body']['text']));
        }
        else {
          $symfony_email->text((string) $email_data['body']['text']);
        }
      }

      $context = [
        'email' => $email,
        'body' => $email_data['body'],
        'assets' => $assets,
      ];
      $this->moduleHandler->alter('email_post_render', $symfony_email, $context);

      return $symfony_email;
    });
  }

  /**
   * Composes and optionally sends an email message.
   *
   * @param \Drupal\Core\Mailer\EmailPluginInterface $email
   *   The email plugin instance defining the email to be sent.
   *
   * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
   */
  public function send(EmailPluginInterface $email): bool {
    $symfony_email = $this->build($email);

    $this->moduleHandler->alter('email_pre_send', $email, $symfony_email);

    if (!$email->shouldSend()) {
      // Don't send, but return TRUE to indicate no error.
      return TRUE;
    }

    try {
      ($this->mailer)()->send($symfony_email);
    }
    catch (\Exception $e) {
      Error::logException(($this->logger)(), $e, 'Failed to send email: @message', [
        '@message' => $e->getMessage(),
      ]);
      return FALSE;
    }
    return TRUE;
  }

}
