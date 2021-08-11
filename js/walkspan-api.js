
var app = {
	AerialLayer: null,
	APIKey: '2A34FD93387559BD55AF9476D9E33',
	APIUrl: './php/api.php',
	FirstLoad: true,
	Geocoder: null,
	LastSelectedLat: null,
	LastSelectedLng: null,
	Map: null,
	MapInfoWindow: null,
	Neighborhoods: [],
	NeighborhoodLayer: null,
	PlacesMarkers: [],
	PlacesService: null,
	Ratings: [],
	SelectedAddress: null,
	SelectedAreaType: 'quarter-mile',
	SelectedLat: 40.736, 
	SelectedLng: -74.006,
	SelectedLocationCircles: [],
	SelectedLocationMarker: null,
	SelectedNeighborhoodId: null,
	StreetLayer: null,
	StreetViewPanorama: null,
	StreetViewService: null,
	WalkspanLayer: null,
	WalkspanValue: 'total'
};

app.LayerColors = {
	'access': [ '#D3EAF5', '#94CCE6', '#4CAAD6' ],
	'amenities': [ '#FDDBD1', '#F9A78F', '#F56C44' ],
	'beauty_m': [ '#EDE2D8', '#D4B9A1', '#B78B63' ],
	'beauty_n': [ '#DCF2DA', '#B5E7B6', '#64CD68' ],
	'comfort': [ '#EEE3C7', '#D5BB78', '#B98E1E' ],
	'interest': [ '#EBDDF3', '#D0ACE3', '#B075D0' ],
	'safety': [ '#F6DCE3', '#E9A9BC', '#DB708F' ],
	'total': [ '#E4E4E4', '#BEBEBE', '#929292' ] 
};

app.Places = {
	'Food': ['bakery', 'bar', 'cafe', 'restaurant'],
	'Stores': ['book_store', 'clothing_store', 'convenience_store', 'department_store', 'hardware_store',
			'home_goods_store', 'liquor_store', 'pet_store', 'pharmacy', 'shopping_mall',
			'store', 'supermarket'],
	'Transits': ['bus_station', 'subway_station', 'taxi_stand', 'transit_station'],
	'Health': ['dentist', 'doctor', 'gym'],
	'Public Services': ['church', 'library', 'school'],
	'Others': ['atm', 'bank', 'beauty_salon', 'hair_care', 'laundry', 'movie_theater']
};

app.PlacesColors = {
	'Food': 'orange',
	'Stores': 'pink',
	'Transits': 'blue',
	'Health': 'yellow',
	'Public Services': 'green',
	'Others': 'grey'
};

$(document).ready(function () {

	initializeAddressInput();
	
	app.Geocoder = new google.maps.Geocoder;
	
	initializeMap();
	reverseGeocodeCurrentLocation();
	getRatings(app.SelectedAreaType, app.SelectedLat, app.SelectedLng);
	initializeTabs();
	
	if (Storage) {
		var walkspanTheme = localStorage.getItem('walkspan-theme');
		if (walkspanTheme == 'dark-gray') applyDarkTheme();
	}
	
	window.addEventListener('message', function(event) { 
		
		// Only process messages if they come from trusted sources
		if (/senseofwalk.com$/.test(event.origin)) { 
		
			if (Storage) localStorage.setItem('walkspan-theme', 'dark-gray');
			
			if (event.data.location == 'main-view') {
				gotoMainView();
			} else if (event.data.location == 'request-key') {
				gotoRequestKey();
			}
		}
	}); 
});

function initializeAddressInput () {

	// Initialize Google Places API autocomplete
	setTimeout(function () {
		
		var searchInput = $('.wapi-search');
		if (searchInput.length == 0) return;
		
		var acOptions = {
			bounds: new google.maps.LatLngBounds(
				new google.maps.LatLng(40.700967, -74.027539),
				new google.maps.LatLng(40.879325, -73.872797)
			)
		};
		
		var autocomplete = new google.maps.places.Autocomplete(searchInput[0], acOptions);
		
		var acl = autocomplete.addListener('place_changed', function() {
			
			var place = autocomplete.getPlace();
			
			if (! place.geometry) return;
			
			app.LastSelectedLat = app.SelectedLat;
			app.LastSelectedLng = app.SelectedLng;
			app.SelectedLat = place.geometry.location.lat();
			app.SelectedLng = place.geometry.location.lng();

			/*
			// Clear out existing places markers
			for (var i = 0; i < app.PlacesMarkers.length; i++) {
				app.PlacesMarkers[i].setMap(null);
			}
			
			for (p in app.Places) {
				var request = {
					location: new google.maps.LatLng(app.SelectedLat, app.SelectedLng),
					places: p,
					radius: ((app.SelectedAreaType == 'half-mile') ? 804.672 : 402.336), // Quarter-mile in meters
					types: app.Places[p]
				};
				
				searchPlaces(request);
			}
			*/
			
			getRatings (app.SelectedAreaType, app.SelectedLat, app.SelectedLng);
		});
	}, 100);
}

