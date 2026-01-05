<?php

declare(strict_types=1);

namespace Drupal\Core\Mailer;

use Drupal\Component\Plugin\Discovery\DiscoveryInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Plugin\Discovery\ContainerDerivativeDiscoveryDecorator;
use Drupal\Core\Plugin\Discovery\YamlDiscovery;
use Drupal\Core\Plugin\Factory\ContainerFactory;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderableInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Theme\Registry;
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
  ];

  /**
   * Constructs EmailPluginManager object.
   */
  public function __construct(ModuleHandlerInterface $module_handler, RendererInterface $renderer, MailerInterface $mailer, Registry $themeRegistry, CacheBackendInterface $cache_backend) {
    $this->factory = new ContainerFactory($this, EmailPluginInterface::class);
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
    $this->mailer = $mailer;
    $this->themeRegistry = $themeRegistry;
    $this->alterInfo('email_info');
    $this->setCacheBackend($cache_backend, 'email_plugins');
  }

  /**
   * {@inheritdoc}
   */
  protected function getDiscovery(): DiscoveryInterface {
    if (!isset($this->discovery)) {
      $this->discovery = new YamlDiscovery('emails', $this->moduleHandler->getModuleDirectories());
      $this->discovery->addTranslatableProperty('label', 'label_context');
      $this->discovery->addTranslatableProperty('description', 'description_context');
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
    $this->determineEmailBodyTemplates($definition, $plugin_id);
  }

  /**
   * Set the email body templates if not already defined in the plugin definition.
   * @param array $definition
   * @param string $plugin_id
   * @return void
   */
  protected function determineEmailBodyTemplates(array &$definition, string $plugin_id): void {
    if (empty($definition['html_body_template'])) {
      $html_template_id = 'email_body_html_' . str_replace('.', '_', $plugin_id);
      if ($this->themeRegistry->getRuntime()->get($html_template_id)) {
        $definition['html_body_template'] = $html_template_id;
      }
    }
    if (empty($definition['text_body_template'])) {
      $text_template_id = 'email_body_text_' . str_replace('.', '_', $plugin_id);
      if ($this->themeRegistry->getRuntime()->get($text_template_id)) {
        $definition['text_body_template'] = $text_template_id;
      }
    }
  }

  protected function build(EmailPluginInterface $email): Email {
    $this->moduleHandler->alter('email_pre_build', $email);

    return $this->renderer->executeInRenderContext(new RenderContext(), function () use ($email) {
      $metadata = new BubbleableMetadata();
      // @todo Allow contrib and custom code to provide a custom Email class.
      $symfony_email = new Email();

      foreach ($email->getHeaders() as $name => $value) {
        $symfony_email->getHeaders()->addHeader($name, $value);
      }

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

      $body = [
        'html' => $html,
        'text' => $text,
      ];
      $context = [
        'email' => $email,
        'symfony_email' => $symfony_email,
        'cacheable_metadata' => $metadata,
      ];
      $this->moduleHandler->alter('email_pre_render', $body, $context);

      if (isset($body['html'])) {
        $symfony_email->html((string) $this->renderer->render($body['html']));
        $metadata->merge(BubbleableMetadata::createFromRenderArray($body['html']));
      }
      if (isset($body['text'])) {
        $symfony_email->text((string) $this->renderer->render($body['text']));
        $metadata->merge(BubbleableMetadata::createFromRenderArray($body['text']));
      }

      $context = [
        'email' => $email,
        'body' => $body,
        'cacheable_metadata' => $metadata,
      ];
      $this->moduleHandler->alter('email_post_render', $symfony_email, $context);

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
