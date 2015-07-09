FILES:
---------------------------------------------------------------------------

- rss.php
     * The front controller which handles the retrieving and sanitization of
       user input, instantiating the ParcelTracker class, executing the class
       to gather tracking results in RSS format, and handling the server-based 
       and browser-based caching.

- parceltracker.class.php
     * The main class file which is responsible for gathering tracking data 
       and optionally formatting it into an RSS or SOAP document.

- abstractcarrier.class.php
     * The abstract class for implementing new tracking gateways.

- carriers/
     * A set of concrete classes (extending AbstractCarrier) which provide
       tracking data for specific carriers.

- cache/
     * Contains pre-parsed tracking data to prevent excessive queries
       to the carriers' servers.
     * There is currently no auto-cleanup of cached data, so this directory may
       be either be cleared manually as needed or a CRON job could be setup to
       handle this automatically.

- docs/
     * Documentation and log of changes pertanent to this software.

USAGE:
---------------------------------------------------------------------------

- Ensure the "cache" in the same directory as this file (or the path has been
  updated in the configuration).

- Change the permissions on the cache directory to allow write access
  to the web server, if you are getting write errors and in doubt of what
  permissions to grant, try doing a chmod 777.

- Optional step (required for Chumby widget access):
     * Upload a crossdomain.xml file to the web root of your domain:

       <cross-domain-policy>
           <allow-access-from domain="*" />
       </cross-domain-policy>
