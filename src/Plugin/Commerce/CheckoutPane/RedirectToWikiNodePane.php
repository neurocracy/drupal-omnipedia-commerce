<?php

namespace Drupal\omnipedia_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\omnipedia_commerce\Service\CommerceOrderInterface;
use Drupal\omnipedia_core\Service\WikiNodeMainPageInterface;
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
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The Omnipedia wiki node main page service.
   *
   * @var \Drupal\omnipedia_core\Service\WikiNodeMainPageInterface
   */
  protected $wikiNodeMainPage;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\omnipedia_commerce\Service\CommerceOrderInterface $commerceOrder
   *   The Omnipedia Commerce order helper service.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   *
   * @param \Drupal\omnipedia_core\Service\WikiNodeMainPageInterface $wikiNodeMainPage
   *   The Omnipedia wiki node main page service.
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    CheckoutFlowInterface       $checkoutFlow,
    EntityTypeManagerInterface  $entityTypeManager,
    CommerceOrderInterface      $commerceOrder,
    LoggerInterface             $loggerChannel,
    MessengerInterface          $messenger,
    WikiNodeMainPageInterface   $wikiNodeMainPage
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition,
      $checkoutFlow, $entityTypeManager
    );

    $this->commerceOrder    = $commerceOrder;
    $this->loggerChannel    = $loggerChannel;
    $this->messenger        = $messenger;
    $this->wikiNodeMainPage = $wikiNodeMainPage;

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
      $container->get('logger.channel.omnipedia_commerce'),
      $container->get('messenger'),
      $container->get('omnipedia.wiki_node_main_page')
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

      $this->loggerChannel->log(
        RfcLogLevel::ERROR,
        'Order has no items:<pre>@order</pre>',
        ['@order' => \print_r($this->order, true)]
      );

      return $paneForm;

    }

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface[] The items for this order. */
    $orderItems = $this->order->getItems();

    /** @var string|null The wiki node ID (nid) to redirect to, if any. */
    $nid = null;

    /** @var string|null The message to display to the user after they've been redirected. */
    $message = null;

    foreach ($products as $product) {

      // Skip to the next product if this one doesn't have the redirect fields.
      if (
        !$product->hasField(self::WIKI_NODE_FIELD_NAME) ||
        !$product->hasField(self::MESSAGE_FIELD_NAME)
      ) {
        continue;
      }

      foreach (
        $product->get(self::WIKI_NODE_FIELD_NAME) as $delta => $fieldItem
      ) {

        /** @var string|null The node ID (nid) for this  or null if not set. */
        $nid = $fieldItem->target_id;

        // Skip this field if it doesn't contain a value.
        if (empty($fieldItem->target_id)) {
          continue;
        }

        $nid = $fieldItem->target_id;

        // Use the first one found.
        break;

      }

      // Get the message regardless of whether or not we found a node ID (nid),
      // as the wiki node field can be empty.
      $message = $product->get(self::MESSAGE_FIELD_NAME)->getString();

    }

    // Bail if no message was found and log an error. Note that we need to use
    // empty() in case we got an empty string.
    if (empty($message)) {

      $this->loggerChannel->log(
        RfcLogLevel::ERROR,
        'Order has no message:<pre>@order</pre>',
        ['@order' => \print_r($this->order, true)]
      );

      return $paneForm;

    }

    // Fall back to the default main page if no wiki node was set.
    if ($nid === null) {
      $nid = $this->wikiNodeMainPage->getMainPage('default')->nid->getString();
    }

    $this->messenger->addStatus($message);

    throw new NeedsRedirectException(
      Url::fromRoute('entity.node.canonical', ['node' => $nid])->toString()
    );

  }

}
