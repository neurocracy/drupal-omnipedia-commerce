<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Form;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\core_event_dispatcher\Event\Form\FormBaseAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter Commerce payment options, adding Stripe links.
 *
 * @see https://www.drupal.org/project/commerce_stripe/issues/2966948
 *   Commerce Stripe issue regarding customizing the Stripe credit card options.
 */
class CommerceCheckoutFlowStripeBaseFormAlterEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   */
  public function __construct(
    TranslationInterface $stringTranslation
  ) {
    $this->stringTranslation = $stringTranslation;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'hook_event_dispatcher.form_base_commerce_checkout_flow.alter' =>
        'onBaseFormAlter',
    ];
  }

  /**
   * Alter the 'commerce_checkout_flow' base form.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormBaseAlterEvent $event
   *   The event object.
   */
  public function onBaseFormAlter(FormBaseAlterEvent $event): void {

    /** @var array */
    $form = &$event->getForm();

    // Don't do anything if these don't exist so we don't cause any errors.
    if (
      !isset($form['payment_information']['payment_method']) ||
      !isset($form['payment_information']['#payment_options'])
    ) {
      return;
    }

    /** @var array The payment method radio buttons. */
    $paymentMethodElement = &$form['payment_information']['payment_method'];

    /** @var \Drupal\commerce_payment\PaymentOption[] The Drupal Commerce payment options for this form. */
    $paymentOptions = $form['payment_information']['#payment_options'];

    foreach ($paymentOptions as $paymentKey => $paymentOption) {

      // Skip payment gateways that aren't Stripe.
      if ($paymentOption->getPaymentGatewayId() !== 'stripe') {
        continue;
      }

      $paymentMethodElement['#options'][$paymentKey] = $this->t(
        '@label (using <a href=":stripeUrl" target="_blank">Stripe</a>)',
        [
          '@label'      => $paymentMethodElement['#options'][$paymentKey],
          ':stripeUrl'  => 'https://stripe.com',
        ]
      );

    }

  }

}
