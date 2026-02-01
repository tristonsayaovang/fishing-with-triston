<?php


namespace Drupal\fwt_lure_suggestor\Service;


use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class WeatherRetrievalService
{
    protected ClientInterface $httpClient;
    protected LoggerInterface $logger;
    protected string $apiKey;

    public function __construct(ClientInterface $http_client, LoggerInterface $logger)
    {
        $this->httpClient = $http_client;
        $this->logger = $logger;
        $this->apiKey = $_ENV['WEATHER_API_KEY'] ;
    }

 
  /**
   * Get the current weather via La Crosse zipcode.
   *
   * @param string $zip
   *   ZIP code.
   *
   * @return array|null
   *   Returns an array with weather data, or null on error.
   */
  public function getCurrentWeather(): ?array {
    $zip = 54601;
    $endpoint = 'https://api.weatherapi.com/v1/current.json';
    
    try {
      $response = $this->httpClient->request('GET', $endpoint, [
        'query' => [
          'key' => $this->apiKey,
          'q' => $zip,
        ],
        'timeout' => 5,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $data;
    }
    catch (\Exception $e) {
      $this->logger->error('Weather API request failed: @message', ['@message' => $e->getMessage()]);
      return null;
    }
  }

}
