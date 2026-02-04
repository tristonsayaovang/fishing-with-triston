<?php

namespace Drupal\fwt_lure_suggestor\Service;

use Drupal\fwt_lure_suggestor\Service\WeatherRetrievalService;
use Drupal\taxonomy\Entity\Term;
use Drupal\node\Entity\Node;

class LureSuggestorAlgorithmService
{

    protected WeatherRetrievalService $weatherRetrievalService;

    public function __construct(WeatherRetrievalService $weatherRetrievalService)
    {
        $this->weatherRetrievalService = $weatherRetrievalService;
    }

    public function test(): string
    {

        return 'test';
    }

    public function getWeather(): ?array
    {
        return $this->weatherRetrievalService->getCurrentWeather();
    }

    public function createLureConditions(): ?array
    {
        $weatherInfo = $this->getWeather();
        if (!is_array($weatherInfo)) {
            return null;
        }
        $weather = $weatherInfo['current'];

        $lureConditions = [];

        $condition = $weather['condition']['text'];
        $cloud = $weather['cloud'];
        $wind = $weather['wind_mph'];
        $barometricPressure = $weather['pressure_in'];
        $temp = $weather['temp_f'];


        if ($cloud > 66) {
            $lureConditions[] = 'Cloudy';
        } elseif ($cloud > 33 && $cloud <= 66) {
            $lureConditions[] = 'Partly Cloudy';
        } else {
            $lureConditions[] = 'Sunny';
        }


        if ($wind > 15) {
            $lureConditions[] = 'Windy';
        } elseif ($wind > 8 && $wind <= 15) {
            $lureConditions[] = 'Slightly Windy';
        } else {
            $lureConditions[] = 'Low Wind';
        }


        if ($temp > 80) {
            $lureConditions[] = 'Hot';
        } elseif ($temp > 70 && $temp <= 80) {
            $lureConditions[] = 'Warm';
        } elseif ($temp > 62 && $temp <= 70) {
            $lureConditions[] = 'Mild';
        } elseif ($temp > 50 && $temp <= 62) {
            $lureConditions[] = 'Colder';
        } else {
            $lureConditions[] = 'Cold';
        }

        $today = date("n");

        switch ($today) {
            case in_array($today, [12, 1 ,2, 3]):
                $lureConditions[] = 'Winter';
                break;
            case in_array($today, [4 ,5]):
                $lureConditions[] = 'Prespawn';
                $lureConditions[] = 'Early Spring';
                break;
            case in_array($today, [6]):
                $lureConditions[] = 'Spawn';
                $lureConditions[] = 'Spring';
                break;
            case in_array($today, [7]):
                $lureConditions[] = 'Post Spawn';
                $lureConditions[] = 'Summer';
                break;
            case in_array($today, [8]):
                $lureConditions[] = 'Late Summer';
                break;
            case in_array($today, [9]):
                $lureConditions[] = 'Early Fall';
                break;
            case in_array($today, [10]):
                $lureConditions[] = 'Fall';
                break;
            case in_array($today, [11]):
                $lureConditions[] = 'Late Fall';
                break;
        }
        
        $requiredGroups = [
            ['Warm', 'Mild'],
            ['Cloudy', 'Partly Cloudy'],
            ['Windy', 'Slightly Windy'],
        ];

        $matchesAllGroups = true;
        foreach ($requiredGroups as $group) {
            if (!$this->arrContainsValues($lureConditions, $group)) {
                $matchesAllGroups = false;
                break;
            }
        }

        if (
            $matchesAllGroups &&
            $barometricPressure >= 29.7 &&
            $barometricPressure <= 30.4
        ) {
            $lureType = 'Power';
        } else {
            $lureType = 'Finesse';
        }


        return ['lureConditions' => $lureConditions, 'lureType' => $lureType];
    }

    private function arrContainsValues(array $lureConditions, array $desiredConditions): bool
    {
        return !empty(array_intersect($lureConditions, $desiredConditions));
    }


    public function fetchLures(): mixed
    {

        $lureConditionsArr = $this->createLureConditions();

        $lureConditions = $lureConditionsArr['lureConditions'] ?? [];
        $lureType       = $lureConditionsArr['lureType'] ?? null;

        if (empty($lureConditions) || empty($lureType)) {
            return null;
        }

        $weatherTids = $this->getTermIdsByNames('weather_conditions', $lureConditions);

        // This is to make sure there are no missing terms
        if (count($weatherTids) !== count($lureConditions)) {
            return null;
        }

        $lureTypeTids = $this->getTermIdsByNames('lure_type', [$lureType]);

        if (empty($lureTypeTids)) {
            return null;
        }

        $lureTypeTid = reset($lureTypeTids);

        $query = \Drupal::entityQuery('node')
            ->accessCheck(TRUE)
            ->condition('type', 'lure')
            ->condition('status', 1);


        $query->condition('field_ideal_conditions.target_id', $weatherTids, 'IN');


        $query->condition('field_lure_type.target_id', $lureTypeTid, 'IN');

        $nids = $query->execute();

        return array_slice(Node::loadMultiple($nids), 0, 3);
    //    return $nids ? Node::loadMultiple($nids) : ['cookies'];
    }

    private function getTermIdsByNames(string $vocabulary, array $names): array
    {
        return \Drupal::entityQuery('taxonomy_term')
            ->accessCheck(TRUE)
            ->condition('vid', $vocabulary)
            ->condition('name', $names, 'IN')
            ->execute();
    }
}
