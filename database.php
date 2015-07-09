<?php
	require_once('config.php');
	$link = mysql_connect($server, $user, $password) or die('Could not connect: ' . mysql_error());
	mysql_select_db($db_name, $link) or die (" Database not found.");

	function mysql_insert_assoc ($my_table, $my_array) {
		//
		// Insert values into a MySQL database
		// Includes quote_smart code to foil SQL Injection
		//
		// a call to this function of:
		//
		//  $val1 = "foobar";
		//  $val2 = 495;
		//  mysql_insert_assoc("tablename", array(col1=>$val1, col2=>$val2, col3=>"val3", col4=>720, col5=>834.987));
		//
		// Sends the following query:
		//  INSERT INTO 'tablename' (col1, col2, col3, col4, col5) values ('foobar', 495, 'val3', 720, 834.987)

	   // Find all the keys (column names) from the array $my_array
	   $columns = array_keys($my_array);

	   // Find all the values from the array $my_array
	   $values = array_values($my_array);

	   // quote_smart the values
	   $values_number = count($values);
	   for ($i = 0; $i < $values_number; $i++)
		 {
		 $value = $values[$i];
		 if (get_magic_quotes_gpc()) { $value = stripslashes($value); }
		 $value = "'" . mysql_real_escape_string($value) . "'";
		 $values[$i] = $value;
		 }

	   // Compose the query
	   $sql = "INSERT INTO $my_table ";

	   // create comma-separated string of column names, enclosed in parentheses
	   $sql .= "(" . implode(", ", $columns) . ")";
	   $sql .= " values ";

	   // create comma-separated string of values, enclosed in parentheses
	   $sql .= "(" . implode(", ", $values) . ")";

	   $result = @mysql_query ($sql) OR die ("Unsuccessful: " . mysql_error());

	   return ($result) ? true : false;
	}
?>