function initializeTabs() {
	
	$('.walkspan-layer-tabs .tab-link').each(function () {
		if (! $(this).attr('hoverbox')) return;
		
		$(this).bind('mouseover', function () {
			$(this).append('<div class="wapi-hoverbox">' + $(this).attr('hoverbox') + '</div>');
		});
	
		$(this).bind('mouseout', function () {
			$(this).find('.wapi-hoverbox').remove();
		});
	});
}

function selectArea (event, value) {
	
	if (app.NeighborhoodLayer) {
		for (var i = 0; i < app.NeighborhoodLayer.length; i++) {
			app.Map.data.remove(app.NeighborhoodLayer[i]);
		}
	}
	app.SelectedAreaType = value;
	$(event.currentTarget).addClass('selected')
		.siblings().removeClass('selected');
	
	app.LastSelectedLat = app.SelectedLat;
	app.LastSelectedLng = app.SelectedLng;
	
	if (app.SelectedAreaType == 'neighborhood') {
		getNeighborhood();
	} else {
		app.SelectedNeighborhood = null;
	}
	
	reverseGeocodeCurrentLocation();
	getRatings(app.SelectedAreaType, app.SelectedLat, app.SelectedLng);
}

function drawRatings (ratingsData) {

	var slices = [];
	
	if ((! isDefined(ratingsData)) || (ratingsData.length == 0)) return;
		
	for (var i = 0; i < ratingsData.length; i++) {
		var value = Number(ratingsData[i].Value) / 5;
		var label = '';
		
		switch(ratingsData[i].Name.toLowerCase()) {
			case 'beautyn': label = 'Nature'; break;
			case 'beautym': label = 'Architecture'; break;
			case 'comfort': label = 'Comfort'; break;
			case 'interest': label = 'Interest'; break;
			case 'safety': label = 'Safety'; break;
			case 'access': label = 'Access'; break;
			case 'amenities': label = 'Amenities'; break;
			case 'total': continue; break;
		}
		
		slices.push({ 
			angle: Math.PI * (2 / ratingsData.length),
			color: '#4CA075',
			name: label,
			value: value
		});
	}
	
	// Set right side graph title
	$('.wapi-right-selected-area').text(app.SelectedAreaType.replace(/\-/gi, ' '));
	
	var canvas = $('.wapi-graph-canvas')[0];
	var context = $('.wapi-graph-canvas')[0].getContext('2d');
	
	context.clearRect(0, 0, canvas.width, canvas.height);
	
	var beginAngle = 0;
	var endAngle = 0;
	var outerRadius = 120;

	for (var i = 0; i < slices.length; i++) {
		
		beginAngle = endAngle;
		endAngle = endAngle + slices[i].angle;
		
		// Draw background segment
		context.save();
		context.fillStyle = '#F0F0F0';
		context.translate(canvas.width / 2, canvas.height / 2);
		context.beginPath();
		context.arc(0, 0, outerRadius, beginAngle, endAngle);
		context.lineTo(0, 0);
		context.fill();
		context.restore();
		
		// Draw value segment
		context.save();
		context.translate(canvas.width / 2, canvas.height / 2);
		context.beginPath();
		context.fillStyle = slices[i].color;
		context.arc(0, 0, (slices[i].value * outerRadius), beginAngle, endAngle);
		context.lineTo(0, 0);
		context.closePath();
		context.fill();
		context.restore();
		
		// Draw text along arc
		context.save();
		context.translate(canvas.width / 2, canvas.height / 2);
		context.rotate(endAngle + 0.75);
		//context.rotate(endAngle); // To show all metrics
		context.font = '12px Calibri';
		context.fillStyle = '#808080';
		context.strokeStyle = '#808080';
		context.lineWidth = 4;
		drawTextAlongArc(
			context, 
			slices[i].name, 
			0, 
			0, 
			(outerRadius + 5), 
			slices[i].angle * (1.6 / Math.pow(slices[i].name.length, 0.3))
			//slices[i].angle * (1.1 / Math.pow(slices[i].name.length, 0.13)) // To show all metrics
		);
		context.restore();
	}
	
	beginAngle = 0;
	endAngle = 0;
	
	context.strokeStyle = '#FFF';
	context.lineWidth = 4;
	
	x1 = canvas.width / 2;
	y1 = canvas.height / 2;
	
	// Draw white overlay line(s)
	for (var i = 0; i < slices.length; i++) {
		
		beginAngle = endAngle;
		endAngle = endAngle + slices[i].angle;

		context.save();
		context.beginPath();
		context.moveTo(x1, y1);
		context.lineTo(x1 + (outerRadius * Math.cos(endAngle)), y1 + (outerRadius * Math.sin(endAngle)));
		context.stroke();
		context.closePath();
		context.restore();
	}
	
	// Draw over gradient
	context.lineWidth = 10;
	for (var i = 0; i < 30; i++) {
		context.save();
		context.strokeStyle = 'rgba(255, 255, 255, ' + (1 - (i * 0.10)) + ')';
		context.beginPath();
		context.arc(canvas.width / 2, canvas.height / 2, (i * 10), 0, 2 * Math.PI);
		context.stroke();
		context.closePath();
		context.restore();
	}
	
	// Draw large rings
	context.lineWidth = 3;
	for (var i = 0; i < 8; i++) {
		context.save();
		context.strokeStyle = 'rgba(255, 255, 255, 0.65)';
		context.beginPath();
		context.arc(canvas.width / 2, canvas.height / 2, (i * 16), 0, 2 * Math.PI);
		context.stroke();
		context.closePath();
		context.restore();
	}
}

