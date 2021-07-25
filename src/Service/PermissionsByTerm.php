<?php

namespace Drupal\omnipedia_commerce\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\omnipedia_commerce\Service\PermissionsByTermInterface;
use Drupal\permissions_by_term\Service\AccessStorage;
use Drupal\permissions_by_term\Service\NodeAccess;

/**
 * The Omnipedia Permissions by Term helper service.
 *
 * @see \permissions_by_term_user_form_submit()
 *   Much of the code relating to Permissions by Term is adapted from this and
 *   altered to use dependency injection.
 */
class PermissionsByTerm implements PermissionsByTermInterface {

  /**
   * Whether node access records are disabled in the Permissions by Term module.
   *
   * @var bool
   */
  protected $disabledNodeAccessRecords;

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
   * The Drupal user entity storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal configuration factory service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type manager.
   *
   * @param \Drupal\permissions_by_term\Service\AccessStorage $accessStorage
   *   The Permissions by Term module access storage service.
   *
   * @param \Drupal\permissions_by_term\Service\NodeAccess $nodeAccess
   *   The Permissions by Term module node access service.
   */
  public function __construct(
    ConfigFactoryInterface      $configFactory,
    EntityTypeManagerInterface  $entityTypeManager,
    AccessStorage               $accessStorage,
    NodeAccess                  $nodeAccess
  ) {
    $this->accessStorage  = $accessStorage;
    $this->nodeAccess     = $nodeAccess;
    $this->userStorage    = $entityTypeManager->getStorage('user');

    $this->disabledNodeAccessRecords = $configFactory
      ->get('permissions_by_term.settings')->get('disable_node_access_records');
  }

  /**
   * {@inheritdoc}
   */
  public function userPermissionsUpdateAccessResult(
    ?AccountInterface $userPerformingUpdate,
    ?AccountInterface $userToUpdate
  ): AccessResultInterface {

    return $userToUpdate->access('update', $userPerformingUpdate, true)->andIf(
      // Permissions by Term does not have a specific permission for updating
      // a user's permission terms, so this is the closest thing.
      AccessResult::allowedIfHasPermission(
        $userPerformingUpdate, 'show term permissions on user edit page'
      )

    );
  }

  /**
   * {@inheritdoc}
   */
  public function addUserTerms(
    string $uid, array $tids, bool $rebuild = true
  ): void {

    /** @var \Drupal\user\UserInterface|null */
    $user = $this->userStorage->load($uid);

    if (!\is_object($user)) {
      return;
    }

    /** @var string */
    $langCode = $user->getPreferredLangcode();

    /** @var string[] Zero or more permissions that the user has before applying the product's episode tiers. Note that values are single strings containing term IDs, user IDs, and language codes all concatenated together. */
    $previousPermissions = $this->accessStorage
      ->getAllTermPermissionsByUserId($uid);

    // Add all $tids to the user as term permissions.
    foreach ($tids as $tid) {
      $this->accessStorage->addTermPermissionsByUserIds(
        [$uid], $tid, $langCode
      );
    }

    /** @var string[] Zero or more permissions that the user has after purchase. Note that values are single strings containing term IDs, user IDs, and language codes all concatenated together. */
    $updatedPermissions = $this->accessStorage
      ->getAllTermPermissionsByUserId($uid);

    // Rebuild node permissions if told to do so.
    if (
      $rebuild === true &&
      // Note that the order of the parameters to \array_diff() matters, as the
      // first parameter is what the other arrays are checked against. Since we
      // only expect a product to add permissions and not remove any, we only
      // check against the post-purchase permissions.
      !empty(\array_diff($updatedPermissions, $previousPermissions))
    ) {
      $this->rebuildNodeAccess($uid);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function rebuildNodeAccess(string $uid): void {

    if ($this->disabledNodeAccessRecords === true) {
      return;
    }

    $this->nodeAccess->rebuildAccess($uid);

  }

}
