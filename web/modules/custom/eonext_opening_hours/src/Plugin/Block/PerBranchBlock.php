<?php

declare(strict_types=1);

namespace Drupal\eonext_opening_hours\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\dpl_opening_hours\Model\OpeningHoursRepository;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an opening hours per branch block.
 *
 * @Block(
 *   id = "eonext_opening_hours_per_branch",
 *   admin_label = @Translation("Opening hours per branch"),
 *   category = @Translation("Custom"),
 * )
 */
final class PerBranchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a PerBranchBlock block object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\dpl_opening_hours\Model\OpeningHoursRepository $openingHoursRepository
   *   The opening hours repository.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private EntityTypeManagerInterface $entityTypeManager,
    private OpeningHoursRepository $openingHoursRepository,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('dpl_opening_hours.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'show_empty' => $this->t('Show empty branches'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {

    $branches = $this
      ->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'branch',
        'status' => 1,
      ]);

    $form['show_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show empty branches'),
      '#default_value' => $this->configuration['show_empty'],
    ];

    $form['available_branches'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Available branches'),
      '#options' => array_map(function ($branch) {
        return $branch->label();
      }, $branches),
      '#default_value' => $this->configuration['available_branches'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['show_empty'] = $form_state->getValue('show_empty');
    $this->configuration['available_branches'] = array_filter($form_state->getValue('available_branches'));
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {

    $branches = $this
      ->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'branch',
        'status' => 1,
      ]);

    $items = [];

    $availableBranches = $this->configuration['available_branches'];

    // Filter branches by available branches configuration.
    if (!empty($availableBranches)) {
      $branches = array_filter($branches, function ($branch) use ($availableBranches) {
        return in_array($branch->id(), $availableBranches);
      });
    }

    foreach ($branches as $branch) {
      $item = $this->formatItem($branch);
      // We are returning FALSE if the branch has no opening hours.
      if (!$item) {
        continue;
      }
      $items[] = $item;
    }

    return [
      '#theme' => 'eonext_opening_hours',
      '#header_items' => [
        $this->t('Branch'),
        $this->t('Opening hours'),
      ],
      '#items' => $items,
      '#cache' => [
        'max-age' => 0,
      ],
      '#attached' => [
        'library' => [
          'eonext_opening_hours/general',
        ],
      ],
    ];
  }

  /**
   * Format the opening hours item.
   *
   * @param \Drupal\node\NodeInterface $branch
   *   The branch node.
   *
   * @return array|bool
   *   The formatted item.
   */
  private function formatItem(NodeInterface $branch): array|bool {

    $openingHours = $this
      ->openingHoursRepository
      ->loadMultiple([$branch->id()], new \DateTime(), new \DateTime());

    if (empty($openingHours) && $this->configuration['show_empty'] != TRUE) {
      return FALSE;
    }

    // Order openingHours by startTime.
    usort($openingHours, function ($a, $b) {
      return $a->startTime->getTimestamp() - $b->startTime->getTimestamp();
    });

    return [
      'id' => $branch->id(),
      'branch' => $branch->label(),
      'opening_hours' => $openingHours,
    ];
  }

}
