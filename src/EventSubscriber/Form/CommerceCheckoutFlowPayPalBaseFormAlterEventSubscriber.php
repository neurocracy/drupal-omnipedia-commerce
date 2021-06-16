<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Form;

use Drupal\core_event_dispatcher\Event\Form\FormBaseAlterEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter Commerce checkout flow PayPal buttons location.
 *
 * This moves the PayPal Smart Payment Buttons from the main form to the actions
 * so that it's output to the checkout footer for more consistent UX with the
 * other checkout steps.
 *
 * @see \commerce_paypal_form_commerce_checkout_flow_alter()
 *   PayPal Smart Payment Buttons added in this alter hook.
 *
 * @see https://www.drupal.org/project/commerce_paypal/issues/3218809
 *   Issue opened about moving the PayPal Smart Payment Buttons.
 */
class CommerceCheckoutFlowPayPalBaseFormAlterEventSubscriber implements EventSubscriberInterface {

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
      !isset($form['actions']) ||
      !isset($form['paypal_smart_payment_buttons'])
    ) {
      return;
    }

    $form['actions']['paypal_smart_payment_buttons'] =
      $form['paypal_smart_payment_buttons'];

    // Remove the original PayPal buttons now that we've copied them to actions.
    unset($form['paypal_smart_payment_buttons']);

    // Remove the default next button as the PayPal buttons are used instead.
    unset($form['actions']['next']);

    // Commerce PayPal sets this to false to hide it, so undo that.
    unset($form['actions']['#access']);

  }

}
