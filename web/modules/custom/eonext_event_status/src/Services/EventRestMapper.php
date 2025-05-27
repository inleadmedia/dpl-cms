<?php

namespace Drupal\eonext_event_status\Services;

use DanskernesDigitaleBibliotek\CMS\Api\Model\EventPATCHRequestExternalData;
use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInnerAddress;
use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInnerDateTime;
use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInnerImage;
use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInnerSeries;
use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInnerTicketCategoriesInner;
use DanskernesDigitaleBibliotek\CMS\Api\Model\EventsGET200ResponseInnerTicketCategoriesInnerPrice;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\dpl_event\Entity\EventInstance;
use Drupal\dpl_event\Form\SettingsForm;
use Drupal\dpl_event\Services\EventRestMapper as EventsRestMapperDefault;
use Drupal\eonext_event_status\Model\EventsGET200ResponseInner;
use Drupal\file\FileInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\recurring_events\Entity\EventSeries;

/**
 * Provides a service to map event data for REST API.
 */
class EventRestMapper extends EventsRestMapperDefault {

  /**
   * Constructor.
   */
  public function __construct(
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected ConfigFactoryInterface $configFactory,
    protected RibbonService $ribbonService,
  ) {
    parent::__construct($fileUrlGenerator, $configFactory);
  }

