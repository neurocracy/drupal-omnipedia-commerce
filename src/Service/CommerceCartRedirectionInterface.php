<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * The Omnipedia Commerce Cart Redirection helper service interface.
 */
interface CommerceCartRedirectionInterface {

  /**
   * Determine if a cart contains a product configured to redirect to checkout.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $cart
   *   The cart.
   *
   * @return boolean
   *   True if the provided cart contains a product that's configured to
   *   redirect to checkout on adding to cart, false otherwise.
   */
  public function isCartToRedirect(?OrderInterface $cart): bool;

}
