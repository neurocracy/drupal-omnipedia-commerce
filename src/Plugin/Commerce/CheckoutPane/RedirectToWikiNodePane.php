<?php

namespace Drupal\omnipedia_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\omnipedia_commerce\Service\CommerceOrderInterface;
use Drupal\omnipedia_commerce\Service\ContentAccessProductInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides redirect to wiki node pane for Omnipedia.
 *
 * @CommerceCheckoutPane(
 *   id           = "omnipedia_redirect_to_wiki_node",
 *   label        = @Translation("Omnipedia: redirect to wiki page"),
 *   default_step = "complete",
 * )
 *
 * Note that even though PluginBase uses MessengerTrait, which is supposed to
 * provide the 'messenger' service, the dependency is not saved unless one
 * calls MessengerTrait::messenger() to get it, and then it uses the \Drupal
 * static class. Because of this, we instead inject the service into the
 * constructor as per Drupal best practices.
 *
 * @see \Drupal\Core\Messenger\MessengerTrait::messenger()
 *   Returns the messenger service using the \Drupal static class rather than
 *   via dependency injection.
 *
 * @see https://drupal.stackexchange.com/questions/276783/redirect-after-purchasing/279266#279266
 *   Adapted from this Drupal Answers post.
 *
 * @throws \Drupal\commerce\Response\NeedsRedirectException This is thrown on
 *   successfully resolving a wiki node and message to tell Commerce to
 *   redirect.
 */
class RedirectToWikiNodePane extends CheckoutPaneBase {

  /**
   * The name of the product entity field containing the redirect message.
   */
  protected const MESSAGE_FIELD_NAME = 'field_redirect_message';

  /**
   * The name of the product entity field containing a wiki node reference.
   */
  protected const WIKI_NODE_FIELD_NAME = 'field_redirect_wiki_page';

  /**
   * The Omnipedia Commerce order helper service.
   *
   * @var \Drupal\omnipedia_commerce\Service\CommerceOrderInterface
   */
  protected $commerceOrder;

  /**
   * The Omnipedia content access product service.
   *
   * @var \Drupal\omnipedia_commerce\Service\ContentAccessProductInterface
   */
  protected $contentAccessProduct;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\omnipedia_commerce\Service\CommerceOrderInterface $commerceOrder
   *   The Omnipedia Commerce order helper service.
   *
   * @param \Drupal\omnipedia_commerce\Service\ContentAccessProductInterface $contentAccessProduct
   *   The Omnipedia content access product service.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    CheckoutFlowInterface         $checkoutFlow,
    EntityTypeManagerInterface    $entityTypeManager,
    CommerceOrderInterface        $commerceOrder,
    ContentAccessProductInterface $contentAccessProduct,
    LoggerInterface               $loggerChannel,
    MessengerInterface            $messenger
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition,
      $checkoutFlow, $entityTypeManager
    );

    $this->commerceOrder        = $commerceOrder;
    $this->contentAccessProduct = $contentAccessProduct;
    $this->loggerChannel        = $loggerChannel;
    $this->messenger            = $messenger;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration, $pluginId, $pluginDefinition,
    CheckoutFlowInterface $checkoutFlow = null
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $checkoutFlow,
      $container->get('entity_type.manager'),
      $container->get('omnipedia_commerce.commerce_order'),
      $container->get('omnipedia_commerce.content_access_product'),
      $container->get('logger.channel.omnipedia_commerce'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   *
   * Note that in the event that an error is found - such as required fields not
   * existing - this will return the pane form, allowing the complete page to
   * be displayed as a fallback. Because of that, a complete message should
   * still be configured in the checkout flow for the complete step.
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException This is thrown on
   *   successfully resolving a wiki node and message to tell Commerce to
   *   redirect.
   */
  public function buildPaneForm(
    array $paneForm, FormStateInterface $formState, array &$completeForm
  ) {

    /** @var \Drupal\commerce_product\Entity\ProductInterface[] */
    $products = $this->commerceOrder->getProductsFromOrder($this->order);

    // Bail if the order has no items and log an error.
    if (count($products) === 0) {

      $this->loggerChannel->error(
        'Order has no items:<pre>@order</pre>',
        // Don't \print_r($order, true) or it'll cause PHP out of memory error.
        ['@order' => \print_r($this->order->id(), true)]
      );

      return $paneForm;

    }

    foreach ($products as $product) {

      /** @var array A message render array. */
      $message = $this->contentAccessProduct->getProductMessage($product);

      /** @var \Drupal\omnipedia_core\Entity\NodeInterface|null */
      $node = $this->contentAccessProduct->getProductWikiNode($product);

      // Break when we've found both a message and a wiki node.
      if (!empty($message) && \is_object($node)) {
        break;
      }

    }

    // Bail if no message was found and log an error.
    if (empty($message)) {

      $this->loggerChannel->error(
        'Order has no message:<pre>@order</pre>',
        // Don't \print_r($order, true) or it'll cause PHP out of memory error.
        ['@order' => \print_r($this->order->id(), true)]
      );

      return $paneForm;

    }

    if (!isset($node) || !\is_object($node)) {
      return $paneForm;
    }

    $this->messenger->addStatus($message);

    throw new NeedsRedirectException($node->toUrl()->toString());

    // Is this necessary? Does omitting it cause errors somewhere?
    return $paneForm;

  }

}
