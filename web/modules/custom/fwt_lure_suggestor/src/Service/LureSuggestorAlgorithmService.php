<?php

namespace Drupal\fwt_lure_suggestor\Service;

use Drupal\fwt_lure_suggestor\Service\WeatherRetrievalService;

class LureSuggestorAlgorithmService {

    protected WeatherRetrievalService $weatherRetrievalService;

    public function __construct(WeatherRetrievalService $weatherRetrievalService)
    {
        $this->weatherRetrievalService = $weatherRetrievalService;
    }

    public function test(): string
    {

        return 'test';
    }

    public function getWeather()
    {
        return $this->weatherRetrievalService->getCurrentWeather();
    }
}