<?php

namespace Drupal\omnipedia_commerce\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the Omnipedia Commerce settings form.
 */
class OmnipediaCommerceSettingsForm extends ConfigFormBase {

  /**
   * The configuration name this form is for.
   */
  protected const CONFIG_NAME = 'omnipedia_commerce.settings';

  /**
   * The Commerce product entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  /**
   * Constructs this form object; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The factory for configuration objects.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $productStorage
   *   The Commerce product entity storage.
   */
  public function __construct(
    ConfigFactoryInterface  $configFactory,
    EntityStorageInterface  $productStorage
  ) {
    parent::__construct($configFactory);

    $this->productStorage = $productStorage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')->getStorage('commerce_product')
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
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\Core\Config\Config */
    $config = $this->config(self::CONFIG_NAME);

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

    /** @var string|null */
    $productId = $config->get('base_content_access_product_id');

    if (!empty($productId)) {

      /** @var \Drupal\commerce_product\Entity\ProductInterface|null A product entity or null. */
      $product = $this->productStorage->load($productId);

      if (\is_object($product)) {
        $form['base_content_access_product']['#default_value'] = $product;
      }

    }

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config(self::CONFIG_NAME)->set(
      'base_content_access_product_id',
      $form_state->getValue('base_content_access_product')
    )->save();

    parent::submitForm($form, $form_state);

  }

}
