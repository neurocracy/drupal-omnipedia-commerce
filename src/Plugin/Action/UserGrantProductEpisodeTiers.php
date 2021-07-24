<?php

namespace Drupal\omnipedia_commerce\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Grants product episode tiers to a user.
 *
 * @Action(
 *   id       = "omnipedia_commerce_user_grant_product_episode_tiers",
 *   label    = @Translation("Grant product episode tiers"),
 *   type     = "user",
 *   category = @Translation("Omnipedia"),
 * )
 */
class UserGrantProductEpisodeTiers extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The Commerce product entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $productStorage;

  /**
   * The Omnipedia user episode tiers service.
   *
   * @var \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface
   */
  protected $userEpisodeTiers;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $productStorage
   *   The Commerce product entity storage.
   *
   * @param \Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface $userEpisodeTiers
   *   The Omnipedia user episode tiers service.
   */
  public function __construct(
    array $configuration, $pluginId, $pluginDefinition,
    EntityStorageInterface    $productStorage,
    UserEpisodeTiersInterface $userEpisodeTiers
  ) {

    parent::__construct(
      $configuration, $pluginId, $pluginDefinition
    );

    $this->productStorage   = $productStorage;
    $this->userEpisodeTiers = $userEpisodeTiers;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration, $pluginId, $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager')->getStorage('commerce_product'),
      $container->get('omnipedia_commerce.user_episode_tiers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['product_id' => ''];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form, FormStateInterface $formState
  ) {

    $form['product'] = [
      '#type'               => 'entity_autocomplete',
      '#title'              => $this->t('Product'),
      '#description'        => $this->t(
        'The product containing the episode tiers to grant a user.'
      ),
      '#target_type'        => 'commerce_product',
      '#selection_handler'  => 'default',
      '#selection_settings' => [
        'target_bundles'      => ['content_access'],
      ],
    ];

    /** @var \Drupal\commerce_product\Entity\ProductInterface|null A product entity or null. */
    $product = $this->productStorage->load(
      $this->getConfiguration()['product_id']
    );

    if (\is_object($product)) {
      $form['product']['#default_value'] = $product;
    }

    return $form;

  }

  /**
   * {@inheritdoc}
   *
   * Validation is handled for us by the entity autocomplete element.
   */
  public function submitConfigurationForm(
    array &$form, FormStateInterface $formState
  ) {

    $this->configuration['product_id'] = $formState->getValue('product');

  }

  /**
   * {@inheritdoc}
   */
  public function access(
    $user, AccountInterface $account = null, $returnAsObject = false
  ) {

    /** @var \Drupal\user\UserInterface $user */
    $access = $user->access('update', $account, true)->andIf(
      // Permissions by Term does not have a specific permission for updating
      // a user's permission terms, so this is the closest thing.
      AccessResult::allowedIfHasPermission(
        $account, 'show term permissions on user edit page'
      )
    );

    return $returnAsObject ? $access : $access->isAllowed();

  }

  /**
   * {@inheritdoc}
   */
  public function execute($user = null) {

    if (!\is_object($user)) {
      return;
    }

    /** @var \Drupal\commerce_product\Entity\ProductInterface|null A product entity or null. */
    $product = $this->productStorage->load(
      $this->getConfiguration()['product_id']
    );

    if (!\is_object($product)) {
      return;
    }

    /** @var \Drupal\user\UserInterface $user */
    $this->userEpisodeTiers->grantUserProductEpisodeTiers($user, $product);

  }

}
