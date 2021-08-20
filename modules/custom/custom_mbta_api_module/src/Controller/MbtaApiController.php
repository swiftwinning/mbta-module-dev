<?php

namespace Drupal\custom_mbta_api_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * @package Drupal\custom_mbta_api_module\Controller
 */
class MbtaApiController extends ControllerBase {

  /**
   * @var GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * @var String
   */
  protected $baseUri = 'https://api-v3.mbta.com';

  /**
   * MbtaApiController constructor function.
   * @param \Drupal\http_client_manager\HttpClientInterface $http_client
   */
  public function __construct() {
    $this->httpClient = \Drupal::httpClient();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  // This function will become necessary for allowing use by other modules.
  // /**
  //  * @return \Drupal\http_client_manager\HttpClientInterface
  //  */
  // public function getClient() {
  //   return $this->httpClient;
  // }

  /**
   * Returns Table of all MBTA routes with official colors.
   *   Note: 'routesWithCss()' & 'routesWithLinks()' do not use
   *   'sort=description'/'sort=-description' options.
   *   Default sort order may be preferable. Discuss sorting options with stakeholders.
   */
  public function routesWithCss() {
    try {
      $request = $this->httpClient->request(
        'GET',
        $this->baseUri.'/routes?fields[route]=color,text_color,long_name',
      );
    } catch (RequestException $e) {
      //  Do proper error handling.
      return $e;
    }
    $routeInfo = $request->getBody()->getContents();
    $routeArray = json_decode($routeInfo, true)['data'];

    $stringOfRowElements = '';

    foreach ($routeArray as $route) {
      $color = $route['attributes']['color'];
      $text_color = $route['attributes']['text_color'];
      $long_name = $route['attributes']['long_name'];

      $newFormattedRow = '<tr class="color-'.$color.' text-color-'.$text_color.'">
        <td>'.$long_name.'</td>
      </tr>';
      $stringOfRowElements = $stringOfRowElements.$newFormattedRow;
    }

    $build[] = [
      '#markup' => '<table>'.$stringOfRowElements.'</table>',
      '#attached' => [
        'library' => [
          'custom_mbta_api_module/custom_mbta_api_module.mbta-route-colors',
        ],
      ],
    ];
    return ($build);
  }

  /**
   * Returns Table of all MBTA routes, with links from each route to its schedule.
   *   Note: 'routesWithCss()' & 'routesWithLinks()' do not use
   *   'sort=description'/'sort=-description' options.
   *   Default sort order may be preferable. Discuss sorting options with stakeholders.
   */
  public function routesWithLinks() {
    try {
      $request = $this->httpClient->request(
        'GET',
        $this->baseUri.'/routes?fields[route]=long_name',
      );
    } catch (RequestException $e) {
      //  Do proper error handling.
      return $e;
    }
    $response = $request->getBody()->getContents();
    $routeArray = json_decode($response, true)['data'];

    $arrayOfRowElements = [];

    // Refactor this!!! Extremely inefficient to send new request for each stop
    //   on the route! If the API doesn't provide a more direct way to formulate
    //   a request for the same data, the human-readable names of stops need to
    //   be cached in app or stored in db
    foreach ($routeArray as $route) {
      $long_name = $route['attributes']['long_name'];
      $idString = $route['id'];
      $url = Url::fromRoute(
        'custom_mbta_api_module.readable_schedule_table', ['id' => $idString]
      );
      $link_to_route = [
        \Drupal::l(t($long_name), $url),
      ];
      $arrayOfRowElements[] = $link_to_route;
    }

    $build[] = [
      '#type' => 'table',
      '#header' => [$this->t('Select a route')],
      '#rows' => $arrayOfRowElements,
    ];
    return ($build);
  }

  public function readableScheduleTable($id) {
    try {
      $request = $this->httpClient->request(
        'GET',
        //  Consider defaulting to 'Schedules' when 'Predictions' are unavailable
        //  For example off service hours.
        //$this->baseUri.'/predictions?filter[route]='.$id.'&page[limit]=25',
        $this->baseUri.'/schedules?filter[route]='.$id.'&page[limit]=25',
      );
    } catch (RequestException $e) {
      //  Do proper error handling.
      return $e;
    }
    $response = $request->getBody()->getContents();
    $scheduleArray = json_decode($response, true)['data'];

    $rows = [];

    foreach ($scheduleArray as $stop) {
      $arrival_time = $stop['attributes']['arrival_time'];
      $departure_time = $stop['attributes']['departure_time'];
      $stopId = $stop['relationships']['stop']['data']['id'];

      //  There must be a better way to pre-load human readable names with
      //    the original API response.
      //  Or - to cache an associative array of [stopId => stopName] in our app.
      //  This is clearly wrong, too many API calls.
      try {
        $request = $this->httpClient->request(
          'GET',
          $this->baseUri.'/stops/'.$stopId.'?fields[stop]=name',
        );
      } catch (RequestException $e) {
        //  Do proper error handling.
        return $e;
      }
      $response = $request->getBody()->getContents();
      $stopName = json_decode($response, true)['data']['attributes']['name'];
      $timestamp = $departure_time ? $departure_time : $arrival_time;
      $time = $this->parseIsoFormatToReadableTime($timestamp);
      $rows[] = [$this->t($stopName), $this->t($time)];
    }

    $build[] = [
      '#type' => 'page',
      'content' => [
        '#type' => 'table',
        '#header' => [$this->t('Stop Name'), $this->t('Time')],
        '#rows' => $rows,
      ],
    ];
    return $build;
  }

  /*
   * Private helper method takes ISO8601 Formatted string and returns
   *   human-readable time, hh:mm, 12-hour formatted.
   */
  private function parseIsoFormatToReadableTime(String $isoFormattedTimestamp) {
    $hours = intval(substr($isoFormattedTimestamp, 11, 2));
    $minutes = substr($isoFormattedTimestamp, 14, 2);
    $operator = substr($isoFormattedTimestamp, 19, 1);
    $offset = intval(substr($isoFormattedTimestamp, 20, 2));
    if ($offset) {
      if ($operator == '-') {
        $hours -= $offset;
      } elseif ($operator == '+'){
        $hours += $offset;
      }
    }
    if ($hours <= 0) {
      $hours += 12;
    } elseif ($hours >= 13) {
      $hours = $hours % 12;
    }
    return $hours.':'.$minutes;
  }

}
?>
