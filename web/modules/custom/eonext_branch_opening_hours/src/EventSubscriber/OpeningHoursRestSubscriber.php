<?php

namespace Drupal\eonext_branch_opening_hours\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Subscribes to REST requests for dpl_opening_hours.
 */
final class OpeningHoursRestSubscriber implements EventSubscriberInterface {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Constructs a new OpeningHoursRestSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   The HTTP client.
   */
  public function __construct(ConfigFactoryInterface $configFactory, ClientInterface $httpClient) {
    $this->configFactory = $configFactory;
    $this->httpClient = $httpClient;
  }

  /**
   * Reacts to REST POST/PATCH/DELETE for opening hours.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');

    $watched_routes = [
      'rest.dpl_opening_hours_create.POST' => 'created',
      'rest.dpl_opening_hours_update.PATCH' => 'updated',
      'rest.dpl_opening_hours_delete.DELETE' => 'deleted',
    ];

    if (!isset($watched_routes[$route_name])) {
      return;
    }

    $config = $this->configFactory->get('eonext_branch_opening_hours.settings');
    if (!$config->get('enabled')) {
      return;
    }

    $payload = json_decode($request->getContent(), TRUE);
    if (!is_array($payload)) {
      return;
    }

    $domain = trim($config->get('domain'), '/');
    $consumer_hash = $config->get('consumer_hash');
    $url = "https://{$domain}/{$consumer_hash}/update-google-business/opening-hours";

    \Drupal::logger('eonext_branch_opening_hours')->debug(
      'type @type; payload: @payload',
      [
        '@type' => $watched_routes[$route_name],
        '@payload' => print_r($payload, 1),
      ]
    );

    try {
      $this->httpClient->post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'json' => [
          'type' => $watched_routes[$route_name],
          'payload' => $payload,
        ],
      ]);
    }
    catch (\Throwable $e) {
      \Drupal::logger('eonext_branch_opening_hours')->error(
        'Failed to notify external service about opening hours @type: @message',
        [
          '@type' => $watched_routes[$route_name],
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest'],
    ];
  }

}
