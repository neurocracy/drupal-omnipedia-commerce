<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\omnipedia_commerce\Service\CommerceCartRedirectionInterface;
use Drupal\omnipedia_commerce\Service\CommerceOrderInterface;

/**
 * The Omnipedia Commerce Cart Redirection helper service.
 */
class CommerceCartRedirection implements CommerceCartRedirectionInterface {

  /**
   * The Omnipedia Commerce order helper service.
   *
   * @var \Drupal\omnipedia_commerce\Service\CommerceOrderInterface
   */
  protected $commerceOrder;

  /**
   * The Drupal configuration object factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\omnipedia_commerce\Service\CommerceOrderInterface $commerceOrder
   *   The Omnipedia Commerce order helper service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal configuration object factory service.
   */
  public function __construct(
    CommerceOrderInterface  $commerceOrder,
    ConfigFactoryInterface  $configFactory
  ) {
    $this->commerceOrder  = $commerceOrder;
    $this->configFactory  = $configFactory;
  }

  /**
   * Determine if a product is configured to redirect cart to checkout.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product entity.
   *
   * @return boolean
   *   True if this product is configured to redirect to checkout on adding to
   *   cart, false otherwise.
   *
   * @see \commerce_cart_redirection_form_alter()
   *   Adapted from this as there's no API provided for the module, so we have
   *   to load the config and figure it out ourselves.
   */
  protected function isProductToRedirect(ProductInterface $product): bool {

    /** @var \Drupal\Core\Config\ImmutableConfig */
    $config = $this->configFactory->get('commerce_cart_redirection.settings');

    /** @var string[]|null */
    $activeProductTypes = $config->get('product_bundles');

    if (!$activeProductTypes) {
      return false;
    }

    /** @var string[]|null */
    $negate = $config->get('negate_product_bundles');

    /** @var \Drupal\commerce_product\Entity\ProductTypeInterface */
    $productType = $product->getDefaultVariation()->bundle();

    if (
      isset($activeProductTypes[$productType]) &&
      $activeProductTypes[$productType] !== 0
    ) {

      if (!$negate) {
        return true;
      }

    } else {

      if ($negate) {
        return true;
      }

    }

    return false;

  }

  /**
   * {@inheritdoc}
   */
  public function isCartToRedirect(?OrderInterface $cart): bool {

    if (!\is_object($cart)) {
      return false;
    }

    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    foreach ($this->commerceOrder->getProductsFromOrder($cart) as $product) {

      // Return true at the first product that's set to redirect.
      if ($this->isProductToRedirect($product) === true) {
        return true;
      }

    }

    return false;

  }

}
