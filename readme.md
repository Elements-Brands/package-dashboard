# package-dashboard
A quick and dirty dashboard of package tracking information, using the [AfterShip API](http://aftership.com/) on the backend. Supports USPS, UPS, FedEx, DHL, and over 270 additional carriers worldwide. A paid AfterShip account is required.

## Features
* Allows users to add packages with or without tracking numbers to a central dashboard
* If a tracking number is provided, uses the AfterShip API to pull in expected delivery dates and package status
* If no tracking is available, users can specify an expected ship date
* Users can note who the package is from, where it's going, and what's inside

## Important Files
* *index.php* - The main shipments dashboard, shows the most up-to-date information from the database, allows users to view, add, edit, and delete shipments.
* *worker.php* - Pings the AfterShip API to pull in updated status information for packages. This should be set to run via cronjob, recommended frequency is once every hour.
* *database.sql* - Database schema.
