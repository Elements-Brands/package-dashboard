<?php
/**
 * Parses and returns SmartPost package details.
 *
 * @package PHP_Parcel_Tracker
 * @subpackage Carrier
 * @author Brian Stanback <stanback@gmail.com>
 * @author Thom Dyson <thom@tandemhearts.com>
 * @copyright Copyright (c) 2008, Brian Stanback, Thom Dyson
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @version 3.0 <27 July 2010>
 * @filesource
 * @todo Consider implementation using DOMDocument.
 * @inheritdoc
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

class SmartPostCarrier extends AbstractCarrier
{
    /**
     * Parse and return Smart Post tracking details.
     *
     * @inheritdoc
     */
    public function fetchData($trackingNumber) {
        $link = 'http://spportal.fedex.com/sp/tracking.htm?PID=' . $trackingNumber;
        $html = $this->fetchUrl($link);

        if (!preg_match_all('#<td class="resultscell"[^>]+>([^>]+)</td>[^>]+<td class="resultscell"[^>]+>([^>]+)</td>[^>]+<td class="resultscell"[^>]+>([^>]+)</td>[^>]+<td class="resultscell"[^>]+>([^>]+)</td>#', $html, $matches, PREG_SET_ORDER)) {
            // Results not found
            return false;
        }

        $stats = array();
        $locations = array();

        $count = 0;
        $total = count($matches)-1;
        foreach ($matches as $match) {
            list($match, $date, $time, $status, $location) = $match;

            $row = array(
                'status' => $status,
                'time' => $date . ' ' . $time,
                'location' => $location
            );

            if ($count == 0) {
                $stats['status'] = $status;
                $stats['last_location'] = $location;

                if ($stats['status'] == 'Delivered') {
                    $stats['arrival'] = $date . ' ' . $time;
                }
            } elseif ($count == $total) {
                $stats['departure'] = $row['time'];
            }

            $locations[] = $row;
            $count++;
        }

        return array(
            'details' => $stats,
            'locations' => $locations
        );
    }

    /**
     * Validate a FedEx SmartPost tracking number.
     *
     * The matching algorithm for SmartPost is identical to the one for USPS,
     * therefore, one must specify the carrier manually if smart post tracking
     * data is desired.
     *
     * @inheritdoc
     */
    public function isTrackingNumber($trackingNumber) {
        return false;
    }
}
