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
		locationButton.innerHTML = "<span class=\"glyphicon glyphicon-cog animate-spin\"></span> Loading...";
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

	locationButton.innerHTML = "Get Location";
	locationButton.disabled = "";
	submitButton.innerHTML = "Submit";
	submitButton.className = "btn btn-success";
	submitButton.disabled = "";
}

function submit() {
	submitButton.innerHTML = "<span class=\"glyphicon glyphicon-cog animate-spin\"></span> Loading...";
	submitButton.className = "btn btn-success";
	submitButton.disabled = "disabled";

	var url = "?forecast&latitude=" + latitude + "&longitude=" + longitude;
	var xhr = new XMLHttpRequest();
	xhr.onreadystatechange = function() {
		if(xhr.readyState == XMLHttpRequest.DONE && xhr.status == 200) {
			forecast = JSON.parse(xhr.responseText);

			if(forecast.error == true) {
				submitButton.innerHTML = "An error has ocurred. Please try again later.";
				submitButton.className = "btn btn-danger";
				submitButton.disabled = "disabled";
				return;
			}

			document.getElementById("chanceOfRain").innerHTML = forecast.chanceOfRain[0] + "%";
			document.getElementById("chanceOfRainBar").style.width = forecast.chanceOfRain[0] + "%";
			document.getElementById("chanceOfRainBarNegative").style.width = (100 - forecast.chanceOfRain[0]) + "%";
			if(forecast.chanceOfRain[0] == 0) {
				document.getElementById("chanceOfRain").className = "text-danger";
			} else {
				document.getElementById("chanceOfRain").className = "text-info";
			}

			document.getElementById("numYears").innerHTML = forecast.numYears;
			document.getElementById("numYearsWithRain").innerHTML = forecast.numYearsWithRain[0];
			document.getElementById("percentYearsWithRain").innerHTML = ((forecast.numYearsWithRain[0] / forecast.numYears) * 100) + "%";
			document.getElementById("numResults").innerHTML = forecast.numResults.reduce(function(a,b){return a.concat(b);}).reduce(function(a,b){return a+b;});	//Multidimentional array sum
			document.getElementById("numStationsWithRain").innerHTML = forecast.numStationsWithRain.reduce(function(a,b){return a.concat(b);}).reduce(function(a,b){return a+b;});	//Multidimentional array sum
			document.getElementById("forecast").innerHTML = JSON.stringify(forecast.forecast, null, 4);

			document.getElementById("forecastTable").innerHTML = "<tr><th>Date</th><th>Rain Prediction</th><th>NOAA Weather</th></tr>";
			for(var i = 0; i < forecast.forecast.data.weather.length; i++) {
				var row = document.getElementById("forecastTable").insertRow();
				row.insertCell(0).innerHTML = forecast.forecast.time.startPeriodName[i];

				var rainCell = row.insertCell(1);
				var chanceIndex = Math.floor(i / 2);
				if(forecast.forecast.time.startPeriodName[1] != "Tonight") {
					chanceIndex = Math.floor((i + 1) / 2);
				}

				rainCell.innerHTML = forecast.chanceOfRain[chanceIndex] + "%";
				if(forecast.chanceOfRain[chanceIndex] == 0) {
					rainCell.className = "text-danger";
				} else {
					rainCell.className = "text-info";
				}

				row.insertCell(2).innerHTML = forecast.forecast.data.text[i];
			}

			submitButton.innerHTML = "Submit";
			submitButton.disabled = "";
		}
	}
	xhr.open("GET", url, true);
	xhr.send(null);
}
