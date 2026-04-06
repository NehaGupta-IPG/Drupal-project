<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\AttributeBridgeDecorator;
use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mailer\Attribute\Email as EmailAttribute;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\AttributeClassDiscovery;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscoveryDecorator;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Registry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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

  protected RendererInterface $renderer;

  protected MailerInterface $mailer;

  protected Registry $themeRegistry;

  /**
   * {@inheritdoc}
   */
  protected $defaults = [
    // The email id. Set by the plugin system based on the top-level YAML key.
    'id' => '',
    // The email label.
    'label' => '',
    // The email description.
    'description' => '',
    // The email sending module.
    'module' => '',
    // The email key used to identify different emails sent from a module.
    'key' => '',
    // Default plugin class.
    'class' => EmailPluginDefault::class,
    // Template for the HTML version of the email body.
    'html_body_template' => NULL,
    // Template for the text version of the email body.
    'text_body_template' => NULL,
    // Default subject for the email.
    'default_subject' => NULL,
    // Default recipients for the email.
    'default_recipients' => NULL,
  ];

  /**
   * Constructs EmailPluginManager object.
   */
  public function __construct(
    #[Autowire(service: 'container.namespaces')]
    \Traversable $namespaces,
    ModuleHandlerInterface $module_handler,
    RendererInterface $renderer,
    MailerInterface $mailer,
    #[Autowire(service: 'cache.discovery')]
    CacheBackendInterface $cache_backend) {
    parent::__construct('Plugin/Email', $namespaces, $module_handler, EmailPluginInterface::class, EmailAttribute::class);
    $this->factory = new ContainerFactory($this, EmailPluginInterface::class);
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->mailer = $mailer;
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
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery->addTranslatableProperty('description', 'description_context');
      $this->discovery = new AttributeBridgeDecorator($this->discovery, $this->pluginDefinitionAttributeName);
      $this->discovery = new ContainerDerivativeDiscoveryDecorator($this->discovery);
    }
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id): void {
    parent::processDefinition($definition, $plugin_id);

    if (empty($definition['module'])) {
      $definition['module'] = $definition['provider'];
    }
  }

  protected function build(EmailPluginInterface $email): Email {
    $this->moduleHandler->alter('email_pre_build', $email);

    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email) {
      $metadata = new BubbleableMetadata();

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
        'cacheable_metadata' => $metadata,
      ];
      $this->moduleHandler->alter('email_pre_render', $email_data, $context);

      $symfony_email = new $email_data['symfony_email_class']();

      foreach ($email_data['headers'] as $name => $value) {
        $symfony_email->getHeaders()->addHeader($name, $value);
      }

      if (isset($email_data['body']['html'])) {
        $symfony_email->html((string) $this->renderer->render($email_data['body']['html']));
        $metadata->merge(BubbleableMetadata::createFromRenderArray($email_data['body']['html']));
      }
      if (isset($email_data['body']['text'])) {
        $symfony_email->text((string) $this->renderer->render($email_data['body']['text']));
        $metadata->merge(BubbleableMetadata::createFromRenderArray($email_data['body']['text']));
      }

      return $symfony_email;
    });
  }

  /**
   * @param EmailPluginInterface $email
   * @return bool
   * @throws TransportExceptionInterface
   */
  public function send(EmailPluginInterface $email): bool {
    $symfony_email = $this->build($email);

    $this->moduleHandler->alter('email_pre_send', $email, $symfony_email);

    if (!$email->shouldSend()) {
      // Don't send, but return TRUE to indicate no error.
      return TRUE;
    }

    try {
      $this->mailer->send($symfony_email);
    }
    catch (\Exception $e) {
      // Log the exception or handle it as needed.
      \Drupal::logger('email')->error('Failed to send email: @message', ['@message' => $e->getMessage()]);
      return FALSE;
    }
    return TRUE;
  }

}
