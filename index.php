<?php
	if($_REQUEST["key"] != "bqcauiehofuioasnfi") {
		die();
	}

	function api($endpoint, $query) {
		//API access details
		$apikey = "PUT_NOAA_API_KEY_HERE";
		$apiURL = "http://www.ncdc.noaa.gov/cdo-web/api/v2/";

		//Build API request
		$apiCURL = curl_init();
		curl_setopt($apiCURL, CURLOPT_HTTPHEADER, array("token:".$apikey));
		curl_setopt($apiCURL, CURLOPT_URL, $apiURL.$endpoint."?".$query);
		curl_setopt($apiCURL, CURLOPT_RETURNTRANSFER, true);

		//Send API request
global $apiResult;
		$apiResult = curl_exec($apiCURL);
		curl_close($apiCURL);

		return json_decode($apiResult);
	}

	function getLatLon($zip) {
		$ndfdURL = "http://graphical.weather.gov/xml/sample_products/browser_interface/ndfdXMLclient.php";
		$ndfdParams = array("listZipCodeList" => $zip);

		//Build API request
		$ndfdCURL = curl_init();
		curl_setopt($ndfdCURL, CURLOPT_URL, $ndfdURL."?".http_build_query($ndfdParams));
		curl_setopt($ndfdCURL, CURLOPT_RETURNTRANSFER, true);

		//Send API request
		$ndfdResult = curl_exec($ndfdCURL);
		curl_close($ndfdCURL);

		$xml = simplexml_load_string($ndfdResult);

		return explode(",", $xml->latLonList);
	}

	function getForecast($lat, $lon) {
		$fcURL = "http://forecast.weather.gov/MapClick.php";
		$fcParams = array(
			"lat" => $lat,
			"lon" => $lon,
			"unit" => 0,	//0=imperial, 1=metric
			"FcstType" => "json"
		);

		//Build API request
		$fcCURL = curl_init();
		curl_setopt($fcCURL, CURLOPT_URL, $fcURL."?".http_build_query($fcParams));
		curl_setopt($fcCURL, CURLOPT_USERAGENT, "Mozilla/5.0");		//Fails when curl's user agent is used
		curl_setopt($fcCURL, CURLOPT_RETURNTRANSFER, true);

		//Send API request
		$fcResult = curl_exec($fcCURL);
		curl_close($fcCURL);

		return json_decode($fcResult);
	}

	//Get user's latitude and longitude
	//$latlon = getLatLon($zip);
	$latlon = array("latitude" => $_POST["latitude"], "longitude" => $_POST["longitude"]);
	$latlonMargin = 0.1;

	//Get current forecast station
	$forecast = getForecast($latlon["latitude"], $latlon["longitude"]);

	//Get station IDs
	$stationQuery = array(
		"datasetid" => "GHCND",
		"datacategoryid" => "PRCP",
		"extent" =>
			($latlon["latitude"]-$latlonMargin).",".
			($latlon["longitude"]-$latlonMargin).",".
			($latlon["latitude"]+$latlonMargin).",".
			($latlon["longitude"]+$latlonMargin),
		"startdate" => "2015-11-07",
		"enddate" => "2015-11-07"
	);
	$stations = api("stations", http_build_query($stationQuery));
	$stationList = "";
	foreach($stations->results as $record) {
		$stationList .= "&stationid=".$record->id;
	}

	//Get rainfall data for last 4 years
	$numYears = 4;
	$result = array();
	for($i = 1; $i <= $numYears; $i++) {
		$rainQuery = array(
			"datasetid" => "GHCND",
			"datatypeid" => "PRCP",
			"startdate" => date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d"), date("Y")-$i)),
			"enddate" => date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d"), date("Y")-$i)),
			"limit" => 10,
			"includemetadata" => true
		);
		$result[$i-1] = api("data", http_build_query($rainQuery).$stationList);
	}

	//Calculate probability
	$numYearsWithRain = 0;
	$numResults = array();
	$numStationsWithRain = array();
	$yearNum = 0;
	foreach($result as $year) {
		$numResults[$yearNum] = 0;
		$numStationsWithRain[$yearNum] = 0;
		foreach($year->results as $data) {
			$numResults[$yearNum]++;
			if($data->value > 0 && $data->value != 99999) {
				$numStationsWithRain[$yearNum]++;
			}
		}
		if($numResults[$yearNum] > 0) {
			if($numStationsWithRain[$yearNum] > 0) {
				$numYearsWithRain++;
			}
			$yearNum++;
		}
	}

	//Predict whether it will rain or not
	//TODO: Check forecast
	$chanceOfRain = ($numYearsWithRain / $numYears) * 100;
?>

<html>

<head>
	<title>Rain Predictor</title>
		<style>
			@import url(http://fonts.googleapis.com/css?family=Roboto);

			body {
				background-color: #222222;
				color: #DDDDDD;
				margin: 10px;
				font-family: "Roboto", "Arial", sans-serif;
			}

			table, th, td {
				border: 2px solid #DDDDDD;
				border-collapse: collapse;
				padding: 10px;
			}

		</style>
		<script>
			function getLocation() {
				if(navigator.geolocation) {
					navigator.geolocation.getCurrentPosition(showPosition);
				} else {
					document.getElementById("location").innerHTML = "Geolocation is not supported by this browser.<br>Please use a newer browser.<br>";
					document.getElementById("submit").disabled = "disabled";
				}
			}

			function showPosition(position) {
				var latitude = Math.round(position.coords.latitude * 100000) / 100000;
				var longitude = Math.round(position.coords.longitude * 100000) / 100000;
				document.getElementById("location").innerHTML = "Latitude: " + latitude + "<br>Longitude: " + longitude + "<br>";
				document.getElementById("latitude").value = latitude;
				document.getElementById("longitude").value = longitude;
			}
		</script>
</head>

<body>

	<form id="locationForm" action="" method="post">
		<input id="latitude" name="latitude" type="hidden" value="<?php echo $_POST["latitude"]; ?>">
		<input id="longitude" name="longitude" type="hidden" value="<?php echo $_POST["longitude"]; ?>">
		<div id="location">Latitude: <?php echo $_POST["latitude"]; ?><br>Longitude: <?php echo $_POST["longitude"]; ?><br></div>
		<input type="button" onclick="getLocation();" value="Get Location">
		<input id="submit" type="submit" value="Submit">
	</form>
	<p>

	Results:<br>
	<?php echo $chanceOfRain; ?>% change of rain!<br>
	<hr>
	Number of years checked: <?php echo $numYears; ?><br>
	Number of years with rain: <?php echo $numYearsWithRain; ?><br>
	Percentage of years with rain: <?php echo ($numYearsWithRain / $numYears) * 100; ?><br>
	<hr>
	Number of stations checked (by year): <?php print_r($numResults); ?><br>
	Number of stations with rain (by year): <?php print_r($numStationsWithRain); ?><br>
	<hr>
	<!--<pre><?php print_r($result); ?></pre>-->
	<hr>
	<!--<pre><?php echo $stationList; ?></pre>-->
	<hr>
	<!--<pre><?php print_r($latlon); ?></pre>-->
	<hr>
	<!--<pre><?php print_r($forecast); ?></pre>-->
	<hr>

</body>

</html>
