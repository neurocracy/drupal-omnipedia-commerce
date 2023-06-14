<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Kernel;

use Drupal\commerce_cart\EventSubscriber\CartEventSubscriber;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to remove the Commerce added to cart message.
 *
 * Since there doesn't seem to be a safe and easy way to remove a single message
 * from Drupal's messenger service, we instead remove the event subscriber that
 * produces it, as that's the only function of that event subscriber at the time
 * of writing. This may require revisiting if the Commerce Cart module later
 * adds more functionality to that event subscriber, which could break upon
 * being removed.
 *
 * @todo Extend the Commerce Cart event subscriber to conditionally add the
 *   message if a redirect was not performed?
 *
 * @see \Drupal\commerce_cart\EventSubscriber\CartEventSubscriber
 *   The Commerce Cart event subscriber that we remove.
 *
 * @see \Drupal\commerce_cart_redirection\EventSubscriber\CommerceCartRedirectionSubscriber
 *   Commerce Cart Redirection event subscriber that redirects to the checkout
 *   route if a product bundle is configured to do so.
 *
 * @see https://www.drupal.org/project/commerce_cart_redirection/issues/3218501
 *   Issue opened about this.
 */
class CommerceCartAddedMessageRemovalEventSubscriber implements EventSubscriberInterface {

  /**
   * The Commerce Cart event subscriber that we remove.
   *
   * @var \Drupal\commerce_cart\EventSubscriber\CartEventSubscriber
   */
  protected $cartEventSubscriber;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Drupal\commerce_cart\EventSubscriber\CartEventSubscriber $cartEventSubscriber
   *   The Commerce Cart event subscriber that we remove.
   */
  public function __construct(CartEventSubscriber $cartEventSubscriber) {
    $this->cartEventSubscriber = $cartEventSubscriber;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => 'onKernelRequest',
    ];
  }

  /**
   * Removes the event subscriber that produces the added to cart message.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   Symfony response event object.
   *
   * @param string $eventName
   *   The name of the event being dispatched.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The Symfony event dispatcher service.
   */
  public function onKernelRequest(
    RequestEvent $event,
    string $eventName,
    EventDispatcherInterface $eventDispatcher
  ): void {
    $eventDispatcher->removeSubscriber($this->cartEventSubscriber);
  }

}
