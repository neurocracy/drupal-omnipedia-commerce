<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Commerce;

use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_payment\Event\PaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\PaymentOrderUpdaterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to correct Commerce order payment issues.
 *
 * This works around multiple issues where an order may not be correctly updated
 * to reflect a payment succeeding. This primarily occurs with PayPal at the
 * time of writing, where multiple PHP and Symfony errors prevent the order
 * being marked as paid even though the PayPal payment is completed. The
 * sequence of errors seen:
 *
 * - commerce_order causes Symfony to throw an exception during checkout when
 *   it tries to save the order entity, stating that the session cannot be
 *   started because headers have already been sent.
 *
 * - An EntityStorageException is thrown by SqlContentEntityStorage->save() when
 *   Drupal\commerce_payment\PaymentOrderUpdater attempts to update the order.
 */
class OrderPaymentCorrectorEventSubscriber implements EventSubscriberInterface {

  /**
   * The Symfony session attribute key where we store order IDs to be corrected.
   *
   * @see https://symfony.com/doc/3.4/components/http_foundation/sessions.html#namespaced-attributes
   */
  protected const SESSION_KEY = 'omnipedia/commerceOrdersToCorrect';

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The Commerce order entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The Commerce payment order updater service.
   *
   * @var \Drupal\commerce_payment\PaymentOrderUpdaterInterface
   */
  protected $paymentOrderUpdater;

  /**
   * The Symfony session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Drupal\commerce_payment\PaymentOrderUpdaterInterface $paymentOrderUpdater
   *   The Commerce payment order updater service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type plug-in manager.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The Symfony session service.
   */
  public function __construct(
    PaymentOrderUpdaterInterface  $paymentOrderUpdater,
    EntityTypeManagerInterface    $entityTypeManager,
    LoggerInterface               $loggerChannel,
    SessionInterface              $session
  ) {
    $this->orderStorage         = $entityTypeManager->getStorage(
      'commerce_order'
    );
    $this->loggerChannel        = $loggerChannel;
    $this->paymentOrderUpdater  = $paymentOrderUpdater;
    $this->session              = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST         => 'onKernelRequest',
      PaymentEvents::PAYMENT_CREATE => 'onPaymentCreate',
    ];
  }

  /**
   * On kernel request event handler.
   *
   * This loads any order IDs found in the session attribute and sends them to
   * the Commerce payment order updater service to have their total paid
   * recalculated. If an exception is caught, it will be logged and the order
   * ID will be left in the session attribute, so that another attempt will be
   * made on the next request, until it succeeds.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Symfony response event object.
   *
   * @todo Limit how many times this is attempted, and give up after a set
   *   number to avoid potential performance issues? We could log an error when
   *   we give up.
   */
  public function onKernelRequest(GetResponseEvent $event): void {

    /** @var int[] */
    $orderIds = $this->session->get(self::SESSION_KEY, []);

    /** @var int[] */
    $updatedOrderIds = [];

    foreach ($orderIds as $orderId) {

      /* @var \Drupal\commerce_order\Entity\OrderInterface|null */
      $order = $this->orderStorage->load($orderId);

      if (!\is_object($order)) {
        continue;
      }

      $this->loggerChannel->debug(
        'Attempting to update payments for order @orderId',
        ['@orderId' => $order->id()]
      );

      /** @var boolean Whether an exception was thrown when attempting to update the order total. */
      $hadError = false;

      try {

        $this->paymentOrderUpdater->updateOrder($order, true);

      } catch (\Exception $exception) {

        // Log the exception.
        //
        // @see \watchdog_exception()
        //   We're replicating what this function does, but using the injected
        //   logger channel.
        $this->loggerChannel->error(
          '%type: @message in %function (line %line of %file).',
          Error::decodeException($exception)
        );

        // @todo Do we need this or can we just do isset($exception) outside of
        //   this try {} catch () {} ?
        $hadError = true;

      }

      if ($hadError === false) {
        $updatedOrderIds[] = $order->id();
      }

    }

    // Remove the order IDs that were successfully updated from $orderIds.
    $orderIds = \array_diff($updatedOrderIds, $orderIds);

    // If no order IDs are left in the queue, remove the session attribute.
    if (empty($orderIds)) {
        $this->session->remove(self::SESSION_KEY);

    // Otherwise, save the remaining order IDs to the session attribute.
    } else {
      $this->session->set(self::SESSION_KEY, $orderIds);

    }

  }

  /**
   * Payment create event handler.
   *
   * This adds the order ID associated with the payment that was saved to the
   * session attribute, queueing it to have its total paid recalculated at the
   * start of the next request.
   *
   * @param \Drupal\commerce_payment\Event\PaymentEvent $event
   *   The event object.
   */
  public function onPaymentCreate(PaymentEvent $event): void {

    /** @var \Drupal\commerce_payment\Entity\PaymentInterface */
    $payment = $event->getPayment();

    /** @var int|null */
    $orderId = $payment->getOrderId();

    if ($orderId !== null) {

      /** @var int[] */
      $orderIds = $this->session->get(self::SESSION_KEY, []);

      $orderIds[] = $orderId;

      $this->session->set(self::SESSION_KEY, $orderIds);

    }

  }

}
