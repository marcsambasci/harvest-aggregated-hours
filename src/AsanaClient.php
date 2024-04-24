<?php

namespace AsanaHarvest;

use GuzzleHttp\Client;

class AsanaClient
{
    private $accessToken;
    private $client;
    private $errorLogFile;

    /**
     * Constructor for the AsanaClient class.
     *
     * @param string $accessToken The Asana personal access token.
     */
    public function __construct($accessToken)
    {
        // Set the Asana personal access token.
        $this->accessToken = $accessToken;

        // Initialize the HTTP client.
        $this->client = new Client([
            // Set the base URI for API requests.
            'base_uri' => 'https://app.asana.com/api/1.0/',

            // Set the authorization header with the access token.
            'headers' => [
                'Authorization' => "Bearer {$this->accessToken}"
            ]
        ]);

        // Set the path to the error log file. The file name includes the current date in YYYY-MM-DD format.
        $this->errorLogFile = "logs/" . $this->getTodayDate() . "_error.log";
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
     * Updates hours in Asana tasks based on the data stored in a JSON file.
     *
     * The JSON file should contain a mapping between task IDs and hours to be updated.
     * The function will retrieve the custom field ID for the 'Harvest Hours' custom field
     * in each task and update the corresponding custom field value with the provided hours.
     *
     * @return void
     */
    public function updateHoursFromJson()
    {
        // Retrieve the hours data from the JSON file
        $hoursData = $this->readUpdatedHoursFromFile();

        // Loop through each task in the data and update its hours
        foreach ($hoursData['hours'] as $taskId => $hours) {
            // Get the ID of the 'Harvest Hours' custom field for the task
            $customFieldId = $this->getCustomFieldIdForTask($taskId, 'Harvest Hours');

            // If the custom field ID exists, update the hours in the custom field
            if ($customFieldId) {
                $this->updateCustomField($taskId, $customFieldId, $hours);
            }
        }
    }

    /**
     * Reads the updated hours data from a JSON file.
     *
     * @return array The decoded JSON data, or an empty array if the file doesn't exist.
     */
    private function readUpdatedHoursFromFile()
    {
        // Path to the JSON file containing the updated hours data
        $filePath = 'data/updated_hours.json';

        // If the file doesn't exist, return an empty array
        if (!file_exists($filePath)) {
            return [];
        }

        // Read the contents of the file and decode it into an array
        $jsonData = file_get_contents($filePath);
        return json_decode($jsonData, true);
    }

    /**
     * Retrieves the ID of a custom field for a given task.
     *
     * Sends a GET request to the Asana API to retrieve the task data and then loops through
     * the custom fields of the task to find the ID of the field with the given name.
     *
     * @param int    $taskId The ID of the task.
     * @param string $fieldName The name of the custom field.
     * @return string|null The ID of the custom field, or null if not found.
     */
    private function getCustomFieldIdForTask(int $taskId, string $fieldName): ?string
    {
        // Send a GET request to the Asana API to retrieve the task data
        try {
            // Set the request parameters
            $params = [
                'query' => ['opt_fields' => 'custom_fields']
            ];

            // Send the GET request
            $response = $this->client->get("tasks/{$taskId}", $params);
        } catch (\Exception $e) {
            // If the request fails, log the error and return null
            $this->handleRequestException($taskId, $e, $this->errorLogFile);
            return null;
        }

        // Decode the response body as an associative array
        $taskData = json_decode($response->getBody(), true);

        // Loop through the custom fields of the task and find the ID of the field with the given name
        foreach ($taskData['data']['custom_fields'] as $customField) {
            // If the custom field name matches the given name, return the ID
            if ($customField['name'] === $fieldName) {
                return $customField['gid'];
            }
        }

        // If no custom field with the given name is found, return null
        return null;
    }

    /**
     * Updates the value of a custom field for a given task.
     *
     * @param int    $taskId         The ID of the task.
     * @param string $customFieldId  The ID of the custom field.
     * @param float  $hours          The new value for the custom field.
     * @return array                 The response from the API call, decoded as an associative array.
     * @throws \GuzzleHttp\Exception\RequestException If the API call fails.
     * @throws \Exception If any other exception occurs.
     */
    private function updateCustomField(int $taskId, string $customFieldId, float $hours): array
    {
        // Create the directory for the error log, if it doesn't exist.
        $this->createDirectory($this->errorLogFile);

        try {
            // Prepare the request payload.
            $payload = [
                'data' => [
                    'custom_fields' => [
                        $customFieldId => $hours
                    ]
                ]
            ];

            // Make the request to update the custom field value.
            $response = $this->client->put(
                "tasks/{$taskId}",
                [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => $payload
                ]
            );

            // Optional: Log the success of the update.
            $this->logSuccess($taskId, 'logs/success.log');

            // Decode the response body and return it.
            return json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Handle request exceptions.
            return $this->handleRequestException($taskId, $e, $this->errorLogFile);
        } catch (\Exception $e) {
            // Handle generic exceptions.
            return $this->handleGenericException($e, $this->errorLogFile);
        }
    }

    /**
     * Create a directory if it does not exist.
     *
     * @param string $filePath Path to a file within the directory.
     * @return void
     */
    private function createDirectory(string $filePath): void
    {
        // Extract the directory from the file path.
        $directory = dirname($filePath);

        // Check if the directory exists.
        if (!is_dir($directory)) {
            // If the directory does not exist, create it with read, write, and execute permissions for everyone.
            mkdir($directory, 0777, true);
        }
    }

    /**
     * Logs a success message to a file.
     *
     * This function generates a success log message containing the ID of the task that was
     * updated, and the current date and time, and appends it to the specified success log file.
     *
     * @param int $taskId The ID of the task that was updated.
     * @param string $successLogFile The path to the file where the success message should be logged.
     * @return void
     */
    private function logSuccess(int $taskId, string $successLogFile): void // UNCOMMENT Line 161 to enable logging.
    {
        // Generate the success log message.
        $successLog = sprintf(
            "Successfully updated task %d at %s\n",
            $taskId,
            date('Y-m-d H:i:s')
        );

        // Append the success log message to the success log file.
        file_put_contents(
            $successLogFile,
            $successLog,
            FILE_APPEND
        );
    }

    /**
     * Handles a Guzzle HTTP request exception.
     *
     * This function generates an error log, appends it to the specified error log file, and returns
     * an array with the error message.
     *
     * @param int $taskId The ID of the task that caused the exception.
     * @param \GuzzleHttp\Exception\RequestException $e The exception that occurred.
     * @param string $errorLogFile The path to the error log file.
     * @return array An array with the error message.
     */
    private function handleRequestException(int $taskId, \GuzzleHttp\Exception\RequestException $e, string $errorLogFile): array
    {
        // Generate the error log.
        $errorLog = $this->generateErrorLog($taskId, $e);

        // Append the error log to the error log file.
        file_put_contents(
            $errorLogFile,
            $errorLog,
            FILE_APPEND
        );

        // Return the error message.
        return ['error' => $e->getMessage()];
    }

    /**
     * Generates an error log message for a Guzzle HTTP request exception.
     *
     * If the exception has a response, it generates an error log message with the status code and
     * response body. Otherwise, it generates an error log message with just the exception message.
     *
     * @param int $taskId The ID of the task that caused the exception.
     * @param \GuzzleHttp\Exception\RequestException $e The exception that occurred.
     * @return string The error log message.
     */
    private function generateErrorLog(int $taskId, \GuzzleHttp\Exception\RequestException $e): string
    {
        // Check if the exception has a response
        if ($e->hasResponse()) {
            // Get the status code and response body
            $statusCode = $e->getResponse()->getStatusCode();
            $responseBody = $e->getResponse()->getBody()->getContents();

            // Generate and return the error log message for tasks with a response
            return sprintf(
                "[%s] Error updating custom field for task with ID %s. HTTP status: %s. Response: %s\n",
                date('Y-m-d H:i:s'),
                $taskId,
                $statusCode,
                $responseBody
            );
        }

        // Generate and return the error log message for tasks without a response
        return sprintf(
            "[%s] Failed to update custom field for task with ID %s: %s\n",
            date('Y-m-d H:i:s'),
            $taskId,
            $e->getMessage()
        );
    }

    /**
     * Handles an unexpected exception.
     *
     * This function logs the error message and returns an associative array with the error message.
     *
     * @param \Exception $e The exception that occurred.
     * @param string $errorLogFile The path to the error log file.
     * @return array An associative array with the error message.
     */
    private function handleGenericException(\Exception $e, string $errorLogFile): array
    {
        // Generate the error log message.
        $genericErrorLog = sprintf(
            "[%s] An unexpected error occurred: %s\n",
            date('Y-m-d H:i:s'),
            $e->getMessage()
        );

        // Append the error log message to the error log file.
        file_put_contents(
            $errorLogFile,
            $genericErrorLog,
            FILE_APPEND
        );

        // Return the error message.
        return [
            'error' => $e->getMessage(),
        ];
    }
}
