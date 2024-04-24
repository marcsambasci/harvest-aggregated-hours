<?php

namespace AsanaHarvest;

use GuzzleHttp\Client;

class HarvestClient
{
    private $apiToken;
    private $accountId;
    private $client;

    /**
     * Constructor for initializing the Harvest API client.
     *
     * @param string $apiToken The Harvest API access token
     * @param string $accountId The Harvest account ID
     */
    public function __construct(string $apiToken, string $accountId)
    {
        // Set the Harvest API access token
        $this->apiToken = $apiToken;
        
        // Set the Harvest account ID
        $this->accountId = $accountId;
        
        // Initialize the Guzzle HTTP client with base URI and headers
        $this->client = new Client([
            'base_uri' => 'https://api.harvestapp.com/v2/', // Base URI for API requests
            'headers' => [
                'Authorization' => "Bearer {$this->apiToken}", // Set the authorization header for the API token
                'Harvest-Account-ID' => $this->accountId, // Set the Harvest account ID
                'User-Agent' => 'Harvest-Asana' // Set the user agent header
            ]
        ]);
    }

    /**
     * Returns the date 90 days ago in the format 'Y-m-d'.
     *
     * This function uses the PHP strtotime function to subtract 90 days from the
     * current date and time, and then formats the result as 'Y-m-d'.
     *
     * @return string The date 90 days ago.
     */
    private function get90DaysAgoDate()
    {
        // Calculate the date 90 days ago using strtotime
        $date = strtotime('-90 days');
        
        // Format the result as 'Y-m-d' and return it
        return date('Y-m-d', $date);
    }

    /**
     * Get the current date in YYYY-MM-DD format.
     *
     * This function uses the PHP date function to get the current date and format it
     * as 'Y-m-d'.
     *
     * @return string The current date in YYYY-MM-DD format.
     */
    private function getTodayDate()
    {
        // Get the current date and format it as 'Y-m-d'.
        return date('Y-m-d');
    }

    /**
     * Fetches time entries from the Harvest API for a specified date range.
     *
     * @param string|null $fromDate The start date, defaults to 90 days ago if not provided.
     * @param string|null $toDate The end date, defaults to today if not provided.
     * @return array The fetched time entries.
     * @throws \RuntimeException If there is an error while fetching time entries from the Harvest API.
     */
    private function fetchTimeEntries(?string $fromDate = null, ?string $toDate = null) : array
    {
        // Set the from and to dates for the API request.
        $from = $fromDate ?? $this->get90DaysAgoDate();
        $to = $toDate ?? $this->getTodayDate();

        // Initialize variables for the API request loop.
        $entries = []; // Array to store fetched time entries.
        $page = 1; // Current page number.
        $hasMorePages = true; // Flag to indicate if there are more pages to fetch.

        // Set the query parameters for the API request.
        $query = [
            'from' => $from,
            'to' => $to,
            'page' => $page,
        ];

        // Loop to fetch time entries from multiple pages.
        while ($hasMorePages) {
            // Send the API request for the current page.
            $response = $this->client->get('time_entries', ['query' => $query]);

            // Decode the response body.
            $data = json_decode($response->getBody(), true);

            // Check if the response is valid.
            if (!isset($data['time_entries']) || !is_array($data['time_entries'])) {
                throw new \RuntimeException('Invalid response from Harvest API');
            }

            // Filter the time entries array to include only entries with an external reference ID.
            $filteredEntries = array_filter($data['time_entries'], function ($entry) {
                return isset($entry['external_reference']['id']);
            });

            // Merge the fetched entries with the existing entries array.
            $entries = array_merge($entries, $filteredEntries);

            // Update the page number and hasMorePages flag based on the API response.
            $page = $data['next_page'] ?? null;
            $hasMorePages = $page !== null;

            // Log the fetching of the next page if applicable.
            if ($hasMorePages) {
                $query['page'] = $page;
            }
        }

        // Return the fetched time entries.
        return $entries;
    }

    /**
     * Aggregates hours by external reference and saves the result to JSON files.
     *
     * This function takes an array of time entries, aggregates the hours by
     * external reference ID, and saves the result to two JSON files: all_hours.json
     * and updated_hours.json. It returns the aggregate hours by reference ID,
     * excluding hours that are already present in the saved JSON files.
     *
     * @param array $entries Array of time entries.
     * @throws \InvalidArgumentException If $entries is not an array.
     * @return array Associative array of hours by reference ID.
     */
    public function aggregateHoursByExternalReference()
    {
        $entries = $this->fetchTimeEntries();

        // Check if $entries is not empty
        if (empty($entries)) {
            throw new \InvalidArgumentException('$entries must not be empty');
        }

        // Aggregate hours by reference ID
        $hoursByReference = array_reduce($entries, function (array $hours, array $entry) {
            $referenceId = $entry['external_reference']['id'] ?? null;

            // If reference ID exists, add hours to the aggregate
            if ($referenceId !== null) {
                $hours[$referenceId] = ($hours[$referenceId] ?? 0) + ($entry['hours'] ?? 0);
            }

            return $hours;
        }, []);

        // Define output file paths
        $outputPathAll = 'data/all_hours.json';
        $outputPathUpdated = 'data/updated_hours.json';

        // Create directory if it doesn't exist
        $directory = dirname($outputPathAll);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $hasUpdatedHours = false;

        // Array to store only the updated hours
        $differences = [];

        // Final data will be the merged data
        $finalData = $hoursByReference;

        // Check if the all_hours.json exists and merge data if it does
        if (file_exists($outputPathAll)) {
            // Load the existing data
            $jsonData = file_get_contents($outputPathAll);
            $existingHours = json_decode($jsonData, true);

            // Merge and determine differences
            foreach ($hoursByReference as $referenceId => $hours) {
                if (!isset($existingHours[$referenceId]) || $existingHours[$referenceId] != $hours) {
                    // Update the existing data with new hours and track differences
                    $existingHours[$referenceId] = $hours;
                    $differences[$referenceId] = $hours; // Add to differences
                }
            }

            $finalData = $existingHours; // Merged data
            $hasUpdatedHours = !empty($differences);
        } else {
            $differences = $hoursByReference; // All new data are differences if file doesn't exist
            $hasUpdatedHours = !empty($differences);
        }

        // Write the updated final data back to the all_hours.json file
        file_put_contents($outputPathAll, json_encode($finalData, JSON_PRETTY_PRINT));

        // Write only the updated hours to the updated_hours.json file
        file_put_contents($outputPathUpdated, json_encode(array('updateData' => $hasUpdatedHours, 'hours' => $differences), JSON_PRETTY_PRINT));

        // Return the newly aggregated hours (for confirmation or further processing)
        return $differences;
    }
}
