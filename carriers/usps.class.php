<?php
/**
 * Parses and returns USPS package details.
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

class USPSCarrier extends AbstractCarrier
{
    /**
     * Parse and return USPS tracking details.
     *
     * @inheritdoc
     */
    public function fetchData($trackingNumber) {
        $link = 'http://trkcnfrm1.smi.usps.com/PTSInternetWeb/InterLabelInquiry.do?strOrigTrackNum=' . urlencode($trackingNumber);
        $html = $this->fetchUrl($link);

        if (!preg_match('#Status: <span class="mainTextbold">([^<]+)</span><br><br>([^<]+)<br><br>#', $html, $matches)) {
            // No tracking results found
            return false;
        }

        $stats = array();
        $locations = array();

        $stats['status'] = $matches[1];
        $stats['details'] = trim($matches[2]);

        if (preg_match_all('#<INPUT TYPE=HIDDEN NAME="event([0-9]+)" VALUE="([^"]+)">#', $html, $matches, PREG_SET_ORDER)) {
            // Location details

            $count = 0;
            $total = count($matches)-1;

            foreach ($matches as $match) {
                if (preg_match('#^([^,;]+)(,|;) ([A-Za-z]+) ([0-9]{1,2}), ([0-9]{4})(, ([0-9:a-zA-Z ]{7,8})(, (.*))?)?$#', $match[2], $details)) {
                    $row = array(
                        'status' => $details[1],
                        'time' => $details[3] . ' ' . $details[4] . ', ' . $details[5]
                    );
                    if (isset($details[7])) {
                        $row['time'] .= ' ' . strtoupper($details[7]);
                    }
                    if (isset($details[9])) {
                        $row['location'] = $details[9];
                    }

                    if ($count == 0) {
                        $stats['last_location'] = isset($row['location']) ? $row['location'] : '';
                        if ($row['status'] == 'Delivered') {
                            $stats['arrival'] = $row['time'];
                        }
                    } elseif ($count == $total) {
                        $stats['departure'] = $row['time'];
                    }

                    $count++;
                } else {
                    $row = array('status' => $match[2]);
                }

                $locations[] = $row;
            }
        }

        return array(
            'details' => $stats,
            'locations' => $locations
        );
    }

    /**
     * Validate a USPS tracking number.
     *
     * @inheritdoc
     */
    public function isTrackingNumber($trackingNumber) {
        return ($this->isUSS128($trackingNumber) ||
                $this->isUSS39($trackingNumber));
    }

    /**
     * Validate a USPS tracking number based on USS Code 128 Subset C 20-digit barcode PIC (human
     * readable portion).
     *
     * @link http://www.usps.com/cpim/ftp/pubs/pub109.pdf (Publication 109. Extra Services Technical Guide, pg. 19)
     * @link http://www.usps.com/cpim/ftp/pubs/pub91.pdf (Publication 91. Confirmation Services Technical Guide pg. 38)
     * @param $trackingNumber string The tracking number to perform the test on.
     * @return boolean True if the passed number is a USS 128 shipment.
     */
    public function isUSS128($trackingNumber) {
        $trackingNumberLen = strlen($trackingNumber);

        if (!ctype_digit($trackingNumber) || ($trackingNumberLen != 20 && $trackingNumberLen != 22 && $trackingNumberLen != 30)) {
            return false;
        }

        $weightings = array(3, 1);
        $numWeightings = 2;

        if ($trackingNumberLen == 20) {
            // Add service code to shortened number. This passes known test cases but need
            // to verify that this is always a correct assumption.
            $trackingNumber = '91' . $trackingNumber;
        } elseif ($trackingNumberLen == 30) {
            // Truncate extra information
            $trackingNumber = substr($trackingNumber, 8, 30);
        }

        $sum = 0;
        for ($i=20; $i>=0; $i--) {
            $sum += ($weightings[$i % $numWeightings] * $trackingNumber[$i]);
        }

        $checkDigit = ((ceil($sum / 10) * 10) - $sum);

        return ($checkDigit == $trackingNumber[21]);
    }

    /**
     * Validate a USPS tracking number based on a USS Code 39 Barcode, this uses the MOD 11
     * check character calculation for validating both domestic and international mail. The
     * MOD 10 check may be used for domestic mail but is not needed in this scenario.
     *
     * @link http://www.usps.com/cpim/ftp/pubs/pub97.pdf (Publication 97. Express Mail Manifesting Technical Guide, pg. 64)
     * @param $trackingNumber string The tracking number to perform the test on.
     * @return boolean True if the passed number is a USS 39 tracking number.
     */
    public function isUSS39($trackingNumber) {
        if (strlen($trackingNumber) != 13) {
            return false;
        }

        $trackingPrefix = substr($trackingNumber, 0, 2);
        $trackingSuffix = substr($trackingNumber, -2);
        $trackingNumber = substr($trackingNumber, 2, -2);

        if (!ctype_alpha($trackingPrefix) || !ctype_alpha($trackingSuffix) || !ctype_digit($trackingNumber)) {
            return false;
        }

        $weightings = array(8, 6, 4, 2, 3, 5, 9, 7);
        $numWeightings = 8;

        $sum = 0;
        for ($i=0; $i<8; $i++) {
            $sum += ($weightings[$i % $numWeightings] * $trackingNumber[$i]);
        }

        $checkDigit = ($sum % 11);
        $checkDigit = ($checkDigit == 0) ? 5 : (($checkDigit == 1) ? 0 : (11 - $checkDigit));

        return ($checkDigit == $trackingNumber[8]);
    }
}
