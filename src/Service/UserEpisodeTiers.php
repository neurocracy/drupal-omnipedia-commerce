<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\omnipedia_commerce\Service\UserEpisodeTiersInterface;
use Drupal\permissions_by_term\Service\AccessStorage;
use Drupal\permissions_by_term\Service\NodeAccess;
use Psr\Log\LoggerInterface;

/**
 * The Omnipedia user episode tiers service.
 */
class UserEpisodeTiers implements UserEpisodeTiersInterface {

  /**
   * Whether node access records are disabled in the Permissions by Term module.
   *
   * @var bool
   */
  protected $disabledNodeAccessRecords;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $loggerChannel;

  /**
   * The Permissions by Term module access storage service.
   *
   * @var \Drupal\permissions_by_term\Service\AccessStorage
   */
  protected $accessStorage;

  /**
   * The Permissions by Term module node access service.
   *
   * @var \Drupal\permissions_by_term\Service\NodeAccess
   */
  protected $nodeAccess;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal configuration factory service.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\permissions_by_term\Service\AccessStorage $accessStorage
   *   The Permissions by Term module access storage service.
   *
   * @param \Drupal\permissions_by_term\Service\NodeAccess $nodeAccess
   *   The Permissions by Term module node access service.
   */
  public function __construct(
    ConfigFactoryInterface  $configFactory,
    LoggerInterface         $loggerChannel,
    AccessStorage           $accessStorage,
    NodeAccess              $nodeAccess
  ) {
    $this->loggerChannel  = $loggerChannel;
    $this->accessStorage  = $accessStorage;
    $this->nodeAccess     = $nodeAccess;

    $this->disabledNodeAccessRecords = $configFactory
      ->get('permissions_by_term.settings')->get('disable_node_access_records');
  }

  /**
   * {@inheritdoc}
   */
  public function grantUserProductEpisodeTiers(
    AccountInterface $user, ProductInterface $product
  ): void {

    // Bail if this is not an authenticated user and log an error.
    if ($user->isAnonymous()) {
      $this->loggerChannel->log(
        RfcLogLevel::ERROR,
        'User is anonymous - this could indicate a misconfiguration of commerce functionality or user permissions.'
      );

      return;
    }

    // Bail if the user is anonymous or the provided product does not have the
    // episode tiers field.
    if (!$product->hasField('field_episode_tier')) {

      $this->loggerChannel->log(
        RfcLogLevel::ERROR,
        'Product does not have the episode tiers field:<pre>@product</pre>',
        ['@product' => \print_r($product, true)]
      );

      return;

    }

    /** @var string[] Zero or more permissions that the user has before applying the product's episode tiers. Note that values are single strings containing term IDs, user IDs, and language codes all concatenated together. */
    $previousPermissions = $this->accessStorage
      ->getAllTermPermissionsByUserId($user->id());

    /** @var int[] Term IDs (tids) to assign the user as permissions. */
    $tids = [];

    foreach ($product->get('field_episode_tier') as $fieldItem) {

      /** @var string|null The term ID (tid) for this single episode tier or null if not set. */
      $tid = $fieldItem->target_id;

      // Skip this term ID (tid) if we got null or if it's already in $tids.
      if (empty($tid) || \in_array((int) $tid, $tids)) {
        continue;
      }

      $tids[] = (int) $tid;

    }

    // Add all $tids to the user as term permissions.
    foreach ($tids as $tid) {
      $this->accessStorage->addTermPermissionsByUserIds(
        [$user->id()], $tid, $user->getPreferredLangcode()
      );
    }

    /** @var string[] Zero or more permissions that the user has after purchase. Note that values are single strings containing term IDs, user IDs, and language codes all concatenated together. */
    $updatedPermissions = $this->accessStorage
      ->getAllTermPermissionsByUserId($user->id());

    // Rebuild node permissions if needed.
    if (
      !$this->disabledNodeAccessRecords &&
      // Note that the order of the parameters to \array_diff() matters, as the
      // first parameter is what the other arrays are checked against. Since we
      // only expect a product to add permissions and not remove any, we only
      // check against the post-purchase permissions.
      !empty(\array_diff($updatedPermissions, $previousPermissions))
    ) {
      $this->nodeAccess->rebuildAccess($user->id());
    }

  }

}
