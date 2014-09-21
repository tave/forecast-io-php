<?php
namespace Forecast;
class Service {
  const ENDPOINT = 'https://api.forecast.io/forecast/';

  /**
   * GuzzleHttp Client
   * @var GuzzleHttp
   */
  protected $httpClient = null;

  /**
   * API key to use
   * @var string
   */
  protected $accessKey = null;

  /**
   * Timeout in seconds
   * @var integer
   */
  protected $timeout = 40;

  /**
   * Connect timeout in seconds
   * @var integer
   */
  protected $connectTimeout = 10;

  /**
   * Last error that occured
   * @var string
   */
  protected $lastError = null;

  /**
   * Last request if an error occured
   * @var string
   */
  protected $lastRequest = null;


  /**
   * Forecast\Service constructor
   *
   * @param string $key API key
   */
  public function __construct($key = null)
  {
    if (empty($key) && defined('FORECAST_API_KEY')) {
      $this->accessKey(FORECAST_API_KEY);
    }
    else if (strlen($key)) {
      $this->accessKey($key);
    }
  }


  /**
   * Get/Set the API Key
   *
   * @param string $key
   * @return mixed
   */
  public function accessKey($key = null)
  {
    if (count(func_get_args()) > 0) {
      $this->accessKey = trim($key) ?: null;
      return $this;
    }

    return $this->accessKey;
  }


  /**
   * Get/Set the overall timeout
   *
   * @param int $seconds
   * @return mixed
   */
  public function timeout($seconds = 0)
  {
    if (count(func_get_args()) > 0) {
      $this->timeout = intval($seconds);
      return $this;
    }

    return $this->timeout;
  }


  /**
   * Get/Set the connection timeout
   *
   * @param int $seconds
   * @return mixed
   */
  public function connectTimeout($seconds = 0)
  {
    if (count(func_get_args()) > 0) {
      $this->connectTimeout = intval($seconds);

      // We don't want connect timeout to be greater than the entire connect-response timeout
      if ($this->connectTimeout == 0) {
        // Wait for-ev-er
        $this->timeout(0);
      }
      else if ($this->connectTimeout > $this->timeout) {
        // Just add one to the connection timeout
        $this->timeout($this->connectTimeout + 1);
      }

      return $this;
    }

    return $this->connectTimeout;
  }


  /**
   * The last error
   *
   * @return string
   */
  public function lastError()
  {
    return $this->lastError;
  }


  /**
   * Last request if it failed
   * @return [type] [description]
   */
  public function lastRequest()
  {
    return $this->lastRequest;
  }


  /**
   * Last response if it failed
   *
   * @return string
   */
  public function lastResponse()
  {
    return $this->lastResponse;
  }


  /**
   * Fetch forecast data from the API
   *
   * @param  decimal $latitude
   * @param  decimal $longitude
   * @param  mixed (int) $time or (array) $options
   * @param  array $options
   * @return array
   */
  public function fetch($latitude, $longitude, $time = null, $options = [])
  {
    $this->lastError = null;
    $this->lastRequest = null;
    $this->lastResponse = null;

    if (empty($this->accessKey)) {
      $this->lastError = "No access key set.  Please set an access key before attempting to fetch forecast data.";
    }

    if ( ! $this->httpClient) {
      $this->httpClient = new \GuzzleHttp\Client();
    }

    $endpoint = rtrim(self::ENDPOINT,'/')
      . '/'
      . $this->accessKey
      . '/'
      . $latitude
      . ','
      . $longitude;

    if (is_array($time)) {
      $options = $time;
      $time = null;
    }

    if ($time) {
      $endpoint .= ',' . $time;
    }

    if ($options) {
      $endpoint .= '?' . http_build_query($options);
    }

    try {
      $Response = $this->httpClient->get($endpoint, [
        'allow_redirects' => true,
        'timeout' => $this->timeout,
        'connect_timeout' => $this->connectTimeout,
        'headers' => [ 'User-Agent' => 'Tave/ForecastIOWrapper' ]
      ]);

      return $Response->json();
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      $this->lastRequest = $e->getRequest();
      $this->lastResponse = $e->hasResponse() ? $e->getResponse() : null;
      $this->lastError = $e->getMessage();
      return false;
    }
  }

  public function batch($requests, $options = [])
  {

    $this->lastError = null;
    $this->lastRequest = null;
    $this->lastResponse = null;

    if (empty($this->accessKey)) {
      $this->lastError = "No access key set.  Please set an access key before attempting to fetch forecast data.";
    }

    if ( ! $this->httpClient) {
      $this->httpClient = new \GuzzleHttp\Client();
    }

    $baseURL = rtrim(self::ENDPOINT,'/') . '/' . $this->accessKey . '/';
    $httpRequests = [];
    foreach ( $requests as $idx => $request ) {
      $url = $baseURL . $request['latitude'] . ',' . $request['longitude'];

      if ( ! empty($request['time']) ) {
        $url .= ',' . $request['time'];
      }

      if ( $options ) {
        $url .= '?' . http_build_query($options);
      }

      $requests[$idx]['url'] = $url;

      $httpRequests[] = $this->httpClient->createRequest('GET', $url, [
        'allow_redirects' => true,
        'timeout' => $this->timeout,
        'connect_timeout' => $this->connectTimeout,
        'headers' => [ 'User-Agent' => 'Tave/ForecastIOWrapper' ]
      ]);
    }

    try {
      $httpResults = \GuzzleHttp\batch($this->httpClient, $httpRequests);

      foreach ( $httpResults as $httpRequest ) {
        $url = $httpRequest->getUrl();
        foreach ( $requests as $idx => $request ) {
          if ( $request['url'] == $url ) {
            if ( $httpResults[$httpRequest] instanceof \GuzzleHttp\Exception\RequestException) {
              $this->lastRequest = $httpResults[$httpRequest]->getRequest();
              $this->lastResponse = $httpResults[$httpRequest]->hasResponse() ? $httpResults[$httpRequest]->getResponse() : null;
              $this->lastError = $httpResults[$httpRequest]->getMessage();
              $requests[$idx]['error'] = $this->lastError;
            }
            else {
              $requests[$idx]['forecast'] = $httpResults[$httpRequest]->json();
            }
          }
        }
      }

      return $requests;
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      $this->lastRequest = $e->getRequest();
      $this->lastResponse = $e->hasResponse() ? $e->getResponse() : null;
      $this->lastError = $e->getMessage();
      return false;
    }
  }
}