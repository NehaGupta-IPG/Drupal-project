<?php

namespace Drupal\media\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\media\IFrameUrlHelper;

/**
 * Provides a form to configure Media settings.
 *
 * @internal
 */
class MediaSettingsForm extends ConfigFormBase {

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typedConfigManager,
    protected IFrameUrlHelper $iFrameUrlHelper,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typedConfigManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['media.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $domain = $this->config('media.settings')->get('iframe_domain');

    if (!$this->iFrameUrlHelper->isSecure($domain)) {
      $message = $this->t('It is potentially insecure to display oEmbed content in a frame that is served from the same domain as your main Drupal site, as this may allow execution of third-party code. Refer to <a href="https://oembed.com/#section3">oEmbed Security Considerations</a>.');
      $this->messenger()->addWarning($message);
    }

    $description = '<p>' . $this->t('Displaying media assets from third-party services, such as YouTube or Twitter, can be risky. This is because many of these services return arbitrary HTML to represent those assets, and that HTML may contain executable JavaScript code. If handled improperly, this can increase the risk of your site being compromised.') . '</p>';
    $description .= '<p>' . $this->t('In order to mitigate the risks, third-party assets are displayed in an iFrame, which effectively sandboxes any executable code running inside it. For even more security, the iFrame can be served from an alternate domain (that also points to your Drupal site), which you can configure on this page. This helps safeguard cookies and other sensitive information.') . '</p>';

    $form['security'] = [
      '#type' => 'details',
      '#title' => $this->t('Security'),
      '#description' => $description,
      '#open' => TRUE,
    ];
    // @todo Figure out how and if we should validate that this domain actually
    // points back to Drupal.
    // See https://www.drupal.org/project/drupal/issues/2965979 for more info.
    $form['security']['iframe_domain'] = [
      '#type' => 'url',
      '#title' => $this->t('iFrame domain'),
      '#size' => 40,
      '#maxlength' => 255,
      '#config_target' => new ConfigTarget('media.settings', 'iframe_domain', toConfig: fn(?string $value) => $value ?: NULL),
      '#description' => $this->t('Enter a different domain from which to serve oEmbed content, including the <em>http://</em> or <em>https://</em> prefix. This domain needs to point back to this site, or existing oEmbed content may not display correctly, or at all.'),
    ];

    $form['security']['standalone_url'] = [
      '#prefix' => '<hr>',
      '#type' => 'checkbox',
      '#title' => $this->t('Standalone media URL'),
      '#config_target' => 'media.settings:standalone_url',
      '#description' => $this->t("Allow users to access @media-entities at /media/{id}.", ['@media-entities' => $this->entityTypeManager->getDefinition('media')->getPluralLabel()]),
    ];
    return parent::buildForm($form, $form_state);
  }

}
