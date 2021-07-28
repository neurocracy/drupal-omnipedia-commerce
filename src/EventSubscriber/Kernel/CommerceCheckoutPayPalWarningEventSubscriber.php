<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\StackedRouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to show PayPal warning on the Commerce checkout route.
 *
 * As of 2021-07-21, we noticed an issue with some PayPal payments getting
 * stuck at the capture phase and thus not being marked as complete, which
 * means that the EpisodeTierPermissionEventSubscriber is not getting triggered
 * because the order has not yet technically been paid. Until this is resolved,
 * we're showing a warning message on the checkout form to notify users of this.
 *
 * @see \Drupal\omnipedia_commerce\EventSubscriber\Commerce\EpisodeTierPermissionEventSubscriber
 */
class CommerceCheckoutPayPalWarningEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The Drupal current route match service.
   *
   * @var \Drupal\Core\Routing\StackedRouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * The Drupal messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Drupal\Core\Routing\StackedRouteMatchInterface $currentRouteMatch
   *   The Drupal current route match service.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   */
  public function __construct(
    StackedRouteMatchInterface  $currentRouteMatch,
    MessengerInterface          $messenger,
    TranslationInterface        $stringTranslation
  ) {
    $this->currentRouteMatch  = $currentRouteMatch;
    $this->messenger          = $messenger;
    $this->stringTranslation  = $stringTranslation;
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
   * Output a PayPal warning message on the order information checkout step.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   Symfony response event object.
   */
  public function onKernelRequest(GetResponseEvent $event): void {

    if (
      $this->currentRouteMatch->getRouteName() !== 'commerce_checkout.form' ||
      $this->currentRouteMatch->getParameter('step') !== 'order_information'
    ) {
      return;
    }

    $this->messenger->addWarning($this->t(
      'We\'re currently experiencing some issues with PayPal payments that could delay your access to the Season Pass. We\'re working to resolve this as soon as possible.'
    ));

  }

}
