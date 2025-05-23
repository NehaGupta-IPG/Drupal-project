<?php

namespace Drupal\Core\Action\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Sends a message to the current user's screen.
 */
#[Action(
  id: 'action_message_action',
  label: new TranslatableMarkup('Display a message to the user.'),
  context_definitions: [
    'message' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Message"),
      description: new TranslatableMarkup("The message to be displayed to the current user. You may include placeholders like [node:title], [user:name], and [comment:body] to represent data that will be different each time message is sent. Only the placeholders passed to the entity context will be available."),
    ),
    // @Todo Considering allowing multiple entities.
    'entity' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Entity"),
      required: FALSE,
      description: new TranslatableMarkup("The contextual entity that can be used for token replacements.")
    ),
  ],
)]
class MessageAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a MessageAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly Token $token,
    protected readonly RendererInterface $renderer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('token'), $container->get('renderer'));
  }

  /**
   * {@inheritdoc}
   */
  protected function doExecute(string $message, ?EntityInterface $entity): ActionResult {
    $data = $entity ? [$entity->getEntityTypeId() => $entity->id()] : [];
    // @Todo Test if $message needs Xss::filterAdmin() from context and config.
    $message = $this->token->replace($message, $data);
    $build = [
      '#markup' => $message,
    ];
    // @todo Fix in https://www.drupal.org/node/2577827
    $this->messenger->addStatus($this->renderer->renderInIsolation($build));

    return ActionResult::success($this->t('The message has been displayed.'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'message' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'],
      '#required' => TRUE,
      '#rows' => '8',
      '#description' => $this->t('The message to be displayed to the current user. You may include placeholders like [node:title], [user:account-name], [user:display-name] and [comment:body] to represent data that will be different each time message is sent. Not all placeholders will be available in all contexts.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['message'] = $form_state->getValue('message');
  }

  /**
   * {@inheritdoc}
   */
  public function access(?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowed();
    return $return_as_object ? $result : $result->isAllowed();
  }

}