function drawTextAlongArc(context, str, centerX, centerY, radius, angle) {
	
	var s = null;
	
	context.translate(centerX, centerY);
	context.rotate(-1 * angle / 2);
	context.rotate(-1 * (angle / str.length) / 2);
	
	for (var n = 0; n < str.length; n++) {
		context.rotate(angle / str.length);
		context.save();
		context.translate(0, -1 * radius);
		s = str[n];
		context.fillText(s, 0, 0);
		context.restore();
	}
}

function fillTotalRatings () {

	// Default styles for all the tab scores
	$('.walkspan-area-tabs .tab-link .tab-score')
		.css({ 'background-color': '#FFF', 'color': '#222' });
	
	for (var i = 0; i < app.Ratings.length; i++) {
		
		var area = ((app.Ratings[i]['area'].toLowerCase() != 'half-mile') 
			&& (app.Ratings[i]['area'].toLowerCase() != 'quarter-mile')) ?
			'neighborhood' : app.Ratings[i]['area'].toLowerCase();
			
		var featureScore = getFeatureScore(app.WalkspanValue, area);
		var scoreColor = app.LayerColors[app.WalkspanValue][featureScore - 1];
		
		var tabScore = $('.walkspan-area-tabs .tab-link.tab-' + area).children('.tab-score');
		
		tabScore.html(featureScore);
		
		if (tabScore.parent().hasClass('selected')) {
			tabScore.css({ 'background-color': scoreColor, 'color': '#FFF' })
		}
	}
}

function reverseGeocodeCurrentLocation() {
	
	// No need to reverse geocode again if the same location is selected
	if ((app.LastSelectedLat == app.SelectedLat)
		&& (app.LastSelectedLng == app.SelectedLng)) {
		$('.wapi-search').val(app.SelectedAddress);
		return;
	}
	
	var latlng = { 
		lat: app.SelectedLat,
		lng: app.SelectedLng 
	};
	
	app.Geocoder.geocode({ 'location': latlng }, function(results, status) {
		
		if (status != google.maps.places.PlacesServiceStatus.OK) return;
		
		app.SelectedAddress = results[0].formatted_address;
		$('.wapi-search').val(app.SelectedAddress);
		
		// Clear out existing places markers
		for (var i = 0; i < app.PlacesMarkers.length; i++) {
			app.PlacesMarkers[i].setMap(null);
		}
		
		for (p in app.Places) {
			var request = {
				location: new google.maps.LatLng(app.SelectedLat, app.SelectedLng),
				places: p,
				radius: ((app.SelectedAreaType == 'half-mile') ? 804.672 : 402.336),
				types: app.Places[p]
			};
			
			//searchPlaces(request);
		}
	});
}

