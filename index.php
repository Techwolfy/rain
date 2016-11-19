<?php
	define("FORECAST_PHP", true);
	require("forecast.php");
?>

<html>

<head>
	<title>Rain Predictor</title>
	<link rel="stylesheet" href="css/styles.css">
	<link rel="stylesheet" href="css/bootstrap.css">
	<script src="js/rain.js"></script>
</head>

<body>

	 <div class="jumbotron text-center">
		<h1>Rain Predictor</h1>
		<div id="location" class="panel panel-info" style="display: inline-block; padding: 10px">
			<h4>Latitude: <span class="text-primary">--</span></h4>
			<h4>Longitude: <span class="text-primary">--</span></h4>
		</div>
		<p />
		<button id="locationButton" class="btn btn-primary" onclick="getLocation();">Get Location</button>
		<button id="submitButton" class="btn btn-success" onclick="submit();" disabled="disabled">Submit</button>
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
				<p>Number of years with rain (current day): <span id="numYearsWithRain">--</span></p>
				<p>Percentage of years with rain (current day): <span id="percentYearsWithRain">--%</span></p>
			</div>
			<div class="col-sm-4">
				<h3>Stations</h3>
				<p>Total number of station data points checked: <span id="numResults">--</span></p>
				<p>Total number of station data points with rain: <span id="numStationsWithRain">--</span></p>
			</div>
		</div>
			<div class="row">
				<div class="col-sm-12">
					<h3>7-Day Forecast</h3>
					<table id="forecastTable" class="table table-striped">
						<tr><th>Date</th><th>Rain Prediction</th><th>NOAA Weather</th></tr>
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
