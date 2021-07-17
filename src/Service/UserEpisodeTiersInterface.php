<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * The Omnipedia user episode tiers service interface.
 */
interface UserEpisodeTiersInterface {

  /**
   * Grant a user the episode tiers in the provided Drupal Commerce product.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account. Note that this cannot be the anonymous user.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   A Drupal Commerce product entity which has the episode tiers field.
   */
  public function grantUserProductEpisodeTiers(
    AccountInterface $user, ProductInterface $product
  ): void;

}