function searchPlaces (request) {
	
	if (!app.PlacesService) return;
	
	app.PlacesService.nearbySearch(request, function(results, status){
		if (status != google.maps.places.PlacesServiceStatus.OK) return;
		
		for (var i = 0; i < results.length; i++) {
			createPlaceMarker(results[i], request.places);
		}
	});
}

function createPlaceMarker (place, placesCategory) {
	
	var marker = new google.maps.Marker({
		draggable: false,
		icon: {
			path: google.maps.SymbolPath.CIRCLE,
			scale: 5,
			strokeOpacity: 0,
			fillOpacity: 1,
			fillColor: app.PlacesColors[placesCategory]
		},
		map: app.Map,
		position: place.geometry.location
	});
	app.PlacesMarkers.push(marker);
	
	google.maps.event.addListener(marker, 'click', function() {
		app.MapInfoWindow.setContent(place.name);
		app.MapInfoWindow.open(app.Map, this);
	});
}

function getRatings (areaType, lat, lng) {
	
	if ((! isDefined(areaType)) || (! isDefined(lat)) || (! isDefined(lng))) {
		alert('Please enter an address to view ratings.');
		return;
	}
	
	var ajaxLoaderHtml = '<div class="wapi-load-indicator"><img src="content/images/ajax-loader.gif"></img></div>';
	$('.wapi-center').append(ajaxLoaderHtml);
	
	var url = app.APIUrl + '?action=GetRatings';
	url += '&key=' + app.APIKey;
	url += '&areatype=all';
	url += '&lat=' + lat;
	url += '&lng=' + lng;
	
	var deferred = $.ajax({
		url: url,
		type: 'GET',
		beforeSend: function (xhr) { },
		success: function (data, textStatus, xhr) { 
		
			app.Ratings = [{}, {}, {}];
			var tempRatings = JSON.parse(data);
			
			for (var i = 0; i < tempRatings.length; i++) {
				var keys = Object.keys(tempRatings[i]);
				for (var j = 0; j < keys.length; j++) {
					app.Ratings[i][keys[j].toLowerCase()] = tempRatings[i][keys[j]];
				}
			}
			
			refreshWalkabilityFeature();
			updateMapSelection();
			fillTotalRatings();
			
			if (app.SelectedAreaType == 'neighborhood') {
				getNeighborhood();
			} else {
				app.SelectedNeighborhood = null;
			}
			
			app.FirstLoad = false;
		},
		complete: function (xhr, textStatus) { },
		error: function (xhr, textStatus, errorThrown) { }
	});
	
	return deferred;
}

function getNeighborhood () {
	
	var neighborhoodRating = app.Ratings.find(function (obj) {
		return ((obj['area'].toLowerCase() != 'half-mile') 
			&& (obj['area'].toLowerCase() != 'quarter-mile'));
	});
	var neighborhoodName = (neighborhoodRating) ? neighborhoodRating['area'] : null;
	if (!neighborhoodName) return null;
	
	var url = app.APIUrl + '?action=GetNeighborhood';
	url += '&key=' + app.APIKey;
	url += '&name=' + neighborhoodName;
	url += '&includegeojson=' + true;
	
	var deferred = $.ajax({
		url: url,
		type: 'GET',
		beforeSend: function (xhr) { },
		success: function (data, textStatus, xhr) { 
			
			if (!data) return;
			
			app.Neighborhoods[neighborhoodName] = JSON.parse(data);
			app.SelectedNeighborhoodId = app.Neighborhoods[neighborhoodName].Id;
			
			if (app.NeighborhoodLayer) {
				for (var i = 0; i < app.NeighborhoodLayer.length; i++) {
					app.Map.data.remove(app.NeighborhoodLayer[i]);
				}
			}
			
			var neighborhoodGeoJSON =
				JSON.parse(app.Neighborhoods[neighborhoodName].GeoJSON);
			var feature = { 
				type: 'Feature',
				geometry: neighborhoodGeoJSON
			};
			
			// Map values to colors
			var value = getFeatureScore(app.WalkspanValue);
			
			app.NeighborhoodLayer = app.Map.data.addGeoJson(feature);
			app.Map.data.setStyle({
				strokeColor: app.LayerColors[app.WalkspanValue][2],
				fillColor: app.LayerColors[app.WalkspanValue][value - 1],
				fillOpacity: 0,
				strokeWeight: 3
			});
		},
		complete: function (xhr, textStatus) { },
		error: function (xhr, textStatus, errorThrown) { }
	});
	
	return deferred;
}

