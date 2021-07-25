<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Commerce;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to grant a user episode tiers on Commerce order paid.
 */
class EpisodeTierPermissionEventSubscriber implements EventSubscriberInterface {

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The Omnipedia user episode tiers service.
   *
   * @var \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface
   */
  protected $userEpisodeTiers;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface $userEpisodeTiers
   *   The Omnipedia user episode tiers service.
   */
  public function __construct(
    LoggerInterface           $loggerChannel,
    UserEpisodeTiersInterface $userEpisodeTiers
  ) {
    $this->loggerChannel    = $loggerChannel;
    $this->userEpisodeTiers = $userEpisodeTiers;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OrderEvents::ORDER_PAID => 'onOrderPaid',
    ];
  }

  /**
   * Event handler callback to transfer episode tiers from product to user.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event object.
   */
  public function onOrderPaid(OrderEvent $event) {

    /** @var \Drupal\commerce_order\Entity\OrderInterface The Commerce order associated with this event. */
    $order = $event->getOrder();

    /** @var \Drupal\user\UserInterface The user entity that placed this order. */
    $user = $order->getCustomer();

    // Bail if this is not an authenticated user and log an error.
    if ($user->isAnonymous()) {
      $this->loggerChannel->log(
        RfcLogLevel::ERROR,
        'User is anonymous - this could indicate a misconfiguration of commerce functionality or user permissions.'
      );

      return;
    }

    // Bail if the order has no items and log an error.
    if (!$order->hasItems()) {

      $this->loggerChannel->log(
        RfcLogLevel::ERROR,
        'Order has no items:<pre>@order</pre>',
        ['@order' => \print_r($order, true)]
      );

      return;

    }

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface[] The items for this order. */
    $orderItems = $order->getItems();

    foreach ($orderItems as $orderItem) {

      /** @var \Drupal\commerce\PurchasableEntityInterface|null The product variation entity or null. */
      $productVariation = $orderItem->getPurchasedEntity();

      // Skip to the next order item if the product variation is not an object,
      // i.e. null.
      if (!\is_object($productVariation)) {
        continue;
      }

      /** @var \Drupal\commerce_product\Entity\ProductInterface|null The purchased product or null. */
      $product = $productVariation->getProduct();

      // Skip to the next order item if we didn't get a product entity.
      if (!\is_object($product)) {
        continue;
      }

      $this->userEpisodeTiers->grantUserProductEpisodeTiers($user, $product);

    }

  }

}
