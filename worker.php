<?
	require_once('config.php');
	require_once('database.php');
	require_once('vendor/autoload.php');
	require_once('helper.functions.php');

	$aftership = new AfterShip\Trackings($aftership_api_key);

	$sql = "SELECT * FROM packages WHERE aftership_id IS NOT NULL AND (delivery >= '".date("Y-m-d H:i:s", strtotime("7 days ago"))."' OR delivery IS NULL OR delivery ='0000-00-00 00:00:00' OR delivery_confirmed=0)";
	$result = @mysql_query($sql) or die (mysql_error());

	while($package = mysql_fetch_assoc($result))
	{
		if (isset($package['aftership_id']))
			$return = $aftership->get_by_id($package['aftership_id']);

		if (isset($return['data']['tracking']))
		{
			$shipment = array(
				"idx" => $package['idx'],
				"aftership_id" => $return['data']['tracking']['id'],
				"tracking" => $return['data']['tracking']['tracking_number'],
				"carrier" => $return['data']['tracking']['slug'],
				"shipped" => date('Y-m-d H:i:s', strtotime($return['data']['tracking']['created_at'])),
				"delivery" => date('Y-m-d H:i:s', strtotime($return['data']['tracking']['expected_delivery'])),
				"status" => $return['data']['tracking']['tag'],
				"method" => $return['data']['tracking']['shipment_type'],
			);

			if ($return['data']['tracking']['tag'] == "Delivered")
				$shipment['delivery_confirmed'] = 1;

			//print_r($return);

			record_shipment($shipment);
		}
	}
?>