function getNeighborhoods () {
	
	var url = app.APIUrl + '?action=GetNeighborhoods';
	url += '&key=' + app.APIKey;
	url += '&city=' + 'new%20york';
	
	var deferred = $.ajax({
		url: url,
		type: 'GET',
		beforeSend: function (xhr) { },
		success: function (data, textStatus, xhr) { 
		
			app.Neighborhoods = JSON.parse(data);
			
			// Process neighborhoods and set geojson layer
			for (var i = 0; i < app.Neighborhoods.length; i++) {
				var geojson = L.geoJson(JSON.parse(app.Neighborhoods[i].GeoJSON));
				app.Neighborhoods[i].Layer = geojson;
				delete app.Neighborhoods[i].GeoJSON;
			}
		},
		complete: function (xhr, textStatus) { },
		error: function (xhr, textStatus, errorThrown) { }
	});
	
	return deferred;
}

function updateMapSelection () {

	if (app.SelectedLocationMarker) {
		app.SelectedLocationMarker.setMap(null);
	}
	
	app.SelectedLocationMarker = new google.maps.Marker({
		icon: {
			anchor: new google.maps.Point(12, 12),
			url: 'content/images/home.png'
		},
		map: app.Map,
		position: new google.maps.LatLng(app.SelectedLat, app.SelectedLng)
	});
  
	if (isDefined(app.SelectedLocationCircles)) {
		for (var i = 0; i < app.SelectedLocationCircles.length; i++) {
			app.SelectedLocationCircles[i].setMap(null);
		}
	}

	var drawCircle = function (radius) {
		// Map values to colors
		var value = getFeatureScore(app.WalkspanValue);
		
		app.SelectedLocationCircles[i] = new google.maps.Circle({
			strokeColor: app.LayerColors[app.WalkspanValue][2],
			strokeOpacity: 0.5,
			strokeWeight: 3,
			fillColor: app.LayerColors[app.WalkspanValue][value - 1],
			fillOpacity: 0,
			map: app.Map,
			center: new google.maps.LatLng(app.SelectedLat, app.SelectedLng),
			radius: radius
		});
	};

	if (app.SelectedAreaType == 'quarter-mile') {
		drawCircle(402.336);
	} else if (app.SelectedAreaType == 'half-mile') {
		drawCircle(804.672);
	}
	
	app.Map.setCenter(new google.maps.LatLng(app.SelectedLat, app.SelectedLng));
	
	var mapZoom = (app.FirstLoad) ? 16 :
		(app.SelectedAreaType == 'half-mile') ? 15 :
		(app.SelectedAreaType == 'quarter-mile') ? 16 : 15;
	app.Map.setZoom(mapZoom);
}

function initializeMap() {
	
	var mapStyles = [{
		featureType: 'poi',
		stylers: [{ visibility: 'off' }]
	}];
	
	app.Map = new google.maps.Map($('#divWAPIMap')[0], {
		center: {
			lat: app.SelectedLat, 
			lng: app.SelectedLng
		},
		disableDefaultUI: true,
		fullscreenControl: false,
		mapTypeControl: true,
		mapTypeControlOptions: {
			position: google.maps.ControlPosition.TOP_RIGHT
		},
		mapTypeId: google.maps.MapTypeId.ROADMAP,
		rotateControl: false,
		scaleControl: false,
		streetViewControl: false,
		styles: mapStyles,
		zoom: 16
	});
	
    app.Map.controls[google.maps.ControlPosition.RIGHT_TOP].push($('#divWAPIMapLegend')[0]);
	
	app.MapInfoWindow = new google.maps.InfoWindow();
	app.PlacesService = new google.maps.places.PlacesService(app.Map);
	app.StreetViewService = new google.maps.StreetViewService();
	
	refreshSideWalkView();
	
	google.maps.event.addListener(app.Map, 'click', function(event) {
		app.LastSelectedLat = app.SelectedLat;
		app.LastSelectedLng = app.SelectedLng;
		app.SelectedLat = event.latLng.lat();
		app.SelectedLng = event.latLng.lng();
		
		reverseGeocodeCurrentLocation();
		refreshSideWalkView();
		
		getRatings(app.SelectedAreaType, app.SelectedLat, app.SelectedLng);
	});
	
	refreshGeoserverLayers();
}

