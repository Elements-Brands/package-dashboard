<?php
function get_tracking_url($tracking_number) {
	$php_tracking_urls = array(
		array(
			'url'=>'http://wwwapps.ups.com/WebTracking/processInputRequest?TypeOfInquiryNumber=T&InquiryNumber1=',
			'reg'=>'/\b(1Z ?[0-9A-Z]{3} ?[0-9A-Z]{3} ?[0-9A-Z]{2} ?[0-9A-Z]{4} ?[0-9A-Z]{3} ?[0-9A-Z]|[\dT]\d\d\d ?\d\d\d\d ?\d\d\d)\b/i'
		),
		array(
			'url'=>'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=',
			'reg'=>'/\b((420 ?\d\d\d\d\d ?)?(91|94|01|03|04|70|23|13)\d\d ?\d\d\d\d ?\d\d\d\d ?\d\d\d\d ?\d\d\d\d( ?\d\d)?)\b/i'
		),
		array(
			'url'=>'https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1=',
			'reg'=>'/\b((M|P[A-Z]?|D[C-Z]|LK|EA|V[A-Z]|R[A-Z]|CP|CJ|LC|LJ) ?\d\d\d ?\d\d\d ?\d\d\d ?[A-Z]?[A-Z]?)\b/i'
		),
		array(
			'url'=>'http://www.fedex.com/Tracking?language=english&cntry_code=us&tracknumbers=',
			'reg'=>'/\b((96\d\d\d\d\d ?\d\d\d\d|96\d\d|\d\d\d\d) ?\d\d\d\d ?\d\d\d\d( ?\d\d\d)?)\b/i'
		),
		array(
			'url'=>'http://www.ontrac.com/trackres.asp?tracking_number=',
			'reg'=>'/\b(C\d\d\d\d\d\d\d\d\d\d\d\d\d\d)\b/i'
		),
		array(
			'url'=>'http://www.dhl.com/content/g0/en/express/tracking.shtml?brand=DHL&AWB=',
			'reg'=>'/\b(\d\d\d\d ?\d\d\d\d ?\d\d)\b/i'
		),
	);
	foreach ($php_tracking_urls as $item) {
		$match = array();
		preg_match($item['reg'], $tracking_number, $match);
		if (count($match)) return $item['url'] . $match[0];
	}
	return false;
}

function record_shipment($shipment_array)
{
    global $db;

    $sql = "SELECT * FROM packages WHERE idx=".mysql_escape_string($shipment_array['idx']);
    $result = @mysql_query ($sql) OR die ("Unsuccessful: " . mysql_error());

    if (mysql_num_rows($result) == 0) {
        unset($shipment_array['idx']);
        mysql_insert_assoc("packages", $shipment_array);
        return true;
    }
    else {
        $sql = 'UPDATE packages SET ';

        foreach ($shipment_array as $key => $value) {
            $value = mysql_escape_string($value);
            $sql .= "`$key` = \"$value\", ";
        }

        $sql = substr($sql, 0, strlen($sql)-2);
        $sql .= " WHERE ";

		// now match on idx
		$sql .= "idx=".$shipment_array['idx'];

		$result = @mysql_query ($sql) OR die ("Unsuccessful: " . mysql_error());
		return ($result) ? true : false;
    }

    // returning true for new order inserted, false for updated

}
