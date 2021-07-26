<?php

namespace Drupal\omnipedia_commerce\Form;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\omnipedia_commerce\Service\CommerceOrderInterface;
use Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for confirming granting users their order episode tiers.
 */
class GrantOrderProductEpisodeTiersConfirmForm extends ConfirmFormBase {

  /**
   * The cancel route.
   */
  protected const CANCEL_ROUTE = 'entity.commerce_order.collection';

  /**
   * Our temp store name.
   */
  protected const TEMP_STORE_NAME = 'grant_order_episode_tiers_confirm';

  /**
   * The Omnipedia Commerce order helper service.
   *
   * @var \Drupal\omnipedia_commerce\Service\CommerceOrderInterface
   */
  protected $commerceOrder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The Commerce order entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The Drupal tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The Omnipedia user episode tiers service.
   *
   * @var \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface
   */
  protected $userEpisodeTiers;

  /**
   * Constructs this form object; saves dependencies.
   *
   * @param \Drupal\omnipedia_commerce\Service\CommerceOrderInterface $commerceOrder
   *   The Omnipedia Commerce order helper service.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $orderStorage
   *   The Commerce order entity storage.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   The Drupal tempstore factory.
   *
   * @param \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface $userEpisodeTiers
   *   The Omnipedia user episode tiers service.
   */
  public function __construct(
    CommerceOrderInterface    $commerceOrder,
    AccountInterface          $currentUser,
    MessengerInterface        $messenger,
    EntityStorageInterface    $orderStorage,
    TranslationInterface      $stringTranslation,
    PrivateTempStoreFactory   $tempStoreFactory,
    UserEpisodeTiersInterface $userEpisodeTiers
  ) {
    $this->commerceOrder      = $commerceOrder;
    $this->currentUser        = $currentUser;
    $this->messenger          = $messenger;
    $this->orderStorage       = $orderStorage;
    $this->stringTranslation  = $stringTranslation;
    $this->tempStoreFactory   = $tempStoreFactory;
    $this->userEpisodeTiers   = $userEpisodeTiers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('omnipedia_commerce.commerce_order'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('entity_type.manager')->getStorage('commerce_order'),
      $container->get('string_translation'),
      $container->get('tempstore.private'),
      $container->get('omnipedia_commerce.user_episode_tiers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'grant_order_episode_tiers_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {

    /* @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $this->tempStoreFactory
      ->get(self::TEMP_STORE_NAME)
      ->get($this->currentUser()->id());

    return $this->formatPlural(
      count($orders),
      'Are you sure you want to grant this user the following product\'s episode tiers?',
      'Are you sure you want to grant these users the following products\' episode tiers?',
    );

  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url(self::CANCEL_ROUTE);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Grant episode tiers');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /* @var \Drupal\commerce_order\Entity\OrderInterface[] $orders */
    $orders = $this->tempStoreFactory
      ->get(self::TEMP_STORE_NAME)
      ->get($this->currentUser()->id());

    if (!$orders) {
      return $this->redirect(self::CANCEL_ROUTE);
    }

    $form['order_table'] = [
      '#type'   => 'table',
      '#header' => [
        $this->t('User'),
        $this->t('Product'),
      ],
      '#rows'   => [],
    ];

    $form['orders'] = ['#tree' => true];

    /* @var \Drupal\commerce_order\Entity\OrderInterface $order */
    foreach ($orders as $order) {

      /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
      foreach ($this->commerceOrder->getProductsFromOrder($order) as $product) {

        $form['orders'][$order->id()] = [
          '#type'   => 'hidden',
          '#value'  => $order->id(),
        ];

        $form['order_table']['#rows'][] = [
          $order->getCustomer()->getDisplayName(),
          $product->getTitle(),
        ];

      }

    }

    $form = parent::buildForm($form, $form_state);

    // Remove the "This action cannot be undone." as this action can actually be
    // undone.
    unset($form['description']);

    return $form;

  }

  /**
   * {@inheritdoc}
   *
   * @todo Use the Batch API so this doesn't time if there are a lot of orders
   *   selected?
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Delete our temp store.
    $this->tempStoreFactory->get(self::TEMP_STORE_NAME)->delete(
      $this->currentUser()->id()
    );

    if ($form_state->getValue('confirm')) {

      foreach ($form_state->getValue('orders') as $orderId => $value) {

        /* @var \Drupal\commerce_order\Entity\OrderInterface */
        $order = $this->orderStorage->load($orderId);

        /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
        foreach (
          $this->commerceOrder->getProductsFromOrder($order) as $product
        ) {

          $this->userEpisodeTiers->grantUserProductEpisodeTiers(
            $order->getCustomer(), $product
          );

        }

      }

      $this->messenger->addStatus($this->formatPlural(
        count($form_state->getValue('orders')),
        'Granted episode tiers to 1 order.',
        'Granted episode tiers to @count orders.',
      ));

    }

    $form_state->setRedirect(self::CANCEL_ROUTE);

  }

}
