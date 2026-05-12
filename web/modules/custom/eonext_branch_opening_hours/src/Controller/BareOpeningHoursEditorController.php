<?php

declare(strict_types=1);

namespace Drupal\eonext_branch_opening_hours\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\dpl_react_apps\Controller\DplReactAppsController;
use Drupal\file\FileInterface;
use Drupal\node\NodeInterface;

/**
 * Defines OpeningHoursEditorController class.
 */
final class BareOpeningHoursEditorController extends ControllerBase {

  /**
   * Display the opening hours app.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The branch node.
   *
   * @return mixed[]
   *   The app render array.
   */
  public function content(NodeInterface $node): array {
    if ($node->getType() !== 'branch') {
      return [];
    }

    $build = [
      '#theme' => 'dpl_react_app',
      '#name' => 'opening-hours',
      '#data' => [
        'branch-id' => $node->id(),
        'branch-title' => $node->getTitle(),
        'opening-hours-bare' => 1,
        'class' => ['opening-hours--bare'],
        'opening-hours-heading-text' => t('Opening Hours', [], ['context' => 'Opening Hours']),
        'show-opening-hours-for-week-text' => t('Show opening hours for week', [], ['context' => 'Opening Hours']),
        'week-text' => t('Week', [], ['context' => 'Opening Hours']),
        'library-is-closed-text' => t('The library is closed this day', [], ['context' => 'Opening Hours']),
      ] + DplReactAppsController::externalApiBaseUrls(),
      '#attributes' => [
        'class' => ['opening-hours--bare'],
      ],
      '#cache' => [
        'tags' => ['config:eonext_branch_opening_hours.settings'],
        'contexts' => ['url.site'],
      ],
    ];

    $fid = $this->config('eonext_branch_opening_hours.settings')->get('logo');
    if (!empty($fid)) {
      $file = $this->entityTypeManager()->getStorage('file')->load($fid);
      if ($file instanceof FileInterface && _eonext_branch_opening_hours_file_uri_exists($file)) {
        $build['#cache']['tags'][] = 'file:' . $file->id();
      }
    }

    return $build;
  }

  /**
   * Check access for a specific library node.
   *
   * @param int $node
   *   The node ID.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(int $node): AccessResult {
    $nodeStorage = $this->entityTypeManager()->getStorage('node');
    $nodeEntity = $nodeStorage->load($node);

    if ($nodeEntity instanceof NodeInterface && $nodeEntity->getType() === 'branch') {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
