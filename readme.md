# package-dashboard
A dashboard of package tracking information, so everyone at your company can always see what packages are on the way, their status, expected arrival dates, and more.

Uses the [AfterShip](http://aftership.com/) API on the backend. Supports USPS, UPS, FedEx, DHL, and over 270 additional carriers worldwide. Uses [AfterShip's PHP SDK](https://github.com/AfterShip/aftership-php). A paid AfterShip account is required because this package uses AfterShips' "lookup shipment by ID" endpoint, which is a paid feature.

## Features
* Allows users to add packages with or without tracking numbers to a central dashboard
* If a tracking number is provided, uses the AfterShip API to pull in expected delivery dates and package status
* If no tracking is available, users can specify an expected ship date
* Users can note who the package is from, where it's going, and what's inside

## Important Files
* *index.php* - The main shipments dashboard, shows the most up-to-date information from the database, allows users to view, add, and delete shipments.
* *worker.php* - Pings the AfterShip API to pull in updated status information for packages. This should be set to run via cronjob, recommended frequency is once every hour. Also is run when users click the "refresh" button in the UI.
* *database.sql* - Database schema (mySQL).

## Required Configuration
* Obtain an AfterShip API key by signing up for an AfterShip account. Make sure to create your API key *after* you add payment information to the AfterShip account, or the API key won't have permission for the premium endpoints.
* Create a config.php file in the root directory as defined in the comments at the top of index.php
* Schedule a cronjob to run worker.php
