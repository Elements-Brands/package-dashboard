<?php
/**
 * PHP Parcel Tracker Test Driver
 *
 * A simple test driver for validating the detecting and data gathering
 * of different carriers/tracking numbers.
 *
 * @package PHP_Parcel_Tracker
 * @author Brian Stanback <stanback@gmail.com>
 * @copyright Copyright (c) 2008, Brian Stanback
 * @license http://www.apache.org/licenses/LICENSE-2.0.html Apache 2.0
 * @version 3.0 <27 July 2010>
 * @todo Integrate tests with PHPUnit or SimpleUnit.
 * @filesource
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

include_once('parceltracker.class.php');

// Enter your test numbers here
$testNumbers = array(
	'1ZW4W0890398815084'           => 'ups',
    '9405511699000947301577'       => 'usps',
);

testDetection($testNumbers);
//testDataRetrieval($testNumbers);

/**
 * Test detection of carriers.
 *
 * @param $testNumbers array An array of tracking numbers to test.
 */
function testDetection($testNumbers) {
    echo ">>> Now testing carrier detection:\n\n";

    $tracker = new ParcelTracker();

    $numTests = count($testNumbers);
    $failures = 0;
    $successes = 0;

    foreach ($testNumbers as $number => $expectedCarrier) {
        $carrier = $tracker->detectCarrier($number);

        if (!$carrier || $carrier != $expectedCarrier) {
            // Assert false
            if ($carrier) {
                echo "\t\tFAIL $number (got $carrier, expected $expectedCarrier)\n";
            } else {
                echo "\t\tFAIL $number (no match, expected $expectedCarrier)\n";
            }
            $failures++;
        } else {
            // Assert true
            echo "\tPASS $number ($carrier)\n";
            $successes++;
        }
    }

    echo "\nRan $numTests tests, $failures failures and $successes successes.\n\n";
}

/**
 * Test tracking data retrieval.
 *
 * @param $testNumbers array An array of tracking numbers to test.
 */
function testDataRetrieval($testNumbers) {
    echo ">>> Now testing retrieval of tracking data:\n\n";

    $tracker = new ParcelTracker();

    $numTests = count($testNumbers);
    $failures = 0;
    $successes = 0;

    foreach ($testNumbers as $number) {
        $parcel = $tracker->getDetails($number);

        if (!$parcel) {
            // Assert false
            echo "\tFAIL $number\n";
            $failures++;
        } else {
            // Assert true
            echo "\tPASS $number\n";
            $successes++;

            // DEBUG
            //print_r($parcel);
        }
    }

    echo "\nRan $numTests tests, $failures failures and $successes successes.\n";
}
