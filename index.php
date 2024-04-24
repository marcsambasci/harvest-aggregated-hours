<?php
ini_set('memory_limit', '512M'); // Increase to 512MB
ini_set('max_execution_time', 300); // Increase to 300 seconds

// Load composer
require __DIR__ . '/vendor/autoload.php';

// Load classes
require_once 'src/AsanaClient.php';
require_once 'src/HarvestClient.php';

// Load environment variables
use AsanaHarvest\AsanaClient;
use AsanaHarvest\HarvestClient;
use Dotenv\Dotenv;

// Load .env file
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$harvestClient = new HarvestClient($_ENV['HARVEST_API_TOKEN'], $_ENV['HARVEST_ACCOUNT_ID']);

// Check if the updated_hours.json file exists and has the 'updated' property set to true
$dataDirectory = __DIR__ . '/data/';
$updatedHoursFile = $dataDirectory . 'updated_hours.json';

/**
 * Check if the updated_hours.json file exists and has the 'updateData' property set to true.
 * If it does, update the hours in Asana tasks using data from the file.
 * If it doesn't, aggregate hours from Harvest API and save the result to the file.
 */
if (file_exists($updatedHoursFile)) {
    // Load the JSON data from the file
    $updateData = json_decode(file_get_contents($updatedHoursFile), true);

    // Check if the data is an array and the 'updateData' property is true
    if (is_array($updateData) && isset($updateData['updateData']) && $updateData['updateData']) {
        // Initialize Asana client
        $asanaClient = new AsanaClient($_ENV['ASANA_API_TOKEN']);

        // Update hours in Asana tasks using data from the file
        $asanaClient->updateHoursFromJson();

        // Update the 'updateData' property to false in the file
        file_put_contents($updatedHoursFile, json_encode(array('updateData' => false, 'hours' => $updateData['hours']), JSON_PRETTY_PRINT));
    } else {
        // Harvest API calls
        $harvestClient->aggregateHoursByExternalReference();
    }
} else {
    // Harvest API calls
    $harvestClient->aggregateHoursByExternalReference();
}

echo "Success!";

exit;
