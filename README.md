# Harvest Aggregated Hours

This app will fetch all tracked hours from every user in Harvest and update the custom field "Harvest Hours" in every Asana task with the accumulated hours from all users. This app should be set up as a CRON job to be ran at whatever time interval you wish.

## Initial Set up

- Run `composer install` in the project root to install the dependencies.
- Update the environment file with your API tokens, secret keys, and IDs.
- Upload the files to your server.
- Set up a CRON job to run the index.php file at your preferred time intervals.