function selectLayer(event, value) {
	
	app.WalkspanValue = value;
	
	$(event.currentTarget).addClass('selected')
		.siblings().removeClass('selected');
	
	refreshWalkabilityFeature();
	updateMapSelection();
	fillTotalRatings();
	refreshGeoserverLayers();
	
	if (app.SelectedAreaType == 'neighborhood') {
		getNeighborhood();
	} else {
		app.SelectedNeighborhood = null;
	}
}

function refreshWalkabilityFeature () {
	
	var selectedFeature = $('.walkspan-left-wrapper .feature-panel .walkability-feature.selected');
	
	// Reset all images to default faded state
	var featureImgs = $('.walkspan-left-wrapper .feature-panel .walkability-feature-image img');
	featureImgs.each(function () {
		$(this).attr('src', $(this).attr('src').replace('-faded.png', '.png')
			.replace('.png', '-faded.png'));
		$(this).css('opacity', '1.0');
	});
	
	// Remove faded state from the selected feature image
	var featureImg = selectedFeature.closest('.walkability-feature')
		.find('.walkability-feature-image img');
	featureImg.attr('src', featureImg.attr('src').replace('-faded', ''));
	
	var featureScore = getFeatureScore(app.WalkspanValue);
	
	featureImg.css('opacity', (featureScore == 3) ? '1.0' : 
		((featureScore == 2) ? '0.6' : '0.25'));
	
	// Update about feature decription title
	var aboutTitle = 'About ' + selectedFeature.closest('.walkability-feature')
		.find('.walkability-feature-name').html();
	selectedFeature.closest('.walkspan-left-wrapper')
		.find('.feature-description-header').html(aboutTitle);
	
	// Update about feature decription content
	selectedFeature.closest('.walkspan-left-wrapper')
		.find('.feature-description-content').html(selectedFeature.attr('description'));
	
	// Set legend panel score colors
	if (app.LayerColors[app.WalkspanValue]) {
		var legendPanel = $('.walkspan-left-wrapper .legend-panel');
		legendPanel.find('.score-1 .score-bubble')
			.css('background-color', app.LayerColors[app.WalkspanValue][0]);
		legendPanel.find('.score-2 .score-bubble')
			.css('background-color', app.LayerColors[app.WalkspanValue][1]);
		legendPanel.find('.score-3 .score-bubble')
			.css('background-color', app.LayerColors[app.WalkspanValue][2]);
	}
}

function getFeatureScore(feature, areaType) {
	
	var ret = 3;
	
	if (!areaType) {
		areaType = app.SelectedAreaType;
	}
	
	for (var i = 0; i < app.Ratings.length; i++) {
		
		if ((app.Ratings[i]['area'].toLowerCase() == areaType.toLowerCase()) 
		 || ((areaType.toLowerCase() == 'neighborhood')
		  && (app.Ratings[i]['area'].toLowerCase() != 'quarter-mile') 
		  && (app.Ratings[i]['area'].toLowerCase() != 'half-mile')))
		{
			ret = app.Ratings[i][feature.replace('_', '')];
			ret = parseInt(Math.round(ret));
			break;
		}
	}

	// Normalize total
	if (app.WalkspanValue == 'total') {
		ret = parseInt(Math.ceil(ret / 33));
	}
	
	return ret;
}

