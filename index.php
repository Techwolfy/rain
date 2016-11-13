<?php
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
		$apiResult = curl_exec($apiCURL);
		curl_close($apiCURL);

		return json_decode($apiResult);
	}

	/*function getLatLon($zip) {
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
	}*/

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

	if(isset($_REQUEST["forecast"])) {
		//Get user's latitude and longitude
		//$latlon = getLatLon($zip);
		$latlon = array("latitude" => $_REQUEST["latitude"], "longitude" => $_REQUEST["longitude"]);
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
				"startdate" => date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")-$i)),
				"enddate" => date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y")-$i))
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
		//TODO: Check forecast for rain
		$chanceOfRain = ($numYearsWithRain / $numYears) * 100;
		//TODO: Display chance of rain and forecast
		//TODO: Extend to month view? Allow user to change date / location?

		$response = array(
			"chanceOfRain" => $chanceOfRain,
			"forecast" => $forecast,
			"numYears" => $numYears,
			"numYearsWithRain" => $numYearsWithRain,
			"numResults" => $numResults,
			"numStationsWithRain" => $numStationsWithRain
		);

		header("Content-Type: application/json");
		echo json_encode($response);
		die();
	}
?>

<html>

<head>
	<title>Rain Predictor</title>
	<link rel="stylesheet" href="bootstrap.css">
	<script>
		var latitude = null;
		var longitude = null;
		var forecast = null;
		var locationDiv = null;
		var locationButton = null;
		var submitButton = null;

		window.onload = function() {
			locationDiv = document.getElementById("location");
			locationButton = document.getElementById("locationButton");
			submitButton = document.getElementById("submitButton");
		}

		function getLocation() {
			if(navigator.geolocation) {
				locationButton.value = "Loading...";
				locationButton.disabled = "disabled";
				submitButton.disabled = "disabled";
				navigator.geolocation.getCurrentPosition(showPosition, showLocationError);
			} else {
				locationDiv.innerHTML = "Geolocation is not supported by this browser.<br>\nPlease use a newer browser.<br>";
			}
		}

		function showLocationError(error) {
			switch(error.code) {
				case error.PERMISSION_DENIED:
					locationDiv.innerHTML = "User denied the request for location data.<br>";
					break;
				case error.POSITION_UNAVAILABLE:
					locationDiv.innerHTML = "Location information is unavailable.<br>";
					break;
				case error.TIMEOUT:
					locationDiv.innerHTML = "The request for location data timed out.<br>";
					break;
				default:
					locationDiv.innerHTML = "An unknown error occurred.<br>";
					break;
			}
			locationDiv.innerHTML += "Please reload the page and try again.<br>";
		}

		function showPosition(position) {
			latitude = Math.round(position.coords.latitude * 100000) / 100000;
			longitude = Math.round(position.coords.longitude * 100000) / 100000;
			locationDiv.innerHTML = "<h4>Latitude: <span class=\"text-primary\">" + latitude + "</span></h4>\n" + "<h4>Longitude: <span class=\"text-primary\">" + longitude + "</span></h4>";

			locationButton.value = "Get Location";
			locationButton.disabled = "";
			submitButton.disabled = "";
		}

		function submit() {
			submitButton.value = "Loading...";
			submitButton.disabled = "disabled";

			var url = "?forecast&latitude=" + latitude + "&longitude=" + longitude;
			var xhr = new XMLHttpRequest();
			xhr.onreadystatechange = function() {
				if(xhr.readyState == XMLHttpRequest.DONE && xhr.status == 200) {
					forecast = JSON.parse(xhr.responseText);

					document.getElementById("chanceOfRain").innerHTML = forecast.chanceOfRain + "%";
					document.getElementById("chanceOfRainBar").style.width = forecast.chanceOfRain + "%";
					document.getElementById("chanceOfRainBarNegative").style.width = (100 - forecast.chanceOfRain) + "%";
					if(forecast.chanceOfRain == 0) {
						document.getElementById("chanceOfRain").className = "text-danger";
					} else {
						document.getElementById("chanceOfRain").className = "text-info";
					}

					document.getElementById("numYears").innerHTML = forecast.numYears;
					document.getElementById("numYearsWithRain").innerHTML = forecast.numYearsWithRain;
					document.getElementById("percentYearsWithRain").innerHTML = (forecast.numYearsWithRain / forecast.numYears) * 100;
					document.getElementById("numResults").innerHTML = forecast.numResults;
					document.getElementById("numStationsWithRain").innerHTML = forecast.numStationsWithRain;
					document.getElementById("forecast").innerHTML = JSON.stringify(forecast.forecast, null, 4);

					document.getElementById("forecastTable").innerHTML = "<tr><th>Date</th><th>Weather</th></tr>";
					for(var i = 0; i < forecast.forecast.data.weather.length; i++) {
						var row = document.getElementById("forecastTable").insertRow();
						row.insertCell(0).innerHTML = forecast.forecast.time.startPeriodName[i];
						row.insertCell(1).innerHTML = forecast.forecast.data.text[i];
					}

					submitButton.value = "Submit";
					submitButton.disabled = "";
				}
			}
			xhr.open("GET", url, true);
			xhr.send(null);
		}
	</script>
</head>

<body>

	 <div class="jumbotron text-center">
		<h1>Rain Predictor</h1>
		<div id="location" class="panel panel-info" style="display: inline-block; padding: 10px">
			<h4>Latitude: <span class="text-primary">--</span></h4>
			<h4>Longitude: <span class="text-primary">--</span></h4>
		</div>
		<p />
		<input id="locationButton" type="button" class="btn btn-primary" onclick="getLocation();" value="Get Location">
		<input id="submitButton" type="button"  class="btn btn-success" onclick="submit();" value="Submit" disabled="disabled">
	</div>
	<div class="container">
		<div class="row">
			<div class="col-sm-4">
				<h3>Results</h3>
				<p><span id="chanceOfRain">--%</span> change of rain!</p>
				<div class="progress">
					<div id="chanceOfRainBar" class="progress-bar"></div>
					<div id="chanceOfRainBarNegative" class="progress-bar progress-bar-danger"></div>
				</div>
			</div>
			<div class="col-sm-4">
				<h3>Stats</h3>
				<p>Number of years checked: <span id="numYears">--</span></p>
				<p>Number of years with rain: <span id="numYearsWithRain">--</span></p>
				<p>Percentage of years with rain: <span id="percentYearsWithRain">--</span></p>
			</div>
			<div class="col-sm-4">
				<h3>Stations</h3>
				<p>Number of stations checked (by year):<br><span id="numResults">--</span></p>
				<p>Number of stations with rain (by year):<br><span id="numStationsWithRain">--</span></p>
			</div>
		</div>
			<div class="row">
				<div class="col-sm-12">
					<h3>7-Day Forecast</h3>
					<table id="forecastTable" class="table table-striped">
						<tr><th>Date</th><th>Weather</th></tr>
					</table>
				</div>
			</div>
			<div class="row" style="display: none;">
				<div class="col-sm-12">
					<p><pre><span id="forecast">--</span></pre></p>
				</div>
			</div>
	</div>

</body>

</html>
