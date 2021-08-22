<?php

namespace Drupal\omnipedia_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a PayPal warning checkout pane for Omnipedia.
 *
 * @CommerceCheckoutPane(
 *   id           = "omnipedia_paypal_warning",
 *   label        = @Translation("Omnipedia: PayPal warning"),
 *   default_step = "order_information",
 * )
 */
class PayPalWarningPane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(
    array $paneForm, FormStateInterface $formState, array &$completeForm
  ) {

    $paneForm['omnipedia_paypal_warning'] = [
      '#theme'            => 'status_messages',
      '#message_list'     => ['warning' => [
        [
        '#type'   => 'html_tag',
        '#tag'    => 'p',
        '#value'  => $this->t('We\'re currently experiencing some issues with PayPal payments that could delay your access to the Season Pass. We\'re working to resolve this as soon as possible.'),
        ],
      ]],
      '#status_headings'  => [
        'warning' => $this->t('Warning message'),
      ],
    ];

    return $paneForm;

  }

}
