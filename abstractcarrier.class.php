<?php
/**
 * Abstract Carrier Class
 *
 * An abstract base class providing convienence methods for its concrete carriers.
 *
 * @package PHP_Parcel_Tracker
 * @author Brian Stanback <stanback@gmail.com>
 * @copyright Copyright (c) 2008, Brian Stanback
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @version 3.0 <27 July 2010>
 * @filesource
 * @abstract
 * @todo Add an abstract method for detecting whether a given tracking number
 *     if valid or not - this should make it possible to autodetect carriers
 */

/****************************************************************************
 * Copyright 2008 Brian Stanback
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

abstract class AbstractCarrier
{
    /**
     * Instance-specific configuration options.
     *
     * @var array
     */
    protected $config;

    /**
     * Class constructor: get and store passed formatting and retrieval settings.
     *
     * @param array $config Configuration settings passed by the ParcelTracker class.
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * The abstract method (to be implemented by each specific carrier) which is
     * responsible for fetching and parsing tracking data into the target data
     * structure.
     *
     * The following associative array structure should be populated and returned
     * by this function:
     *
     * array(
     *     'summary' => array(
     *         'service'       => [string],  // Service class
     *         'status'        => [string],  // Current status
     *         'destination'   => [string],  // Destination location
     *         'last_location' => [string],  // Last known location
     *         'next_location' => [string],  // Next known location
     *         'departure'     => [string],  // Departure date/time
     *         'est_arrival'   => [string],  // Estimated arrival date/time
     *         'arrival'       => [string],  // Arrival date/time
     *         'time'          => [string]   // The last updated date/time
     *         'details'       => [string],  // Miscellaneous details
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
     * )
     *
     * @abstract
     * @param string $trackingNumber The tracking number to retrieve details for.
     * @return array|boolean An associative array containing the 'details' and 'locations' or
     *    false if an error occured.
     */
    abstract function fetchData($trackingNumber);

    /**
     * Validate the tracking number.
     *
     * This method should be overridden by each concrete carrier.
     * The below stub return is used in the event that a check digit
     * algorithm is not available.
     *
     * @param string $trackingNumber The tracking number to validate.
     * @return boolean Returns true if the number is valid or false if unrecognized.
     */
    public function isTrackingNumber($trackingNumber) {
        return false;
    }

    /**
     * Shared metod for fetching data from a URL.
     *
     * @param string $url The url to fetch the HTML source for.
     */
    protected function fetchUrl($url) {
        if ($this->config['retrMethod'] == 'curl') {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $html = curl_exec($ch);
            curl_close($ch);

            if (function_exists('utf8_decode')) {
                $html = utf8_decode($html);
            }
        } else {
            $html = file_get_contents($url);
        }

        return $html;
    }
}
