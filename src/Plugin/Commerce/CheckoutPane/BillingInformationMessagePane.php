<?php

namespace Drupal\omnipedia_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a billing information explanation pane for Omnipedia.
 *
 * @CommerceCheckoutPane(
 *   id           = "omnipedia_billing_information_message",
 *   label        = @Translation("Omnipedia: billing information explanation"),
 *   default_step = "order_information",
 * )
 */
class BillingInformationMessagePane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(
    array $paneForm, FormStateInterface $formState, array &$completeForm
  ) {

    $paneForm['#attributes'] = ['class' => ['block-help']];

    $paneForm['omnipedia_billing_information_message'] = [
      '#type'   => 'html_tag',
      '#tag'    => 'p',
      '#value'  => $this->t('We\'ll need your billing address to determine what taxes we\'re legally required to charge. It will not be used for any other purpose and will not be disclosed to any third-party other than the payment processor you choose. The displayed price will include any applicable taxes.'),
    ];

    return $paneForm;

  }

}
