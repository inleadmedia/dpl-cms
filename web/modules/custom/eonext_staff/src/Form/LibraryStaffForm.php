<?php

declare(strict_types=1);

namespace Drupal\eonext_staff\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eonext_staff\Entity\LibraryStaff;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for the library staff entity edit forms.
 */
final class LibraryStaffForm extends ContentEntityForm {

//  protected $entityTypeManager;

  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

//    $instance->entityTypeManager = $container->get('entity_type.manager');

    return $instance;
  }

  public function form(array $form, FormStateInterface $form_state, UserInterface $user = NULL): array {
    return parent::form($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   * {@inheritDoc}
   *
   * The routing system seems not to be able to pass params to a route
   * that makes use of '_entity_form'. So load the user entity here and
   * link it with the current staff entity.
   */
  public function setEntity(EntityInterface $entity) {
    $user = $this->getEntityFromRouteMatch($this->getRouteMatch(), 'user');
    $entityByUser = $this->entityTypeManager
      ->getStorage('eonext_library_staff')
      ->loadByProperties(['uid' => $user->id()]);

    if (!empty($entityByUser)) {
      $entity = reset($entityByUser);
    }
    else {
      $entity->setUserId((int) $user->id());
    }

    return parent::setEntity($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $user = $this->getEntityFromRouteMatch($this->getRouteMatch(), 'user');

    $message_args = ['%label' => $user->label()];
    $logger_args = [
      '%label' => $user->label(),
    ];

    switch ($result) {
      case SAVED_NEW:
      case SAVED_UPDATED:
        $this->messenger()->addStatus($this->t('Staff information for user %label has been updated.', $message_args));
        $this->logger('eonext_staff')->notice('Staff information for user %label has been updated.', $logger_args);
        break;

      default:
        throw new \LogicException('Could not save staff for user.');
    }

    $form_state->setRedirectUrl($user->toUrl('edit-form'));

    return $result;
  }

}
