<?php
/**
 * PHP Parcel Tracker
 *
 * Cache-enabled RSS gateway for tracking packages.
 * Requires PHP 5 or greater.
 *
 * @package PHP_Parcel_Tracker
 * @author Brian Stanback <stanback@gmail.com>
 * @author Thom Dyson <thom@tandemhearts.com>
 * @copyright Copyright (c) 2008, Brian Stanback, Thom Dyson
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @version 3.0 <27 July 2010>
 * @filesource
 */

/****************************************************************************
 * Copyright 2008 Brian Stanback, Thom Dyson
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 ***************************************************************************/

include_once('parceltracker.class.php');

// Configuration overrides, see parceltracker.class.php for all options and descriptions
$config = array(
    'cacheDir' => 'cache',     // The cache directory
    'cacheInterval' => 14400,  // The number of seconds before reloading tracking info
                               // Set to 0 to disable caching
    'dateFormat' => 'us',
    'showDayOfWeek' => true
);

// Get the requested package details
$carrier = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
$trackingNumber = isset($_REQUEST['num']) ? $_REQUEST['num'] : '';

// Sanitize the request variables
$carrier = preg_replace('#[^a-z0-9]#', '', strtolower($carrier));
$trackingNumber = preg_replace('#[^A-Z0-9]#', '', strtoupper($trackingNumber));

$cacheFile = '';
$rss = '';

if ($config['cacheInterval'] > 0) {
    // Check for cached results
    $cacheFile = $config['cacheDir'] . '/' . dechex(crc32($carrier . '_' . $trackingNumber)) . '.xml';
    if (file_exists($cacheFile)) {
        $modtime = filemtime($cacheFile);
        $rss = file_get_contents($cacheFile);

        if (strpos($rss, '&lt;b&gt;Arrival:&lt;/b&gt;') !== false || time() - $config['cacheInterval'] < $modtime) {
            // The package is delivered (no need to re-fetch tracking data) or the cache TTL hasn't expired

            $lastmod = gmdate('D, d M Y H:i:s \G\M\T', $modtime);
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $lastmod)) {
                // Cached by the browser
                header('Last-Modified: ' . $lastmod, true, 304);
                return;
            }

            // Use file-based cache
            header('Last-Modified: ' . $lastmod, true, 200);
        } else {
            $rss = '';
        }
    }
}

if (empty($rss)) {
    // Perform a new tracking lookup
    $tracker = new ParcelTracker($config);
    $parcel = $tracker->getDetails($trackingNumber, $carrier);

    // DEBUG
    //print_r($parcel);
    //return;

    $rss = $tracker->toRSS($parcel, $config['cacheInterval']);

    if ($config['cacheInterval'] > 0) {
        // Cache the results
        file_put_contents($cacheFile, $rss);

        // Output last-modified header to enable browser/device caching
        $lastmod = gmdate('D, d M Y H:i:s \G\M\T', filemtime($cacheFile));
        header('Last-Modified: ' . $lastmod, true, 200);
    }
}

// Output the results
header('Content-type: text/xml');
echo $rss;
