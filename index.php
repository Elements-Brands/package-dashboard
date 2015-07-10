<?
	/* You will need to create a 'config.php' that defines the following variables:
		$server // the database server
		$user // mysql user
		$password // mysql password
		$db_name // mysql database name
		$aftership_api_key // your AfterShip API key
	*/
	require_once('config.php');
	require_once('database.php');
	require_once('vendor/autoload.php');
	require_once('helper.functions.php');

	$messages = "";

	if ($_SERVER['REQUEST_METHOD'] == "POST")
	{
		$aftership = new AfterShip\Trackings($aftership_api_key);

		// got to do something before loading the page
		if (isset($_POST['markRec']))
		{
			$sql = "UPDATE packages SET delivery_confirmed=1, delivery='".date("Y-m-d H:i:s")."', status='Delivered' WHERE idx=".mysql_escape_string($_POST['markRec']);
			mysql_query($sql) or die (mysql_error());
			$messages .= '<div class="alert alert-success" role="alert">
					<strong>Package Marked Delivered.</strong> Further tracking updates will not be monitored.
				</div>';
		}
		if (isset($_POST['markDel']))
		{
			$sql = "DELETE FROM packages WHERE idx=".mysql_escape_string($_POST['markDel']);
			mysql_query($sql) or die (mysql_error());

			if (isset($_POST['aftership_id']) && !empty($_POST['aftership_id']))
				$aftership->delete_by_id($_POST['aftership_id']);

			$messages .= '<div class="alert alert-info" role="alert">
					<strong>Package Deleted.</strong> Tracking will no longer be checked.
				</div>';
		}
		if (isset($_POST['addShipment']))
		{
			if (!empty($_POST['trackingNumber']) && $_POST['toggleTracking'] == 'trackingTab')
			{
				// send this to aftership and process the response

				$tracking_info = array(
					"title" => $_POST['contents'], // an optional title for this shipment
				);
				$return = $aftership->create($_POST['trackingNumber'], $tracking_info);

				$shipment = array(
				    "idx" => 0, // force creation of new shipment
					"aftership_id" => $return['data']['tracking']['id'],
					"tracking" => $return['data']['tracking']['tracking_number'],
					"carrier" => $return['data']['tracking']['slug'],
					"shipped" => null,
					"delivery" => null,
					"status" => $return['data']['tracking']['tag'],
					"shipper" => $_POST['shipper'],
					"destination" => $_POST['destination'],
					"contents" => $_POST['contents'],
				);

				if ($return['data']['tracking']['tag'] == "Delivered")
					$shipment['delivery_confirmed'] = 1;
			}
			else
			{
				if(empty($_POST['expectShipDate']))
					$_POST['expectShipDate'] = date('Y-m-D H:i:s', strtotime('today'));
				else
					$_POST['expectShipDate'] = date('Y-m-D H:i:s', strtotime($_POST['expectShipDate']));

				$shipment = array(
				    "idx" => 0, // force creation of new shipment
					"shipped" => null,
					"delivery" => $_POST['expectShipDate'],
					"status" => "Awaiting Shipment",
					"shipper" => $_POST['shipper'],
					"destination" => $_POST['destination'],
					"contents" => $_POST['contents'],
				);
			}

			if (record_shipment($shipment))
			{
				$messages .= '<div class="alert alert-success" role="alert">
					<strong>Package Added.</strong> You can now find up-to-date tracking information in the table below.
				</div>';
			}
			else
			{
				$messages .= '<div class="alert alert-error" role="alert">
					<strong>Shipment Not Added.</strong> Something went wrong and your shipment could not be added. You may try again, or try to track it directly with the carrier.
				</div>';
			}
		}
	}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

		<title>Package Dashboard</title>

		<!-- Latest compiled and minified CSS -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">

		<!-- Optional theme -->
		<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">

		<!-- Custom styles for this template -->
		<!--<link href="theme.css" rel="stylesheet">-->

		<!-- Bootstrap core JavaScript -->
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js"></script>
		<!-- Latest compiled and minified JavaScript -->
		<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>

		<!-- Datepicker -->
		<link rel="stylesheet" href="css/bootstrap-datepicker.min.css">
		<script src="js/bootstrap-datepicker.min.js"></script>

	</head>

	<body role="document">

		<!-- Fixed navbar -->
		<nav class="navbar navbar-inverse navbar-fixed-top">
			<div class="container">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="#">Package Tracker</a>
				</div>
				<div id="navbar" class="navbar-collapse collapse">
					<ul class="nav navbar-nav">
						<li class="active"><a href="#">Home</a></li>
						<li><a href="#about" data-toggle="modal" data-target="#addShipmentModal">Add Package</a></li>
					</ul>
				</div><!--/.nav-collapse -->
			</div>
		</nav>

		<div class="container theme-showcase" role="main">

			<div class="page-header" style="padding-top: 15px">
				<h1>Important Packages</h1>
			</div>

			<?=$messages?>

			<div class="row">
				<div class="col-md-12">
					<table class="table table-striped">
						<thead>
							<tr>
								<th data-field="id" data-sortable="true">#</th>
								<th data-field="tracking" data-sortable="true">Tracking</th>
								<th data-field="from" data-sortable="true">From</th>
								<th data-field="to" data-sortable="true">To</th>
								<th data-field="contents" data-sortable="true">Contents</th>
								<th data-field="shipped" data-sortable="true">Shipped</th>
								<th data-field="delivery" data-sortable="true">Delivery</th>
								<th data-field="daysleft" data-sortable="true">Days Left</th>
								<th data-field="status" data-sortable="true">Status</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody><?
							$sql = "SELECT * FROM packages WHERE delivery_confirmed=0 OR delivery > DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY) ORDER BY TIMEDIFF(shipped, delivery) DESC";
							$result = mysql_query($sql) or die (mysql_error());

							while($package = mysql_fetch_assoc($result)) {
								if ($package['shipped'] == "0000-00-00 00:00:00" || (strtotime($package['shipped']) < strtotime("1 year ago")))
								{
									$package['shipped_formatted'] = "";
									$package['shipped'] = 0;
								}
								else
									$package['shipped_formatted'] = date('D, F jS, Y', strtotime($package['shipped']));

								if ($package['delivery'] == "0000-00-00 00:00:00" || (strtotime($package['delivery']) < strtotime("1 year ago")))
								{
									$package['delivery_formatted'] = "";
									$package['delivery'] = 0;
								}
								else
									$package['delivery_formatted'] = date('D, F jS, Y', strtotime($package['delivery']));

								// calculate days remaining
								if ($package['delivery'] != 0 && $package['shipped'] != 0)
									$package['days_remaining'] = (strtotime($package['delivery']) - strtotime("today")) / ( 60*60*24 );
								else
									$package['days_remaining'] = "?";


							?><tr>
								<td><?=$package['idx']?></td>
								<td>
									<? if (!empty($package['tracking'])) { ?>
									<a href="<?=get_tracking_url($package['tracking'])?>"><?=$package['tracking']?></a><br />(<?=strtoupper($package['carrier'])." ".ucwords(strtolower($package['method']))?>)</td>
									<? } else { ?>
									None Yet
									<? } ?>
								</td>
								<td><?=$package['shipper']?></td>
								<td><?=$package['destination']?></td>
								<td><?=$package['contents']?></td>
								<td><?=$package['shipped_formatted']?></td>
								<td><?=$package['delivery_formatted']?></td>
								<td><?=$package['days_remaining']?>
								<td><?=$package['status']?></td>
								<td>
									<form action="<?=htmlentities($_SERVER['PHP_SELF'])?>" method="POST">
										<input type="hidden" name="aftership_id" value="<?=$package['aftership_id']?>" />
										<? if (!$package['delivery_confirmed']) { ?>
										<button type="submit" name="markRec" value="<?=$package['idx']?>" class="btn btn-xs btn-success">Mark Received</button>
										<? } ?>
										<button type="button" name="edit" value="<?=$package['idx']?>" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#editPackageModal" data-whatever="">Edit</button>
										<button type="submit" name="markDel" value="<?=$package['idx']?>" class="btn btn-xs btn-danger">Delete</button>
									</form>
								</td>
							</tr>
						<?
							}
						?>
						</tbody>
					</table>
				</div>
			</div>

			<!-- Modal -->
			<script>
				$(document).ready(function() {
					$("#futureTab").hide();

					$("input[name$='toggleTracking']").click(function() {
						var test = $(this).val();
						$("#trackingTab").hide();
						$("#futureTab").hide();
						$("#" + test).show();
					});

					$('#futureTab input').datepicker({
					    autoclose: true,
					    todayHighlight: true
					});
				});

			
			</script>

			<!-- Add shipment modal -->
			<div class="modal fade" id="addShipmentModal" tabindex="-1" role="dialog" aria-labelledby="addShipmentModalLabel">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title" id="addShipmentModalLabel">Add a Shipment</h4>
						</div>
						<form class="form-horizontal" action="<?=htmlentities($_SERVER['PHP_SELF'])?>" method="POST">
							<div class="modal-body">
								<div class="form-group">
									<fieldset>
										<div class="col-sm-4 col-md-offset-3">
											<input type="radio" name="toggleTracking" value="trackingTab" checked />
											<label for="login"> Already Shipped</label>
										</div>
										<div class="col-sm-4">
											<input type="radio" name="toggleTracking" value="futureTab" />
											<label for="register"> Future Shipment</label>
										</div>
									</fieldset>
								</div>
								<div class="form-group" id="trackingTab">
									<label for="trackingNumber" class="col-sm-3 control-label">Tracking Number</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="trackingNumber" id="trackingNumber" placeholder="USPS, UPS, FedEx, and many others supported">
									</div>
								</div>
								<div class="form-group" id="futureTab">
									<label for="expectShipDate" class="col-sm-3 control-label">Expected Shipping Date</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="expectShipDate" id="expectShipDate" placeholder="When do we expect this to ship?">
									</div>
								</div>
								<div class="form-group">
									<label for="shipper" class="col-sm-3 control-label">Shipper/From</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="shipper" id="shipper" placeholder="Who mailed this?">
									</div>
								</div>
								<div class="form-group">
									<label for="destination" class="col-sm-3 control-label">Destination/To</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="destination" id="destination" placeholder="Who is receiving it?">
									</div>
								</div>
								<div class="form-group">
									<label for="contents" class="col-sm-3 control-label">Contents</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="contents" id="contents" placeholder="What's inside?">
									</div>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
								<button type="submit" name="addShipment" class="btn btn-primary">Add Shipment</button>
							</div>
						</form>
					</div>
				</div>
			</div>

			<!-- Edit shipment modal -->
			<div class="modal fade" id="editPackageModal" tabindex="-1" role="dialog" aria-labelledby="editPackageModalLabel">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title" id="editPackageModalLabel">Edit Shipment</h4>
						</div>
						<form class="form-horizontal" action="<?=htmlentities($_SERVER['PHP_SELF'])?>" method="POST">
							<div class="modal-body">
								<div class="form-group">
									<fieldset>
										<div class="col-sm-4 col-md-offset-3">
											<input type="radio" name="toggleTrackingEdit" value="trackingTab" checked />
											<label for="login"> Already Shipped</label>
										</div>
										<div class="col-sm-4">
											<input type="radio" name="toggleTrackingEdit" value="futureTab" />
											<label for="register"> Future Shipment</label>
										</div>
									</fieldset>
								</div>
								<div class="form-group" id="trackingTab">
									<label for="trackingNumber" class="col-sm-3 control-label">Tracking Number</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="trackingNumberEdit" id="trackingNumberEdit" placeholder="USPS, UPS, FedEx, and many others supported">
									</div>
								</div>
								<div class="form-group" id="futureTab">
									<label for="expectShipDate" class="col-sm-3 control-label">Expected Shipping Date</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="expectShipDateEdit" id="expectShipDateEdit" placeholder="When do we expect this to ship?">
									</div>
								</div>
								<div class="form-group">
									<label for="shipper" class="col-sm-3 control-label">Shipper/From</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="shipperEdit" id="shipperEdit" placeholder="Who mailed this?">
									</div>
								</div>
								<div class="form-group">
									<label for="destination" class="col-sm-3 control-label">Destination/To</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="destinationEdit" id="destinationEdit" placeholder="Who is receiving it?">
									</div>
								</div>
								<div class="form-group">
									<label for="contents" class="col-sm-3 control-label">Contents</label>
									<div class="col-sm-9">
										<input type="text" class="form-control" name="contentsEdit" id="contentsEdit" placeholder="What's inside?">
									</div>
								</div>
							</div>
							<div class="modal-footer">
								<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
								<button type="submit" name="editShipment" class="btn btn-primary">Edit Shipment</button>
							</div>
						</form>
					</div>
				</div>
			</div>

		</div> <!-- /container -->

	</body>
</html>