  /**
   * {@inheritDoc}
   */
  public function getResponse(EventInstance $event_instance): EventsGET200ResponseInner {
    $this->event = $event_instance;

    $response = new EventsGET200ResponseInner([
      'title' => $this->getValue('title'),
      'uuid' => $this->event->uuid(),
      'url' => $this->event->toUrl()->setAbsolute(TRUE)->toString(TRUE)->getGeneratedUrl(),
      'ticketManagerRelevance' => !empty($this->getSeriesValue('field_relevant_ticket_manager')),
      'description' => $this->getValue('event_description'),
      'body' => $this->event->getDescription(),
      'state' => $this->event->getState()?->value,
      'image' => $this->getImage(),
      'branches' => $this->getBranches(),
      'address' => $this->getAddress(),
      'tags' => $this->getTags(),
      'partners' => $this->getMultiValue('event_partners'),
      'ticketCapacity' => $this->getValue('event_ticket_capacity'),
      'ticketCategories' => $this->getTicketCategories(),
      'createdAt' => $this->getDateField('created'),
      'updatedAt' => $this->event->getUpdatedDate(),
      'dateTime' => $this->getDate(),
      'externalData' => $this->getExternalData(),
      'screenNames' => $this->event->getScreenNames(),
      'ribbon' => $this->ribbonService->getRibbon(
        $this->event->get('eventseries_id')->entity,
        $this->event,
      ),
    ]);

    $series = $this->event->getEventSeries();

    if ($series instanceof EventSeries) {
      $response->setSeries(new EventsGET200ResponseInnerSeries([
        'uuid' => $series->uuid(),
      ]));
    }
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  private function getValue(string $field_name): ?string {

    $field = $this->event->getField($field_name);

    if (!($field instanceof FieldItemListInterface)) {
      return NULL;
    }

    return $field->getString();
  }

  /**
   * {@inheritDoc}
   */
  private function getSeriesValue(string $field_name): ?string {
    $series = $this->event->getEventSeries();

    if (!($series instanceof EventSeries) || !$series->hasField($field_name)) {
      return NULL;
    }

    $field = $series->get($field_name);

    if (!($field instanceof FieldItemListInterface)) {
      return NULL;
    }

    return $field->getString();
  }

  /**
   * {@inheritDoc}
   */
  private function getImage(): ?EventsGET200ResponseInnerImage {
    $media_field = $this->event->getField('event_image');

    if (!($media_field instanceof FieldItemListInterface)) {
      return NULL;
    }

    $media = $media_field->referencedEntities()[0] ?? NULL;
    $file_field_name = 'field_media_image';

    if (!($media instanceof MediaInterface) || !$media->hasField($file_field_name)) {
      return NULL;
    }

    $file_field = $media->get($file_field_name);
    $file = $file_field->referencedEntities()[0] ?? NULL;

    if (!($file instanceof FileInterface)) {
      return NULL;
    }

    $file_uri = $file->getFileUri();

    $url = !empty($file_uri) ? $this->fileUrlGenerator->generateAbsoluteString($file_uri) : NULL;

    return new EventsGET200ResponseInnerImage(['url' => $url]);
  }

  /**
   * {@inheritDoc}
   */
  private function getBranches(): array {
    $names = [];

    $branches = $this->event->getBranches() ?? [];

    foreach ($branches as $branch) {
      $label = $branch->getTitle();

      if (!empty($label)) {
        $names[] = $label;
      }
    }

    return $names;
  }

  /**
   * {@inheritDoc}
   */
  private function getAddress(): EventsGET200ResponseInnerAddress {
    // Loading the field, and rendering it, to let the BranchAddressFormatter
    // do the work of looking up a possible branch.
    $rendered = $this->event->get('event_address')->view('full');

    $zip = $rendered[0]['postal_code']['#value'] ?? NULL;
    $address_1 = $rendered[0]['address_line1']['#value'] ?? NULL;
    $address_2 = $rendered[0]['address_line2']['#value'] ?? NULL;

    $street = "$address_1 $address_2";

    if (empty($address_1) && empty($address_2)) {
      $street = NULL;
    }

    $address = new EventsGET200ResponseInnerAddress();
    $address->setLocation($this->getValue('event_place'));
    $address->setStreet($street);
    $address->setZipCode(!empty($zip) ? intval($zip) : NULL);
    $address->setCity($rendered[0]['locality']['#value'] ?? NULL);
    $address->setCountry($rendered[0]['country_code']['#value'] ?? NULL);

    return $address;
  }

  /**
   * {@inheritDoc}
   */
  private function getTags(): array {
    $names = [];

    /** @var \Drupal\taxonomy\TermInterface[] $tags */
    $tags = $this->event->get('event_tags')->referencedEntities();

    foreach ($tags as $tag) {
      $names[] = $tag->getName();
    }

    return $names;
  }

  /**
   * {@inheritDoc}
   */
  private function getMultiValue(string $field_name): array {
    $field = $this->event->getField($field_name);

    if (!($field instanceof FieldItemListInterface)) {
      return [];
    }

    $values = $field->getValue();

    // Turning the value keys into a simple, one-level array of strings.
    return array_column($values, 'value');
  }

  /**
   * {@inheritDoc}
   */
  private function getTicketCategories(): array {

    $categories = [];

    $field = $this->event->getField('event_ticket_categories');

    if (!($field instanceof FieldItemListInterface)) {
      return $categories;
    }

    $paragraphs = $field->referencedEntities();
    foreach ($paragraphs as $paragraph) {
      if (!($paragraph instanceof ParagraphInterface)) {
        continue;
      }

      $title = $paragraph->hasField('field_ticket_category_name') ? $paragraph->get('field_ticket_category_name')->getString() : NULL;
      $price_value = $paragraph->hasField('field_ticket_category_price') ? $paragraph->get('field_ticket_category_price')->getString() : 0;

      if (empty($title)) {
        continue;
      }

      $config = $this->configFactory->get(SettingsForm::CONFIG_NAME);

      $price = new EventsGET200ResponseInnerTicketCategoriesInnerPrice([
        'value' => intval($price_value),
        'currency' => $config->get('price_currency') ?? 'DKK',
      ]);

      $categories[] = new EventsGET200ResponseInnerTicketCategoriesInner([
        'uuid' => $paragraph->uuid(),
        'title' => $title,
        'price' => $price,
      ]);
    }

    return $categories;
  }

  /**
   * {@inheritDoc}
   */
  private function getDateField(string $field_name): ?\DateTime {
    $timestamp = $this->getValue($field_name);

    if (empty($timestamp)) {
      return NULL;
    }

    $date = new \DateTime();
    $date->setTimestamp(intval($timestamp));

    return $date;
  }

  /**
   * {@inheritDoc}
   */
  private function getDate(): ?EventsGET200ResponseInnerDateTime {
    $field = $this->event->getField('date');

    if (!($field instanceof FieldItemListInterface)) {
      return NULL;
    }

    $value = $field->getValue();

    $start = $value[0]['value'] ?? NULL;
    $end = $value[0]['end_value'] ?? NULL;

    if (empty($start) || empty($end)) {
      return NULL;
    }

    $site_timezone = new \DateTimeZone(date_default_timezone_get());

    $date_start = new \DateTime($start, new \DateTimeZone('UTC'));
    $date_start->setTimezone($site_timezone);
    $date_end = new \DateTime($end, new \DateTimeZone('UTC'));
    $date_end->setTimezone($site_timezone);

    return new EventsGET200ResponseInnerDateTime([
      'start' => $date_start,
      'end' => $date_end,
    ]);
  }

  /**
   * {@inheritDoc}
   */
  private function getExternalData(): EventPATCHRequestExternalData {
    return new EventPATCHRequestExternalData([
      'adminUrl' => $this->getValue('field_external_admin_link'),
      'url' => $this->getValue('event_link'),
    ]);
  }

}
