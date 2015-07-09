<?php
/**
 * Parses and returns DHL (USA) package details.
 *
 * @package PHP_Parcel_Tracker
 * @subpackage Carrier
 * @author Brian Stanback <stanback@gmail.com>
 * @copyright Copyright (c) 2008, Brian Stanback
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @version 3.0 <27 July 2010>
 * @filesource
 * @inheritdoc
 * @todo Map OnTrac (used by small shippers) to replace the DHL function?
 * @todo Use PHP's SimpleXml (XMLReader) or DOMDocument instead of the
 *     inline clsss.
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

class DHLCarrier extends AbstractCarrier
{
    /**
     * Parse and return DHL tracking details.
     *
     * @inheritdoc
     */
    public function fetchData($trackingNumber) {
        $link = 'http://track.dhl-usa.com/TrackByNbr.asp?ShipmentNumber=' . $trackingNumber;
        $html = $this->fetchUrl($link);

        if (!preg_match_all('#<input type="hidden" name="hdnXML([^"]+)" value="([^"]+)"/>#', $html, $matches, PREG_SET_ORDER)) {
            // XML tracking details not found
            return false;
        }

        $stats = array();
        $locations = array();

        $xml = '';
        foreach ($matches as $match) {
            $xml .= $match[2];
        }
        $xml = html_entity_decode($xml);

        $parser = new XMLParser();
        $obj = $parser->parse($xml);

        if (!isset($obj->track->trackshipments->shipment->result->code->data) && $obj->track->trackshipments->shipment->result->code->data != 1) {
            // XML document could not be parsed
            return false;
        }

        $stats['service'] = isset($obj->track->trackshipments->shipment->service->desc->data) ? $obj->track->trackshipments->shipment->service->desc->data : '';
        $stats['departure'] = isset($obj->track->trackshipments->shipment->pickup->date->data) ? $obj->track->trackshipments->shipment->pickup->date->data : '';
        $stats['destination'] = isset($obj->track->trackshipments->shipment->destinationdescr->location->data) ? $obj->track->trackshipments->shipment->destinationdescr->location->data : '';
        $stats['arrival'] = isset($obj->track->trackshipments->shipment->delivery->date->data) ? $obj->track->trackshipments->shipment->delivery->date->data : '';

        if (isset($obj->track->trackshipments->shipment->trackinghistory->status)) {
            if (!is_array($obj->track->trackshipments->shipment->trackinghistory->status)) {
                $obj->track->trackshipments->shipment->trackinghistory->status = array($obj->track->trackshipments->shipment->trackinghistory->status);
            }

            foreach ($obj->track->trackshipments->shipment->trackinghistory->status as $history) {
                $row = array();

                $row['location'] = $history->location->city->data . ', ';
                if (isset($history->location->state->data) && !empty($history->location->state->data)) {
                    $row['location'] .= $history->location->state->data . ', ';
                }
                if (isset($history->location->country->data) && !empty($history->location->country->data)) {
                    $row['location'] .= $history->location->country->data;
                }
                $row['location'] = rtrim($row['location'], ', ');

                $row['time'] = $history->date->data . ' ' . $history->time->data;
                $row['status'] = $history->statusdesc->data;

                $locations[] = $row;

                if (!isset($stats['status'])) {
                    $stats['status'] = $history->statusdesc->data;
                }
                if (!isset($stats['last_location'])) {
                    $stats['last_location'] = $row['location'];
                }
            }
        }

        return array(
            'details' => $stats,
            'locations' => $locations
        );
    }

    /**
     * Validate a DHL tracking number.
     *
     * Implementation of Identcode and Leitcode algorithms (both are based on modulo 10).
     *
     * This is an untested algorithm, please send any feedback to the author.
     *
     * @link http://www.activebarcode.com/codes/allbarcodes.html
     * @link http://www.activebarcode.com/codes/checkdigit/modulo10.html
     * @inheritdoc
     */
    public function isTrackingNumber($trackingNumber) {
        $trackingNumberLen = strlen($trackingNumber);

        if (!ctype_digit($trackingNumber) || ($trackingNumberLen != 12 && $trackingNumberLen != 14)) {
            return false;
        }

        $weightings = array(4, 9);
        $numWeightings = 2;

        $sum = 0;
        for ($i=0; $i<($trackingNumberLen-1); $i++) {
            $sum += ($weightings[$i % $numWeightings] * $trackingNumber[$i]);
        }

        $checkDigit = ((ceil($sum / 10) * 10) - $sum);

        return ($checkDigit == $trackingNumber[$trackingNumberLen-1]);
    }
}

/* Begin XML Parser Class */
class XMLParser
{
    var $tree = '$this->xml';
    var $xml = '';
    var $parser;

    function startElement($parser, $name, $attrs) {
        $name = strtolower($name);
        eval('$test = isset(' . $this->tree . '->' . $name . ');');
        if ($test) {
            eval('$tmp = ' . $this->tree . '->' . $name . ';');
            eval('$arr = is_array(' . $this->tree . '-> ' . $name . ');');
            if (!$arr) {
                eval('unset(' . $this->tree . '->' . $name . ');');
                eval($this->tree . '->' . $name . '[0] = $tmp;');
                $cnt = 1;
            } else {
                eval('$cnt = count(' . $this->tree . '->' . $name . ');');
            }
            $this->tree .= '->' . $name . "[$cnt]";
        } else {
            $this->tree .= '->' . $name;
        }

        if (count($attrs) > 0) {
            @eval($this->tree . '->attr = \'' . $attrs . '\';');
        }
    }

    function endElement($parser, $name) {
        for ($a = strlen($this->tree); $a>0; $a--) {
            if (substr($this->tree, $a, 2) == '->') {
                $this->tree = substr($this->tree, 0, $a);
                break;
            }
        }
    }

    function characterData($parser, $data) {
        @eval($this->tree . '->data = \'' . $data . '\';');
    }

    function parse($data) {
        $this->parser = xml_parser_create();
        xml_set_element_handler($this->parser, array(&$this, "startElement"), array(&$this, "endElement"));
        xml_set_character_data_handler($this->parser, array(&$this, "characterData"));
        if (!xml_parse($this->parser, $data)) {
            die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser)));
        }
        xml_parser_free($this->parser);
        return $this->xml;
    }
}
/* End XML Parser class */
