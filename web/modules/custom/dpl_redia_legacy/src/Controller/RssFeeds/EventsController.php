<?php

namespace Drupal\dpl_redia_legacy\Controller\RssFeeds;

use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\dpl_redia_legacy\RediaEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function Safe\strtotime;

/**
 * Building a Redia-legacy RSS feed, showing eventinstances.
 */
class EventsController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('file_url_generator'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Getting the RSS/XML feed of the items.
   */
  public function getFeed(Request $request): CacheableResponse {
    // Disable the feed while we wait for validation by Redia.
    return new CacheableResponse("Feed disabled temporarily", Response::HTTP_NOT_FOUND);
    /*
    $items = $this->getItems();

    $rss_content = $this->buildRss($items, $request);

    $response = new CacheableResponse();
    $response->setContent($rss_content);

    // Create cache metadata.
    $cache_metadata = new CacheableMetadata();
    $cache_metadata->setCacheTags(['eventinstance_list', 'eventseries_list']);

    // Add cache metadata to the response.
    $response->addCacheableDependency($cache_metadata);

    $response->headers->set('Content-Type', 'application/rss+xml');
    return $response;
     */
  }

  /**
   * Loading events, and turning it into a simple array of relevant values.
   *
   * @return \Drupal\dpl_redia_legacy\RediaEvent[]
   *   An array of necessary item fields, used in buildRss().
   */
  protected function getItems(): array {

    $storage = $this->entityTypeManager()->getStorage('eventinstance');
    $query = $storage->getQuery()
      ->condition('status', TRUE)
      ->condition('date.value', strtotime('today'), '>=')
      ->accessCheck(TRUE)
      ->sort('date.value');
    $ids = $query->execute();

    /** @var \Drupal\recurring_events\Entity\EventInstance[] $events */
    $events = $storage->loadMultiple($ids);

    $items = [];

    foreach ($events as $event) {
      $items[] = new RediaEvent($event);
    }

    return $items;
  }

  /**
   * Building the actual RSS feed, from the items and site information.
   *
   * @param \Drupal\dpl_redia_legacy\RediaEvent[] $items
   *   See $this->getItems();.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request, for looking up the current site info.
   */
  protected function buildRss(array $items, Request $request): string {
    $config = $this->config('system.site');
    $site_title = $config->get('name');
    $site_url = $request->getSchemeAndHttpHost();
    $feed_url = Url::fromRoute('dpl_redia_legacy.events');
    $feed_url->setAbsolute();
    $feed_url = $feed_url->toString();

    $date = $this->dateFormatter->format(time(), 'custom', 'r');

    $rss_feed = <<<RSS
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xml:base="$site_url" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/" xmlns:content-rss="http://xml.redia.dk/rss">
  <channel>
    <title>$site_title</title>
    <link>$site_url</link>
    <atom:link rel="self" href="$feed_url" />
    <language>da</language>
    <pubDate>$date</pubDate>
    <lastBuildDate>$date</lastBuildDate>
RSS;

    foreach ($items as $item) {
      $rss_feed .= <<<ITEM
    <item>
      <title>{$item->title}</title>
      <description>{$item->description}</description>
      <author>{$item->author}</author>
      <guid isPermaLink="false">{$item->id}</guid>
      <pubDate>{$item->date}</pubDate>
      <source url="$feed_url">$site_title</source>
      <media:content url="{$item->media?->url}" fileSize="{$item->media?->size}" type="{$item->media?->type}" contentmedium="{$item->media?->medium}" width="{$item->media?->width}" height="{$item->media?->height}">
        <media:hash algo="md5">{$item->media?->md5}</media:hash>
      </media:content>
      <media:thumbnail url="{$item->mediaThumbnail?->url}" width="{$item->mediaThumbnail?->width}" height="{$item->mediaThumbnail?->height}" />
      <content-rss:subheadline>{$item->subtitle}</content-rss:subheadline>
      <content-rss:arrangement-starttime>{$item->startTime}</content-rss:arrangement-starttime>
      <content-rss:arrangement-endtime>{$item->endTime}</content-rss:arrangement-endtime>
      <content-rss:arrangement-location>{$item->branch?->label()}</content-rss:arrangement-location>
      <content-rss:library-id>{$item->branch?->id()}</content-rss:library-id>
      <content-rss:promoted>{$item->promoted}</content-rss:promoted>
    </item>
ITEM;
    }

    $rss_feed .= <<<RSS
  </channel>
</rss>
RSS;

    return $rss_feed;

  }

}
