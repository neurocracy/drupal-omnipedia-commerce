<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Commerce;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\permissions_by_term\Service\AccessStorage;
use Drupal\permissions_by_term\Service\NodeAccess;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to grant a user episode tiers on Commerce order paid.
 */
class EpisodeTierPermissionEventSubscriber implements EventSubscriberInterface {

  /**
   * Whether node access records are disabled in the Permissions by Term module.
   *
   * @var bool
   */
  protected $disabledNodeAccessRecords;

  /**
   * The Permissions by Term module access storage service.
   *
   * @var \Drupal\permissions_by_term\Service\AccessStorage
   */
  protected $accessStorage;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The Permissions by Term module node access service.
   *
   * @var \Drupal\permissions_by_term\Service\NodeAccess
   */
  protected $nodeAccess;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal configuration factory service.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\permissions_by_term\Service\AccessStorage $accessStorage
   *   The Permissions by Term module access storage service.
   *
   * @param \Drupal\permissions_by_term\Service\NodeAccess $nodeAccess
   *   The Permissions by Term module node access service.
   */
  public function __construct(
    ConfigFactoryInterface  $configFactory,
    LoggerInterface         $loggerChannel,
    AccessStorage           $accessStorage,
    NodeAccess              $nodeAccess
  ) {
    $this->loggerChannel  = $loggerChannel;
    $this->accessStorage  = $accessStorage;
    $this->nodeAccess     = $nodeAccess;

    $this->disabledNodeAccessRecords = $configFactory
      ->get('permissions_by_term.settings')->get('disable_node_access_records');
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
   *
   * @see \permissions_by_term_user_form_submit()
   *   Much of the code relating to Permissions by Term is adapted from this and
   *   altered to use dependency injection.
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

    /** @var int The user ID (uid) that placed this order. */
    $uid = $order->getCustomerId();

    /** @var string The user's preferred language code. */
    $langCode = $user->getPreferredLangcode();

    /** @var string[] Zero or more permissions that the user has before purchase. Note that values are single strings containing term IDs, user IDs, and language codes all concatenated together. */
    $prePurchasePermissions = $this->accessStorage
      ->getAllTermPermissionsByUserId($uid);

    /** @var int[] Term IDs (tids) to assign the user as permissions. */
    $tids = [];

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

      // Skip to the next order item if we didn't get a product entity or if we
      // did get a product entity but it doesn't have the episode tier field.
      if (!\is_object($product) || !$product->hasField('field_episode_tier')) {
        continue;
      }

      foreach ($product->get('field_episode_tier') as $fieldItem) {

        /** @var string|null The term ID (tid) for this single episode tier or null if not set. */
        $tid = $fieldItem->target_id;

        // Skip this term ID (tid) if we got null or if it's already in $tids.
        if (empty($tid) || \in_array((int) $tid, $tids)) {
          continue;
        }

        $tids[] = (int) $tid;

      }

    }

    // Add all $tids to the user as term permissions.
    foreach ($tids as $tid) {
      $this->accessStorage->addTermPermissionsByUserIds(
        [$uid], $tid, $langCode
      );
    }

    /** @var string[] Zero or more permissions that the user has after purchase. Note that values are single strings containing term IDs, user IDs, and language codes all concatenated together. */
    $postPurchasePermissions = $this->accessStorage
      ->getAllTermPermissionsByUserId($uid);

    // Rebuild node permissions if needed.
    if (
      !$this->disabledNodeAccessRecords &&
      // Note that the order of the parameters to \array_diff() matters, as the
      // first parameter is what the other arrays are checked against. Since we
      // only expect a product to add permissions and not remove any, we only
      // check against the post-purchase permissions.
      !empty(\array_diff($postPurchasePermissions, $prePurchasePermissions))
    ) {
      $this->nodeAccess->rebuildAccess($uid);
    }

  }

}
