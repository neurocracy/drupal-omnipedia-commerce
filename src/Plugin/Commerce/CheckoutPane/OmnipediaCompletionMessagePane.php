<?php

namespace Drupal\omnipedia_commerce\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowInterface;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\omnipedia_commerce\Service\CommerceOrderInterface;
use Drupal\omnipedia_commerce\Service\ContentAccessProductInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a completion message pane for Omnipedia.
 *
 * @CommerceCheckoutPane(
 *   id           = "omnipedia_completion_message",
 *   label        = @Translation("Omnipedia: completion message"),
 *   default_step = "complete",
 * )
 */
class OmnipediaCompletionMessagePane extends CheckoutPaneBase {

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
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    CheckoutFlowInterface         $checkoutFlow,
    EntityTypeManagerInterface    $entityTypeManager,
    CommerceOrderInterface        $commerceOrder,
    ContentAccessProductInterface $contentAccessProduct,
    LoggerInterface               $loggerChannel
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition,
      $checkoutFlow, $entityTypeManager
    );

    $this->commerceOrder        = $commerceOrder;
    $this->contentAccessProduct = $contentAccessProduct;
    $this->loggerChannel        = $loggerChannel;

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
      $container->get('logger.channel.omnipedia_commerce')
    );
  }

  /**
   * {@inheritdoc}
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

    $baseClass = 'omnipedia-complete-message';

    $paneForm['omnipedia_complete'] = [
      '#type' => 'html_tag',
      '#tag'  => 'div',
      '#attributes' => ['class' => [$baseClass]],

      'message' => $message,
    ];

    $paneForm['message']['#attributes']['class'][] = $baseClass . '__message';

    if (\is_object($node)) {
      $paneForm['link_container'] = [
        '#type' => 'html_tag',
        '#tag'  => 'p',
        '#attributes' => ['class' => [$baseClass . '__link-container']],

        'link'  => [
          '#type'       => 'link',
          '#title'      => $this->t('Start browsing'),
          '#url'        => $node->toUrl(),
          '#attributes' => ['class' => [$baseClass . '__link']],
        ],
      ];
    }

    return $paneForm;

  }

}
