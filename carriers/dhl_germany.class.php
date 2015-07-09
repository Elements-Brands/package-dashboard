<?php
/**
 * Parses and returns DHL Germany package details.
 *
 * @package PHP_Parcel_Tracker
 * @subpackage Carrier
 * @author Christian Leibig <Loibisch>
 * @author Brian Stanback <stanback@gmail.com>
 * @copyright Copyright (c) 2010, Christian Leibig, Brian Stanback
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @version 3.0 <27 July 2010>
 * @filesource
 * @inheritdoc
 * @todo Map OnTrac (used by small shippers) to replace the DHL function?
 * @todo Use PHP's SimpleXml (XMLReader) or DOMDocument instead of the
 *     inline clsss.
 */

/****************************************************************************
 * Copyright 2010 Christian Leibig, Brian Stanback
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

class DHLGermanyCarrier extends AbstractCarrier
{
    /**
     * Parse and return DHL tracking details.
     *
     * @inheritdoc
     */
    public function fetchData($trackingNumber) {
        $link = 'http://nolp.dhl.de/nextt-online-public/set_identcodes.do?lang=de&idc=' . $this->trackingNumber;
        $html = $this->fetchUrl($link);

        if (!strpos($html, "IDC-Pr")) {
            // Matched error text, no package found
            return false;
        }

        $stats = array();
        $locations = array();

        // Have to disable error reporting because the DHL website is malformed
        error_reporting(0);

        // Create a new DOM object
        $dom = new DOMDocument;
        $dom->loadHTML($html);
        $dom->preserveWhiteSpace = false;

        $tables = $dom->getElementsByTagName('table');
        $rows = $tables->item(0)->getElementsByTagName('tr');

        // Loop over all table rows
        $rowcount = 0;
        foreach ($rows as $row) {
            if ($row->getAttribute('class') == 'listeven') {
                $cols = $row->getElementsByTagName('td');

                switch($rowcount) {
                     case 0:
                         // Product type
                         $stats['service'] = $cols->item(1)->nodeValue;
                         break;
                     case 1:
                         // Receiver
                         $stats['destination'] = $cols->item(1)->nodeValue;
                         break;
                     case 2:
                         // Current status
                         $stats['status'] = trim($cols->item(1)->nodeValue);
                         break;
                     case 3:
                         // Status update time
                         $stats['time'] = $cols->item(1)->nodeValue;
                         break;
                     case 4:
                         // Next step
                         $stats['next_location'] = trim($cols->item(1)->nodeValue);
                         break;
                }
                $rowcount++;
            }
        }

        if (!count($stats)) {
            return false;
        }

        return array(
            'details' => $stats,
            'locations' => $locations
        );
    }

    /**
     * Validate a DHL Germany tracking number.
     *
     * Stub method until the german DHL tracking number format
     * can be verified (I need an example or two). See the DHLCarrier
     * class for modulo 10 implementation.
     *
     * @inheritdoc
     */
    public function isTrackingNumber($trackingNumber) {
        return false;
    }
}
