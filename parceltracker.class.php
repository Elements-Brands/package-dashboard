<?php
/**
 * Parcel Tracker Class
 *
 * A class for parsing tracking details given a carrier and tracking number,
 * and returning the data as an array, RSS feed, or SOAP string.
 *
 * NOTE: This class should be used in conjunction with a caching mechanism
 * of your choice, see rss.php included in this project for an example.
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

include_once('abstractcarrier.class.php');

class ParcelTracker
{
    /**
     * An array of instantiated carriers.
     *
     * @var array
     */
    protected $carriers;

    /**
     * Default configuration options.
     *
     * @var array
     */
    protected $defaultConfig = array(
        'retrMethod'    => 'curl',      // HTTP retrieval method, 'curl' or 'standard' for to use
                                        // built-in PHP stream wrappers
        'dateFormat'    => 'us',        // Setting this to 'us' formats the date/time to U.S. standards,
                                        // Setting it to 'iso' will format using international standards
        'showDayOfWeek' => true,        // Set to true to include day of week in the dates, false for no day of week
        'carriersDir'   => 'carriers',  // Path to the carrier modules
        'carriers'      => array(       // List of carrier modules to load
                'ups'         => array('UPS', 'UPSCarrier', 'ups.class.php'),
                'usps'        => array('USPS', 'USPSCarrier', 'usps.class.php'),
                'fedex'       => array('FedEx', 'FedExCarrier', 'fedex.class.php'),
                'smartpost'   => array('SmartPost', 'SmartPostCarrier', 'smartpost.class.php'),
                'dhl'         => array('DHL', 'DHLCarrier', 'dhl.class.php'),
                'dhl_germany' => array('DHL', 'DHLGermanyCarrier', 'dhl_germany.class.php')
        )
    );

    /**
     * Instance-specific configuration options.
     *
     * @var array
     */
    protected $config;

    /**
     * Class constructor: get, configure, and store formatting and retrieval settings.
     *
     * @param array $config Class configuration settings.
     */
    public function __construct($config = array()) {
        $this->config = array_merge($this->defaultConfig, $config);

        if (!isset($this->config['url'])) {
            // Detect and set this script's URL
            $this->config['url'] = (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['PHP_SELF'])) ? 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] : '';
        }

	// Setup target date and time formatting
        $dayOfWeek = ($this->config['showDayOfWeek'] == 1) ? $dayOfWeek = 'l, ' : $dayOfWeek = '';
        switch (strtolower($this->config['dateFormat'])) {
            case 'us':
                $this->config['dateFormat'] = $dayOfWeek . 'F j, Y';
                $this->config['timeFormat'] = 'g:i A';
                break;
            case 'iso':
            default:
                $this->config['dateFormat'] = $dayOfWeek . 'Y-m-d';
                $this->config['timeFormat'] = 'G:i';
        }

        // Load and verify each of the carrier modules so that they are available
        // for auto-detection of tracking numbers.
        $this->carriers = array();
        foreach ($this->config['carriers'] as $carrierKey => $carrier) {
            list($carrierName, $carrierClass, $carrierFile) = $carrier;

            include_once($this->config['carriersDir'] . '/' . $carrierFile);

            if (!class_exists($carrierClass)) {
                die('Error: A carrier class file could not be located (' . $carrierFile . ')');
            }

            $this->carriers[$carrierKey] = new $carrierClass($this->config);

            if (!($this->carriers[$carrierKey] instanceof AbstractCarrier)) {
                die('Error: A carrier class was loaded which doesn\'t extend AbstractCarrier (' . $carrierClass . ')');
            }
        }
    }

    /**
     * Parse and return parcel details for the specified carrier and tracking number.
     *
     * Data is returned in the following structure:
     *
     * array(
     *     'carrier',          => [string],  // Carrier
     *     'trackingNumber',   => [string],  // Tracking Number
     *     'summary' => array(
     *         'service'       => [string],  // Service class
     *         'status'        => [string],  // Current status
     *         'destination'   => [string],  // Destination location
     *         'last_location' => [string],  // Last known location
     *         'next_location' => [string],  // Next known location
     *         'departure'     => [string],  // Departure date/time
     *         'est_arrival'   => [string],  // Estimated arrival date/time
     *         'arrival'       => [string],  // Arrival date/time
     *         'details'       => [string],  // Miscellaneous details
     *         'time'          => [string]   // The last updated date/time
     *     ),
     *     'locations' => array(
     *         [0] => array(
     *             'location'  => [string],  // Location name
     *             'status'    => [string],  // Status at location
     *             'time',     => [string],  // Date/time of package scan
     *             'details'   => [string]   // Package progress description
     *         ),
     *         [1] => array(
     *             ...
     *         ),
     *         ...
     *     )
     *
     * @param string $trackingNumber The tracking number to use for collecting parcel details.
     * @param string $carrier The name of the carrier to use (defined in $config['carriers']).
     *     If this is blank or unspecified, auto-detection of the carrier will be attempted.
     * @return array|boolean An associative array with package stats in the 'summary' key and
     *     an array of locations in the 'locations' key, or false if the query failed.
     * @todo Throw an exception if a problem is encountered, returning the specific error.
     */
    public function getDetails($trackingNumber, $carrier = '') {
        $parcel = false;

        if (empty($carrier)) {
            $carrier = $this->detectCarrier($trackingNumber);
        }

        if (isset($this->carriers[$carrier])) {
            // Get the package data
            $parcel = $this->carriers[$carrier]->fetchData($trackingNumber);
            if ($parcel) {
                $parcel = array_merge(array(
                    'carrier' => $this->config['carriers'][$carrier][0],
                    'trackingNumber' => $trackingNumber
                ), $parcel);
            }
        }

        return $parcel;
    }

    /**
     * Detect which carrier a particular tracking number belongs to.
     *
     * @param $trackingNumber string The tracking number to detect.
     * @return string|boolean The array key of the carrier, as defined in the
     *    carriers configuration setting or false if no match was found.
     */
    public function detectCarrier($trackingNumber) {
        foreach ($this->carriers as $carrierKey => $instance) {
            if ($instance->isTrackingNumber($trackingNumber)) {
                return $carrierKey;
            }
        }
        return false;
    }

    /**
     * Convert result data to a formatted RSS document.
     *
     * @param array $parcel The tracking result from the getDetails() method.
     * @return string The rendered RSS output.
     * @see getDetails()
     * @todo Handle errors with the parcel by outputing more explicit messages.
     */
    public function toRSS($parcel, $ttl = 3600) {
        if ($parcel) {
            $output = $this->rssGenerateHeader($parcel['carrier'], $ttl);
            $output .= $this->rssGenerateSummaryItem($parcel['details']);
            foreach ($parcel['locations'] as $location) {
                $output .= $this->rssGenerateLocationItem($location);
            }
        } else {
            $output = $this->rssGenerateHeader('N/A', $ttl);
            $output .= $this->rssGenerateErrorItem();
        }

        $output .= $this->rssGenerateFooter();

        return $output;
    }

    /**
     * Convert result data to a JavaScript Object Notation (JSON) encoded
     * format for use with XHR/AJAX-based applications.
     *
     * @param array $parcel The tracking result from the getDetails() method.
     * @return string JSON string containing the package details and locations.
     * @see getDetails()
     */
    public function toJSON($parcel) {
        return json_encode($parcel);
    }

    /**
     * Generate a standard RSS header.
     *
     * @return string The RSS header.
     * @see toRSS()
     */
    protected function rssGenerateHeader($carrier, $ttl = 0) {
        $output = '<?xml version="1.0" encoding="utf-8"?>' . "\n" .
                  '<rss version="2.0">' . "\n" .
                  "\t" . '<channel>' . "\n" .
                  "\t\t" . '<title>Package Tracking Results</title>' . "\n" .
                  "\t\t" . '<description>Tracking details provided by ' . $carrier . '</description>' . "\n" .
                  "\t\t" . '<link>' . $this->config['url'] . '</link>' . "\n";

        if ($ttl > 0) {
            $output .= "\t\t" . '<ttl>' . $ttl . '</ttl>' . "\n";
        }

        return $output;
    }

    /**
     * Generate the summary details for package tracking results.
     *
     * @param array $data An associative array containing the details portion from the
     *     getDetails() return value.
     * @return string An RSS summary item.
     * @see toRSS()
     */
    protected function rssGenerateSummaryItem($data) {
        $output = "\t\t" . '<item>' . "\n";
        $output .= "\t\t\t" . '<title>Summary</title>' . "\n";
        $output .= "\t\t\t" . '<description>';

        if (isset($data['status']) && !empty($data['status'])) {
            $output .= '&lt;b&gt;Status:&lt;/b&gt; ' . $data['status'] . '&lt;br/&gt;';
        }

        $output .= '&lt;b&gt;As of:&lt;/b&gt; ' . date($this->config['dateFormat'] . ' ' . $this->config['timeFormat'], isset($data['time']) ? strtotime($data['time']) : time()) . '&lt;br/&gt;';

        if (isset($data['departure']) && !empty($data['departure'])) {
            if (($departureTime = @strtotime($data['departure'])) != false) {
                $output .= '&lt;b&gt;Departure:&lt;/b&gt; ' . date($this->config['dateFormat'], $departureTime) . '&lt;br/&gt;';
            } else {
                $output .= '&lt;b&gt;Departure:&lt;/b&gt; ' . $data['departure'] . '&lt;br/&gt;';
            }
        }

        if (isset($data['destination']) && !empty($data['destination'])) {
            $output .= '&lt;b&gt;Destination:&lt;/b&gt; ' . $data['destination'] . '&lt;br/&gt;';
        }

        if (isset($data['service']) && !empty($data['service'])) {
            $output .= '&lt;b&gt;Service Type:&lt;/b&gt; ' . $data['service'] . '&lt;br/&gt;';
        }

        if (isset($data['est_arrival']) && !empty($data['est_arrival'])) {
            if (($estArrivalTime = @strtotime($data['est_arrival'])) != false) {
                $output .= '&lt;b&gt;Est. Arrival:&lt;/b&gt; ' . date($this->config['dateFormat'], $estArrivalTime) . '&lt;br/&gt;';
            } else {
                $output .= '&lt;b&gt;Est. Arrival:&lt;/b&gt; ' . $data['est_arrival'] . '&lt;br/&gt;';
            }
        } else if (isset($data['arrival']) && !empty($data['arrival'])) {
            if (($arrivalTime = @strtotime($data['arrival'])) != false) {
                $output .= '&lt;b&gt;Arrival:&lt;/b&gt; ' . date($this->config['dateFormat'], $arrivalTime) . '&lt;br/&gt;';
            } else {
                $output .= '&lt;b&gt;Arrival:&lt;/b&gt; ' . $data['arrival'] . '&lt;br/&gt;';
            }
        }

        if (isset($data['last_location']) && !empty($data['last_location'])) {
            $output .= '&lt;b&gt;Last Location:&lt;/b&gt; ' . $data['last_location'] .  '&lt;br/&gt;';
        }

        if (isset($data['next_location']) && !empty($data['next_location'])) {
            $output .= '&lt;b&gt;Next Location:&lt;/b&gt; ' . $data['next_location'] .  '&lt;br/&gt;';
        }

        if (isset($data['details']) && !empty($data['details'])) {
            $output .= '&lt;b&gt;Details:&lt;/b&gt; ' . $data['details'] .  '&lt;br/&gt;';
        }

        $output .= '</description>' . "\n";
        $output .= "\t\t\t" . '<pubDate>' . date('D, d M Y H:i:s O') . '</pubDate>' . "\n";
        $output .= "\t\t" . '</item>' . "\n";

        return $output;
    }

    /**
     * Generate the item for a tracked origin/destination point.
     *
     * @param array $data An associative array containing a single location from
     *     the locations portion of the getDetails() return value.
     * @return string An RSS location item.
     * @see toRSS()
     */
    protected function rssGenerateLocationItem($data) {
        $output = "\t\t" . '<item>' . "\n";
        $output .= "\t\t\t" . '<title>' . $data['status'] . '</title>' . "\n";
        $output .= "\t\t\t" . '<description>';

        if (isset($data['location']) && !empty($data['location'])) {
            $output .= 'Location: ' . $data['location'] .  '&lt;br/&gt;';
        }

        if (isset($data['details']) && !empty($data['details'])) {
            $output .= 'Details: ' . $data['details'] .  '&lt;br/&gt;';
        }

        if (isset($data['time']) && ($time = @strtotime($data['time'])) != false) {
            $output .= 'Local Time: ' . date($this->config['dateFormat'] . ' ' . $this->config['timeFormat'], $time) .  '&lt;br/&gt;';
        } else {
            $time = time();
        }

        $output .= '</description>' . "\n";
        $output .= "\t\t\t" . '<pubDate>' . date('D, d M Y H:i:s O', $time) . '</pubDate>' . "\n";
        $output .= "\t\t" . '</item>' . "\n";

        return $output;
    }

    /**
     * Build and return an invalid tracking number error.
     *
     * @return string An RSS error item.
     * @see toRSS()
     */
    protected function rssGenerateErrorItem() {
        $output = "\t\t" . '<item>' . "\n";
        $output .= "\t\t\t" . '<title>Summary</title>' . "\n";
        $output .= "\t\t\t" . '<description>&lt;b&gt;&lt;font color="#777777"&gt;Error&lt;/font&gt;&lt;br/&gt;The tracking number is invalid, has expired, or is not yet in the system.&lt;b&gt;</description>' . "\n";
        $output .= "\t\t\t" . '<pubDate>' . date('D, d M Y H:i:s O') . '</pubDate>' . "\n";
        $output .= "\t\t" . '</item>' . "\n";
        $output .= $html;

        return $output;
    }

    /**
     * Generate an RSS footer.
     *
     * @return string The RSS footer.
     * @see toRSS()
     */
    protected function rssGenerateFooter() {
        return "\t</channel>\n</rss>\n";
    }
}
