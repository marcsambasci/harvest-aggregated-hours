# Harvest Aggregated Hours

This app will fetch all tracked hours from every user in Harvest and update the custom field "Harvest Hours" in every Asana task with the accumulated hours from all users. This app should be set up as a CRON job to be ran every 2 to 4 hours a day.

## Initial Set up

- Run `composer install` in the project root to install the dependencies.
- Upload the files to your server.
- Set up a CRON job to run the index.php file every 2 to 4 hours a day.
