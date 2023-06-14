<?php

namespace Drupal\omnipedia_commerce\EventSubscriber\Form;

use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_cart\Form\AddToCartFormInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\core_event_dispatcher\Event\Form\FormAlterEvent;
use Drupal\core_event_dispatcher\FormHookEvents;
use Drupal\Core\Url;
use Drupal\omnipedia_commerce\Service\CommerceCartRedirectionInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Event subscriber to alter the Commerce add to cart form.
 *
 * If the product associated with the add to cart form is already in the cart
 * and the form would redirect to the checkout via the
 * commerce_cart_redirection module, this removes the submit and validate
 * handlers from the form and sets a redirect to the checkout when the user
 * submits the form. This essentially bypasses the error that would be
 * triggered by the commerce_product_limits module from the user and acts as
 * if the product was not already in their cart.
 *
 * @see https://www.drupal.org/project/commerce_product_limits/issues/3224504
 *   Issue opened about this.
 *
 * @see https://drupal.stackexchange.com/questions/249398/how-do-i-count-the-number-of-products-in-cart-programmatically#255314
 *   Inspired by this.
 */
class CommerceAddToCartFormAlterEventSubscriber implements EventSubscriberInterface {

  /**
   * The Commerce cart provider service.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $commerceCartProvider;

  /**
   * The Omnipedia Commerce Cart Redirection helper service.
   *
   * @var \Drupal\omnipedia_commerce\Service\CommerceCartRedirectionInterface
   */
  protected $commerceCartRedirection;

  /**
   * Event subscriber constructor; saves dependencies.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $commerceCartProvider
   *   The Commerce cart provider service.
   *
   * @param \Drupal\omnipedia_commerce\Service\CommerceCartRedirectionInterface $commerceCartRedirection
   *   The Omnipedia Commerce Cart Redirection helper service.
   */
  public function __construct(
    CartProviderInterface             $commerceCartProvider,
    CommerceCartRedirectionInterface  $commerceCartRedirection
  ) {
    $this->commerceCartProvider     = $commerceCartProvider;
    $this->commerceCartRedirection  = $commerceCartRedirection;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      FormHookEvents::FORM_ALTER => 'onFormAlter',
    ];
  }

  /**
   * Get the current cart.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *
   * @todo Don't hard code the 'default' order type ID if at all possible.
   */
  protected function getCart(): ?OrderInterface {
    return $this->commerceCartProvider->getCart('default');
  }

  /**
   * Determine if the provided product is present in the cart.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product entity.
   *
   * @return boolean
   *   True if the provided product is already in the user's cart, false if not.
   */
  protected function isProductInCart(ProductInterface $product): bool {

    /** @var \Drupal\commerce_order\Entity\OrderInterface|null */
    $cart = $this->getCart();

    // If there's no cart, the product cannot be in it.
    if (!\is_object($cart)) {
      return false;
    }

    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface[] */
    $productVariations = $product->getVariations();

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $orderItem */
    foreach ($cart->getItems() as $orderItem) {

      if (\in_array($orderItem->getPurchasedEntity(), $productVariations)) {
        return true;
      }

    }

    return false;

  }

  /**
   * Alter the Commerce add to cart form.
   *
   * @param \Drupal\core_event_dispatcher\Event\Form\FormAlterEvent $event
   *   The event object.
   */
  public function onFormAlter(FormAlterEvent $event): void {

    /** @var \Drupal\commerce_order\Entity\OrderInterface|null */
    $cart = $this->getCart();

    // Bail if no cart was found.
    if (!\is_object($cart)) {
      return;
    }

    /** @var \Drupal\Core\Form\FormStateInterface */
    $formState = $event->getFormState();

    // Bail if not the add to cart form.
    if (!($formState->getFormObject() instanceof AddToCartFormInterface)) {
      return;
    }

    /** @var array */
    $form = &$event->getForm();

    /** @var \Drupal\commerce_product\Entity\ProductInterface|null $product */
    $product = $formState->get('product');

    // Bail if we can't get a product entity.
    if (!\is_object($product)) {
      return;
    }

    if (
      !$this->isProductInCart($product) ||
      !$this->commerceCartRedirection->isCartToRedirect($cart)
    ) {
      return;
    }

    // Set these to empty so that the submit and validate handlers are bypassed
    // as we don't want the form to do anything other than redirect, and having
    // the product already in the cart would cause a form error triggered by
    // the commerce_product_limits module. Note that both have to be empty to
    // prevent random 403s on redirecting to the checkout route.
    $form['#submit']    = [];
    $form['#validate']  = [];

    $form['actions']['submit']['#submit'] = $form['#submit'];

    $formState->setResponse(new RedirectResponse(Url::fromRoute(
      'commerce_checkout.form', [
        'commerce_order' => $cart->id(),
      ])->toString()));

  }

}
