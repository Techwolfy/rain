<?php
	//PHP include guard (prevents direct access)
	if(!defined("FORECAST_PHP")) {
		http_response_code(301);
		header("Location: .");
	}

	//https://www.ncdc.noaa.gov/cdo-web/webservices/v2#data
	function api($endpoint, $query) {
		//API access details
		$apikey = "PUT_NOAA_API_KEY_HERE";
		$apiURL = "https://www.ncdc.noaa.gov/cdo-web/api/v2/";

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
		$numDays = 8;
		$result = array();
		for($i = 1; $i <= $numYears; $i++) {
			$rainQuery = array(
				"datasetid" => "GHCND",
				"datatypeid" => "PRCP",
				"startdate" => date("Y-m-d", mktime(0, 0, 0, date("m"), date("d"), date("Y") - $i)),
				"enddate" => date("Y-m-d", mktime(0, 0, 0, date("m"), date("d") + $numDays, date("Y") - $i)),
				"limit" => 100
			);
			$result[$i-1] = api("data", http_build_query($rainQuery).$stationList);
		}

		//Calculate probability
		$numYearsWithRain = array_fill(0, $numDays, 0);
		$numResults = array();
		$numStationsWithRain = array();
		$yearNum = 0;
		$currentDate = date_create();
		foreach($result as $year) {
			$numResults[$yearNum] = array_fill(0, $numDays, 0);
			$numStationsWithRain[$yearNum] = array_fill(0, $numDays, 0);
			$currentDate->modify("-1 year");

			foreach($year->results as $station) {
				$dayNum = date_diff($currentDate, date_create($station->date))->format("%a");
				$numResults[$yearNum][$dayNum]++;
				if($station->value > 0 && $station->value != 99999) {
					$numStationsWithRain[$yearNum][$dayNum]++;
				}
			}

			for($day = 0; $day < $numDays; $day++) {
				if(isset($numResults[$yearNum][$day]) && $numResults[$yearNum][$day] > 0) {
					if(isset($numStationsWithRain[$yearNum][$day]) && $numStationsWithRain[$yearNum][$day] > 0) {
						$numYearsWithRain[$day]++;
					}
				}
			}

			$yearNum++;
		}

		//Predict whether it will rain or not
		$chanceOfRain = array();
		for($i = 0; $i < $numDays; $i++) {
			if(!isset($numYearsWithRain[$i])) {
				$numYearsWithRain[$i] = 0;
			}
			$chanceOfRain[$i] = ($numYearsWithRain[$i] / $numYears) * 100;
		}

		$response = array(
			"chanceOfRain" => $chanceOfRain,
			"forecast" => $forecast,
			"numYears" => $numYears,
			"numYearsWithRain" => $numYearsWithRain,
			"numResults" => $numResults,
			"numStationsWithRain" => $numStationsWithRain,
			"raw" => $result
		);

		header("Content-Type: application/json");
		echo json_encode($response);
		die();
	}
?>