function refreshSideWalkView () {
	
	var latLng = new google.maps.LatLng(app.SelectedLat, app.SelectedLng);
	
	app.StreetViewPanorama = new google.maps.StreetViewPanorama($('#divWAPISidewalkView')[0]);

	app.StreetViewService
		.getPanoramaByLocation(latLng, 50, function(panoData, status) { 
		
		if (status != google.maps.StreetViewStatus.OK) {
			$('#divWAPISidewalkView').html('No StreetView Picture Available')
				.attr('style', 'text-align:center;font-weight:bold').show();
			return;
		}
		
		var panoOptions = {
			addressControl: false,
			enableCloseButton: false,
			fullscreenControl: false,
			linksControl: false,
			panControl: false,
			position: latLng,
			pov: {
				heading: 0,
				pitch: 0,
				zoom: 2
			},
			visible: true,
			zoomControlOptions: {
				style: google.maps.ZoomControlStyle.SMALL
			}
		};

		app.StreetViewPanorama.setOptions(panoOptions);
	});
}

function toggleExpandedSidewalkView (event) {
	
	var panoOptions = { visible: false };
	app.StreetViewPanorama.setOptions(panoOptions);
	
	$(event.currentTarget).add(
		$(event.currentTarget).closest('.wapi-sidewalk-view')
	).add($(event.currentTarget).closest('.walkspan-left-panel.top-panel')
	)
		.toggleClass('expanded');
		
	setTimeout(function () {
		refreshSideWalkView();
	}, 300);
}

function refreshGeoserverLayers () {

	// Clear existing overlay layers
	app.Map.overlayMapTypes.clear();
	
	// Define custom WMS tiled layer
	app.WalkspanLayer = new google.maps.ImageMapType({
		getTileUrl: function (coord, zoom) {
			
			var proj = app.Map.getProjection();
			var zfactor = Math.pow(2, zoom);
			
			// Get Long Lat coordinates
			var top = proj.fromPointToLatLng(
				new google.maps.Point(
					coord.x * 256 / zfactor, 
					coord.y * 256 / zfactor
				)
			);
			var bot = proj.fromPointToLatLng(
				new google.maps.Point(
					(coord.x + 1) * 256 / zfactor, 
					(coord.y + 1) * 256 / zfactor
				)
			);
			
			// Create the Bounding box string
			var bbox = top.lng() + "," +
						bot.lat() + "," +
						bot.lng() + "," +
						top.lat();
						
			var layer = 'walkspan_circle';
			var viewParams = '';
			if (app.SelectedAreaType == 'neighborhood') {
				layer = 'walkspan_neighborhood';
				viewParams += '_neighborhood:' + app.SelectedNeighborhoodId;
			} else {
				viewParams += '_radius:' + ((app.SelectedAreaType == 'quarter-mile') ? '25' :
					((app.SelectedAreaType == 'half-mile') ? '50' : '10000'));
				viewParams += ';_lat:' + (app.SelectedLat * 10000).toFixed() 
					+ ';_lng:' + Math.abs(app.SelectedLng * 10000).toFixed();
			}
			
			// Build WMS URL
			var geoserver = 'geoserver/walkspan/wms?';
			geoserver += '&REQUEST=GetMap';
			geoserver += '&SERVICE=WMS';
			geoserver += '&VERSION=1.1.1';
			geoserver += '&LAYERS=walkspan:' + layer;
			geoserver += '&FORMAT=image/png';
			geoserver += '&BGCOLOR=0xFFFFFF';
			geoserver += '&TRANSPARENT=TRUE';
			geoserver += '&SRS=EPSG:' + '4326';
			geoserver += '&BBOX=' + bbox;
			geoserver += '&WIDTH=256';
			geoserver += '&HEIGHT=256';
			geoserver += '&STYLES=' + app.WalkspanValue + 'Walkspan';
			geoserver += '&VIEWPARAMS=' + viewParams;
			var url = './php/api.php?key=' + app.APIKey + '&action=Geoserver&geoserver=' + encodeURIComponent(geoserver);
			return url;
		},
		tileSize: new google.maps.Size(256, 256),
		isPng: true,
		name: 'WalkspanLayer'
	});
	
	app.Map.overlayMapTypes.push(app.WalkspanLayer);
}

function applyDarkTheme () {
	$('.wapi-main, .wapi-top-bar').addClass('dark-gray-theme');
	$('.wapi-left').children('img').attr('src', 'content/images/walkability-guide-dark-gray.jpg');
}

function gotoMainView () {
	window.location.href = 'https://www.senseofwalk.com/walkspan-api/index.html';
}

function gotoRequestKey () {
	window.location.href = 'https://www.senseofwalk.com/walkspan-api/admin/login.html';
}

function isDefined (obj) {
	return ((typeof obj !== 'undefined') && (obj !== null));
}
