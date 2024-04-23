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

// Initialize Asana and Harvest clients
$asanaClient = new AsanaClient($_ENV['ASANA_API_TOKEN']);
$harvestClient = new HarvestClient($_ENV['HARVEST_API_TOKEN'], $_ENV['HARVEST_ACCOUNT_ID']);

// Harvest API calls
$hoursByReference = $harvestClient->aggregateHoursByExternalReference();

// Asana API calls
$updateHoursFromJson = $asanaClient->updateHoursFromJson();

echo "Done!";

exit;
