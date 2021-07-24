<?php

namespace Drupal\omnipedia_commerce\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\omnipedia_commerce\Service\ContentAccessProductInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the Omnipedia Commerce settings form.
 */
class OmnipediaCommerceSettingsForm extends ConfigFormBase {

  /**
   * The Omnipedia content access product service.
   *
   * @var \Drupal\omnipedia_commerce\Service\ContentAccessProductInterface
   */
  protected $contentAccessProduct;

  /**
   * Constructs this form object; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   *
   * @param \Drupal\omnipedia_commerce\Service\ContentAccessProductInterface $contentAccessProduct
   *   The Omnipedia content access product service.
   */
  public function __construct(
    ConfigFactoryInterface        $configFactory,
    ContentAccessProductInterface $contentAccessProduct
  ) {
    parent::__construct($configFactory);

    $this->contentAccessProduct = $contentAccessProduct;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('omnipedia_commerce.content_access_product')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'omnipedia_commerce_settings';
  }

  /**
   * {@inheritdoc}
   *
   * Since we delegate setting the configuration to the content access product
   * service, this form does not directly edit any configuration. However, this
   * method is defined as abstract in ConfigFormBaseTrait so we have to
   * implement it.
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['base_content_access_product'] = [
      '#type'               => 'entity_autocomplete',
      '#title'              => $this->t('Base content access product'),
      '#description'        => $this->t(
        'The base or entry product granting content access. This is usually the season pass.'
      ),
      '#target_type'        => 'commerce_product',
      '#selection_handler'  => 'default',
      '#selection_settings' => [
        'target_bundles'      => ['content_access'],
      ],
    ];

    /** @var \Drupal\commerce_product\Entity\ProductInterface|null A product entity or null. */
    $product = $this->contentAccessProduct->getBaseProduct();

    if (\is_object($product)) {
      $form['base_content_access_product']['#default_value'] = $product;
    }

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->contentAccessProduct->setBaseProductId(
      $form_state->getValue('base_content_access_product')
    );

    parent::submitForm($form, $form_state);

  }

}
