<?php

	header('Access-Control-Allow-Headers: Content-Type');
	header('Access-Control-Allow-Credentials: true');
	header('Access-Control-Allow-Methods: DELETE, GET, OPTIONS, POST');
	header('Access-Control-Allow-Origin: ' . 
		(isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : 
		(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 
		(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''))));
	
	define('TABLE', 'walkspan');
	define('TABLE_VERTICES', 'walkspan_vertices_pgr');
	define('GOOGLE_MAPS_KEY', 'AIzaSyBOXYeVd2IW4rON6FTyLBesJOG87NE3BJo');
	
	// Ignore options
	if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {    
		return 0;    
	}   
	
	require_once('conn.php');
	
	// Get action requested
	$_REQUEST_lower = array_change_key_case($_REQUEST, CASE_LOWER);
	$action = isset($_REQUEST_lower['action']) ? $_REQUEST_lower['action'] :
		(isset($post['Action']) ? $post['Action'] :
		(isset($post['action']) ? $post['action'] : null));
	if (! isset($action)) {
		http_response_code(400);
		die('Error: Invalid action request');
	}

	check_access_key();

	switch (strtolower($action))
	{
		// Get methods
		case 'autocomplete': autocomplete_google(); break;
		case 'autocompletewalkspan': autocomplete_db(); break;
		case 'geocode': geocode(); break;
		case 'geoserver': geoserver(); break;
		case 'getdirections': get_directions(); break;
		case 'getneighborhood': get_neighborhood(); break;
		case 'getneighborhoods': get_neighborhoods(); break;
		case 'getratings': get_ratings(); break;
		case 'getdatacount': get_data_count(); break;
		case 'getdatasample': get_data_sample(); break; 
		case 'reversegeocode': echo json_encode(reverse_geocode()); break;
		case 'route': route(); break;
	}
	
	function route() {
		
		$metric = isset($_REQUEST['metric']) ? htmlentities($_REQUEST['metric']) : 'distance';
		$waypoints = isset($_REQUEST['waypoints']) ? $_REQUEST['waypoints'] : '[]';
		$waypoints = json_decode($waypoints);
		
		// Need at least 2 points to create a route
		if (count($waypoints) < 2) {
			echo json_encode(array(
				'Message' => 'Need at least 2 points to create a route.',
				'Status' => -1
			));
			return;
		}
		
		// Currently only 3 points are allowed (can be increased)
		if (count($waypoints) > 3) {
			echo json_encode(array(
				'Message' => 'Starting point, ending point and maximum of 1 extra waypoint are allowed.',
				'Status' => -1
			));
			return;
		}
		
		$feature_collection = array('type' => 'FeatureCollection', 'features' => array());
		
		$conn = get_postgresql_db_connection();
		
		// Build waypoint array
		$waypoint_array = '';
		for ($i = 0; $i < count($waypoints); $i++) {
			if ($i > 0) $waypoint_array .= ', ';
			$waypoint_array .= '[' . 
				$waypoints[$i]->lat . ', ' . 
				$waypoints[$i]->lng . 
			']';
		}
		
		$sql = "SELECT * FROM routing_route (
			ARRAY[$waypoint_array]::FLOAT[][], 
			'$metric', 
			'" . TABLE . "',
			'" . TABLE_VERTICES . "'
		)";
		
		$routing_geojson = get_routing_geojson(
			$conn, 
			$feature_collection, 
			$sql, 
			$waypoints[0], 
			$waypoints[count($waypoints) - 1]
		);
	
		echo $routing_geojson;
			
		// Close connection to postgreSQL DB
		pg_close($conn);
	}
	
	// geocode - Geocodes an address to a lat, lng, or returns not found
	function geocode() {
		
		$address = isset($_REQUEST['address']) ? $_REQUEST['address'] : null;
		if ($address == null) return;
		
		// Build graph hopper reverse geocode url
		$url = 'https://graphhopper.com/api/1/geocode?';
		$url .= 'key=' . 'b05b8164-397c-4057-861c-78b648824bc5';
		$url .= '&limit=5';
		$url .= '&locale=' . 'en';
		$url .= '&q=' . urlencode($address);
		$url .= '&reverse=' . 'false';
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => 'Geocode Request'
		));
		
		$response = curl_exec($curl);
		
		if (curl_errno($curl))
			echo 'Curl error: ' . curl_error($curl);

		$response = json_decode($response, true);
		$point = (count($response['hits']) == 0) ?
			array( 'error' => 'No lat, lng found for specified address' )
			: $response['hits'][0]['point']
		;
		curl_close($curl);
		
		echo json_encode($point);
	}
	
	function geoserver() {
		$geoserver_request = isset($_REQUEST['geoserver']) ? $_REQUEST['geoserver'] : null;
		if (!$geoserver_request) {
			return;
		}
		$geoserver_url = 'http://3.15.149.128:8080/' . html_entity_decode($geoserver_request);
		
        $ch = curl_init($geoserver_url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ch_result = curl_exec($ch);
        curl_close($ch);
		
		echo $ch_result;
	}
	
	function reverse_geocode($lat = null, $lng = null) {
		
		// Note: An alternative is Google Places API, ex:
		// https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=-33.8670522,151.1957362&radius=500&type=restaurant&keyword=cruise&key=API_KEY
		
		$lat = (isset($lat)) ? $lat : (isset($_REQUEST['lat']) ? $_REQUEST['lat'] : null);
		$lng = (isset($lng)) ? $lng : (isset($_REQUEST['lng']) ? $_REQUEST['lng'] : null);
		
		if (($lat == null) || ($lng == null)) return;
		
		// Build graph hopper reverse geocode url
		$url = 'https://graphhopper.com/api/1/geocode?';
		$url .= 'key=' . 'b05b8164-397c-4057-861c-78b648824bc5';
		$url .= '&locale=' . 'en';
		$url .= '&point=' . "$lat,$lng";
		$url .= '&reverse=' . 'true';
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => 'Reverse Geocode Request'
		));
		
		$response = curl_exec($curl);
		
		$response = json_decode($response, true);
		
		curl_close($curl);
		
		$address = 'No address found for the specified lat, lng';
		$street = '';
		
		if (count($response['hits']) > 0) {
			
			$firstMatch = $response['hits'][0];
			$house_number = isset($firstMatch['housenumber']) ? $firstMatch['housenumber'] : null;
			$street = isset($firstMatch['street']) ? $firstMatch['street'] : null;
			
			$address = (! isset($house_number)) ? $firstMatch['name'] : 
				($firstMatch['housenumber'] . ' ' . $firstMatch['street']);
			$address = ucwords(strtolower(trim($address)));
			$address .= ' ' . $firstMatch['city'] . 
				(isset($firstMatch['state']) ? ', ' . get_state_abbr($firstMatch['state']) : '') .
				' ' . (isset($firstMatch['postcode']) ? $firstMatch['postcode'] : '');
		}

		$address = array(
			'fulladdress' => $address,
			'housenumber' => $house_number,
			'name' => $firstMatch['name'],
			'street' => $street,
			'city' => $firstMatch['city'],
			'state' => isset($firstMatch['state']) ? $firstMatch['state'] : null,
			'postcode' => isset($firstMatch['postcode']) ? $firstMatch['postcode'] : null
		);
		
		return $address;
	}
	
	function find_nearest_edge ($conn, $waypoint) {

		$sql = "
			SELECT 
				  id
				, source
				, target
				, geom
				, ST_Distance(geom, ST_GeomFromText(
					'POINT(" . (string) $waypoint->lng . " " . (string) $waypoint->lat . ")', 4326)) AS dist
			FROM " . TABLE . "
			ORDER BY dist LIMIT 1";

		$query = pg_query($conn, $sql);
		
		$edge['id'] = pg_fetch_result($query, 0, 0);
		$edge['source'] = pg_fetch_result($query, 0, 1);
		$edge['target'] = pg_fetch_result($query, 0, 2);
		$edge['geom'] = pg_fetch_result($query, 0, 3);
		
		return $edge;
	}
	
	// get_spd_2_point_routing_query - Uses Shortest Path Dijkstra to get a route between 2 points
	function get_spd_2_point_routing_query ($start_edge, $end_edge, $metric) {
		
		$sql = null;
		$cost_column = ($metric == 'distance') ? 'ST_Length(geom)' : $metric;
		$cost_column = "CAST($cost_column AS FLOAT8)";
		if ($metric != 'distance') $cost_column = "(1 / $cost_column)";
		
		$sql = "
			SELECT 
				  di.seq
				, di.id1 AS node
				, di.id2 AS edge
				, di.cost
				, W.street AS address
				, W.objtype
				, W.direction
				, ST_Length(ST_Transform(W.geom, 2263)) AS dist_ft
				, ST_AsGeoJSON(W.geom) AS geojson
			FROM pgr_dijkstra(
				'SELECT 
					  id
					, source
					, target
					, $cost_column as cost 
				FROM " . TABLE . "
				WHERE (source IS NOT NULL) AND (target IS NOT NULL)',
				" . $start_edge['source'] . ", " . $end_edge['target'] . ", false, false
			) as di
			JOIN " . TABLE . " W ON di.id2 = W.id 
		";
		
		return $sql;
	}
	
	// get_spd_3_point_routing_query - Uses Shortest Path Dijkstra to get a route between 2 points and a waypoint
	function get_spd_3_point_routing_query ($start_edge, $middle_edge, $end_edge, $metric) {
		
		$sql = null;
		$cost_column = ($metric == 'distance') ? 'ST_Length(geom)' : $metric;
		$cost_column = "CAST($cost_column AS FLOAT8)";
		if ($metric != 'distance') $cost_column = "(1 / $cost_column)";
		
		$sql = "
			SELECT 
				  di.seq
				, di.node
				, di.edge
				, di.cost
				, W.street AS address
				, W.objtype
				, W.direction
				, ST_Length(ST_Transform(W.geom, 2263)) AS dist_ft
				, ST_AsGeoJSON(W.geom) AS geojson
			FROM pgr_dijkstra(
				'SELECT 
					  id
					, source
					, target
					, CAST((1 / total) AS FLOAT8) AS cost
					, CAST((1 / total) AS FLOAT8) AS reverse_cost 
				FROM " . TABLE . "',
			" . $middle_edge['target'] . ", ARRAY[" . $start_edge['source'] . ", " . $end_edge['target'] . "]
			) di
			JOIN " . TABLE . " W ON di.edge = W.id
		";
		
		return $sql;
	}
	
	// get_spa_routing_query - Uses Shortest Path A* to get a route between 2 points
	function get_spa_routing_query ($start_edge, $end_edge, $metric) {

		$sql = "
			SELECT 
				  rt.id
				, ST_AsGeoJSON(rt.geom) AS geojson
				, length(rt.geom) AS length, " . TABLE . ".id
			FROM " . TABLE . ",
				(SELECT 
					  id
					, geom
				FROM astar_sp_delta(
					'" . TABLE . "',
					" . $start_edge['source'] . ",
					" . $end_edge['target'] . ", 0.1)
				) as rt
			WHERE " . TABLE . ".id = rt.id;";
		
		return $sql;
	}
	
	// get_spss_routing_query - Uses Shortest Path Shooting Star to get a route between 2 points
	function get_spss_routing_query ($start_edge, $end_edge, $metric) {

		$sql = "
			SELECT 
				  rt.id
				, ST_AsGeoJSON(rt.geom) AS geojson
				, length(rt.geom) AS length
				, " . TABLE . ".id
			FROM " . TABLE . ",
				(SELECT 
					  id
					, geom
				FROM shootingstar_sp(
					'" . TABLE . "',
					" . $start_edge['id'] . ",
					" . $end_edge['id'] . ",
					0.1, 'length', true, true)
				) as rt
			WHERE " . TABLE . ".id = rt.id;";
		
		return $sql;
	}

	function get_routing_geojson ($conn, $feature_collection, $sql, $start_point, $end_point) {

		// Run routing query
		$query = pg_query($conn, $sql);
		
		// Loop through edges and add them as features to returned geojson
		while ($edge = pg_fetch_assoc($query)) {

			$feature = array(
				'type' => 'Feature', 
				'geometry' => json_decode($edge['geojson'], true), 
				'crs' => array(
					'type' => 'EPSG', 
					'properties' => array('code' => '4326')
				), 
				'properties' => array(
					'address' => $edge['address'],
					'cost' => $edge['cost'],
					'objtype' => $edge['objtype'],
					'direction' => $edge['direction']
				)
			);

			array_push($feature_collection['features'], $feature);
		}

		return json_encode($feature_collection);
	}
	
	function autocomplete_google () {
		
		$search = isset($_REQUEST['search']) ? htmlentities($_REQUEST['search']) : null;
		
		$url = 'https://maps.googleapis.com/maps/api/place/textsearch/json';
		$url .= '?' . 'query=' . urlencode($search);
		$url .= '&' . 'key=' . GOOGLE_MAPS_KEY;
		
		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $url,
			CURLOPT_USERAGENT => 'Autocomplete Request'
		));
		
		$response = curl_exec($curl);
		
		if (curl_errno($curl))
			echo 'Curl error: ' . curl_error($curl);

		$response = json_decode($response, true);
		
		$addresses = array();
		if (isset($response['results'])) {
			foreach ($response['results'] as $r) {
				if (isset($r['formatted_address'])) {
					$addresses[] = array(
						'Address' => $r['formatted_address']
					);
				}
			}
		}
	
		curl_close($curl);

		echo json_encode($addresses);
	}
	
	function autocomplete_db() {
		
		$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 10;
		$terms = isset($_REQUEST['terms']) ? htmlentities($_REQUEST['terms']) : null;
		if ($terms == null) return;
		$terms = explode(',', $terms);
		
		// Can include ', ' || boroname || 
		$query = "
			SELECT 
				(houseno ||  ' ' || st_name || 
				', New York, NY ' || zipcode) AS Address
			FROM 	public.ny_addresses
			WHERE 	(1 = 1)";
			foreach ($terms as $term)
				$query .= " AND (address ILIKE '%" . strtolower(trim($term)) . "%')";
		$query .= " LIMIT $limit";
		
		$conn = get_postgresql_db_connection();
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		$addresses = array();
		
		while ($row = pg_fetch_row($result)) {
			$addresses[] = array(
				'Address' => 	$row[0]
			);
		}
		
		// Close connection to postgreSQL DB
		pg_close($conn);
		
		echo json_encode($addresses);	
	}
	
	// get_neighborhood - Gets data for a specific neighborhood by its name
	function get_neighborhood() {
		
		$name = isset($_REQUEST['name']) ? htmlentities($_REQUEST['name']) : null;
		$include_geojson = isset($_REQUEST['includegeojson']) ? htmlentities($_REQUEST['includegeojson']) : null;
		if (! isset($name)) return null;
		
		$query = "
			SELECT 
					  id
					, bounds
					, borough
					, city
					, " . (($include_geojson) ? "ST_AsGeoJSON(geom)" : "NULL") . " as geojson
					, neighbrhd
					, state
			FROM 	public.neighborhoods
			WHERE 	(1 = 1)
			AND 	(neighbrhd IS NOT NULL)
			AND 	(neighbrhd ILIKE '" . pg_escape_string($name) . "')
			LIMIT	1;
		";
		
		$conn = get_postgresql_db_connection();

		$result = pg_query($conn, $query)
			or die ('Error: ' + pg_last_error($conn) + '\n');
		
		$neighborhood = null;
		while ($row = pg_fetch_row($result)) {
			$neighborhood = array(
				'Id' => $row[0],
				'Bounds' => $row[1],
				'Borough' => $row[2],
				'City' => $row[3],
				'GeoJSON' => $row[4],
				'Name' => $row[5],
				'State' => $row[6]
			);
		}
		
		// Close connection to postgreSQL DB
		pg_close($conn);
		
		echo json_encode($neighborhood);
	}
	
	// get_neighborhoods - Gets a list of neighborhoods based on borough, city, state
	function get_neighborhoods() {
		
		$borough = isset($_REQUEST['borough']) ? htmlentities($_REQUEST['borough']) : null;
		$city = isset($_REQUEST['city']) ? htmlentities($_REQUEST['city']) : null;
		$state = isset($_REQUEST['state']) ? htmlentities($_REQUEST['state']) : null;
		if (($borough == null) && ($city == null) && ($state == null)) return;
		
		$query = "
			SELECT
				  id
				, bounds
				, borough
				, city
				, ST_AsGeoJSON(geom) as geojson
				, neighbrhd
				, state
			FROM 	public.neighborhoods
			WHERE 	(1 = 1)
			AND 	(neighbrhd IS NOT NULL)
		";
		if ($borough != null) $query .= " AND (borough ILIKE '$borough')";
		if ($city != null) $query .= " AND (city ILIKE '$city')";
		if ($state != null) $query .= " AND (state ILIKE '$state')";
		$query .= ' ORDER BY neighbrhd ASC';
		
		$conn = get_postgresql_db_connection();

		$result = pg_query($conn, $query)
			or die ('Error: ' + pg_last_error($conn) + '\n');
		
		$neighborhoods = array();
		while ($row = pg_fetch_row($result)) {
			$neighborhoods[] = array(
				'Id' => $row[0],
				'Bounds' => $row[1],
				'Borough' => $row[2],
				'City' => $row[3],
				'GeoJSON' => $row[4],
				'Name' => $row[5],
				'State' => $row[6]
			);
		}
		
		// Close connection to postgreSQL DB
		pg_close($conn);
		
		echo json_encode($neighborhoods);	
	}
	
	function get_distance_display ($distance_feet) {
		
		// Calculate distance display (in miles or feet)
		$distance_precision = 0;
		$distance_value = isset($distance_feet) ? $distance_feet : 0;
		
		//if ($distance_value >= 5280) {
			$distance_value /= 5280;
			$distance_precision = 2;
			$distance_unit = 'miles';
		//}
		
		//$distance = round($distance_value, 6);
		$distance_display = number_format($distance_value, $distance_precision, '.', ',') . ' ' . $distance_unit;
	
		return $distance_display;
	}
	
	function get_directions () {
		
		$metric = isset($_REQUEST['metric']) ? htmlentities($_REQUEST['metric']) : 'distance';
		$metric = ($metric == 'distance') ? 'ST_Length(geom)' : $metric;
		$waypoints = isset($_REQUEST['waypoints']) ? $_REQUEST['waypoints'] : '[]';
		$waypoints = json_decode($waypoints);
		
		// Need at least 2 points to create a route
		if (count($waypoints) < 2) {
			echo json_encode(array(
				'Message' => 'Need at least 2 points to create route directions.',
				'Status' => -1
			));
			return;
		}
		
		// Currently only 3 points are allowed (can be increased)
		if (count($waypoints) > 3) {
			echo json_encode(array(
				'Message' => 'Starting point, ending point and maximum of 1 extra waypoint are allowed.',
				'Status' => -1
			));
			return;
		}
		
		$feature_collection = array('type' => 'FeatureCollection', 'features' => array());
		
		$conn = get_postgresql_db_connection();
		
		// Build waypoint array
		$waypoint_array = '';
		for ($i = 0; $i < count($waypoints); $i++) {
			if ($i > 0) $waypoint_array .= ', ';
			$waypoint_array .= '[' . 
				$waypoints[$i]->lat . ', ' . 
				$waypoints[$i]->lng . 
			']';
		}
		
		$query = "SELECT * FROM routing_directions(
			ARRAY[$waypoint_array]::FLOAT[][], 
			'$metric', 
			'" . TABLE . "',
			'" . TABLE_VERTICES . "'
		)";

		$result = pg_query($conn, $query)
			or die ('Error: ' + pg_last_error($conn) + '\n');

		$i = 0;
		$coord_lat = null;
		$coord_lng = null;
		$last_coord_lat = null;
		$last_coord_lng = null;
		$num_rows = pg_num_rows($result);
		$directions = array();
		
		while ($row = pg_fetch_row($result)) {
			
			$cost = min(round((($row[3]) ? $row[3] : 0), 4), 0.01);
			$direction = ($row[6]) ? $row[6] : '';
			
			$distance = round((($row[7]) ? $row[7] * 333000 : 0), 6);
			$distance_display = get_distance_display($distance);
			// Average walking speed (3.1 mph)
			$feet_per_second = 4.5467;
			$time_minutes = round((($distance / $feet_per_second) / 60), 2);
			
			$last_coord_lat = $coord_lat;
			$last_coord_lng = $coord_lng;
			
			$orientation = '';
			
			try {
				
				$geojson = json_decode($row[8]);
				
				if (isset($geojson->coordinates) && is_array($geojson->coordinates)) {
					
					if (isset($geojson->coordinates[0]) && is_array($geojson->coordinates[0])) {
						
						$coords = $geojson->coordinates[0];
						
						if (isset($coords[0]) && is_array($coords[0])) {
							
							$coord_lat = $coords[0][1];
							$coord_lng = $coords[0][0];
							
						} else {
							$coord_lat = $coords[1];
							$coord_lng = $coords[0];
						}
					}
				}
				
				if (($last_coord_lat != null) && ($last_coord_lng != null)
					&& ($coord_lat != null) && ($coord_lng != null)) {
					$orientation = get_orientation(
						$last_coord_lat, 
						$last_coord_lng, 
						$coord_lat, 
						$coord_lng
					);
				}

			} catch (Exception $ex) { 
				// Do nothing
			}
			
			$seq = $row[0];
			
			// Get direction instruction
			$instruction = '';
			if ($i == 0) {
				
				$instruction = 'Start at ';
			//} else if (($i == 0) && ($i > 0)) {
				
				//$instruction = 'Continue on ';
			} else if ($i == ($num_rows - 1)) {
				
				$instruction = 'Finish at ';
			} else if (strpos($direction, 'crossing') !== false) {
			
				$instruction = ($orientation) ? 'Cross ' . $orientation . ' on ' : 'Cross ';
			} else {
				
				$instruction = ($orientation) ? 'Turn ' . $orientation . ' and continue on ' : 'Continue on ';
			}
			
			// Get address
			$address = isset($row[4]) ? ucwords(strtolower($row[4])) : 'unknown';
			$address = str_ireplace('and', 'and', $address);
			$address = str_ireplace('of', 'of', $address);
			$address = str_ireplace('the', 'the', $address);
			if (((trim(strtolower($address)) == 'start') 
			  || (trim(strtolower($address)) == 'end') 
			  || (trim(strtolower($address)) == 'unknown'))
			 && (isset($coord_lat) && isset($coord_lng)) 
			 ) {
				$address_parts = reverse_geocode($coord_lat, $coord_lng);
				$address = $address_parts['fulladdress'];
			}
			
			$route_type = $row[5];
			
			$directions[] = array(
				'Address' => $address,
				'Cost' => $cost,
				'Direction' => $direction,
				'DistanceFeet' => $distance,
				'DistanceDisplay' => $distance_display,
				'Instruction' => $instruction,
				'RouteType' => $route_type,
				'Sequence' => $seq,
				'TimeMinutes' => $time_minutes
			);
			
			$i++;
		}
		
		$final_directions = array();
		for ($i = 1; $i <= count($directions); $i++) {
			
			if (($i < count($directions))
				&& ($directions[$i - 1]['Address'] == $directions[$i]['Address'])) {
				
				$directions[$i]['DistanceFeet'] += $directions[$i - 1]['DistanceFeet'];
				$directions[$i]['TimeMinutes'] += $directions[$i - 1]['TimeMinutes'];

			} else {
				
				$final_directions[] = array(
					'Address' => $directions[$i - 1]['Address'],
					'Cost' => 0.01,
					'Direction' => $directions[$i - 1]['Direction'],
					'DistanceFeet' => $directions[$i - 1]['DistanceFeet'],
					'DistanceDisplay' => get_distance_display($directions[$i - 1]['DistanceFeet']),
					'Instruction' => $directions[$i - 1]['Instruction'],
					'RouteType' => $directions[$i - 1]['RouteType'],
					'Sequence' => count($final_directions),
					'TimeMinutes' => $directions[$i - 1]['TimeMinutes']
				);
			}
		}
		
		// Close connection to postgreSQL DB
		pg_close($conn);
		
		//echo json_encode(array_merge($directions, $final_directions));
		echo json_encode($final_directions);
	}
	
	function get_state_abbr ($state_name) {

		if (! $state_name) return null;
		
		$ret = null;
		
		switch (strtolower(trim($state_name))) {
			case 'new york': 
				$ret = 'NY'; 
				break;
			default: 
				$ret = $state_name; 
				break;
		}
		
		return $ret;
	}
	
	// get_orientation - Gets orientation between two map coordinates
	function get_orientation ($lat_1, $lon_1, $lat_2, $lon_2) {
			
		$ret = null;
		
		$angle = get_line_angle($lat_1, $lon_1, $lat_2, $lon_2);
		
		// Straight
		if (abs($angle) < 6) {
		
			$ret = null;
		
		} else if ($lat_2 > $lat_1) {
		
			// East
			if ($lon_2 > $lon_1) {
				$ret = 'right';
			// West
			} else {
				$ret = 'left';
			}
			
			if (abs($angle) < 20) $ret = 'slight ' . $ret;
		
		// South
		} else {
		
			// East
			if ($lon_2 > $lon_1) {
				$ret = 'left';
			// West
			} else {
				$ret = 'right';
			}
			
			if (abs($angle) < 20) $ret = 'slight ' . $ret;
		}
		
		return $ret;
	}
	
	// get_line_angle - Gets the line angle between two map coordinates
	function get_line_angle ($lat_1, $lon_1, $lat_2, $lon_2) {
		
		$xy_1 = convert_lat_lng_to_XY($lat_1, $lon_1);
		$xy_2 = convert_lat_lng_to_XY($lat_2, $lon_2);
	
		$delta_X = $xy_2['x'] - $xy_1['x'];
		$delta_Y = $xy_2['y'] - $xy_1['y'];
		$rad = atan2($delta_Y, $delta_X);
		
		return rad2deg($rad);
	}
		
	// convert_lat_lng_to_XY - Sets a map coordinate on a flat plane
	function convert_lat_lng_to_XY ($lat, $lon) {
		
		$map_width = 2000;
		$map_height = 1000;
		
		$x = ($lon + 180) * ($map_width / 360);
		
		$lat_rad = $lat * M_PI / 180;
		
		$merc_n = log(tan((M_PI / 4) + ($lat_rad / 2)));
		$y = ($map_height / 2) - ($map_width * $merc_n / (2 * M_PI));
		
		return array(
			'x' => $x, 
			'y' => $y
		);
	}
	
	function get_ratings () {
		
		$area_type = isset($_REQUEST['areatype']) ? trim(strtolower(htmlentities($_REQUEST['areatype']))) : null;
		$lat = isset($_REQUEST['lat']) ? htmlentities($_REQUEST['lat']) : null;
		$lng = isset($_REQUEST['lng']) ? htmlentities($_REQUEST['lng']) : null;
		$api_key = isset($_REQUEST['key']) ? htmlentities($_REQUEST['key']) : null;

		$ratings = '
			  ROUND(AVG(beauty_n), 2) AS BeautyN
			, ROUND(AVG(beauty_m), 2) AS BeautyM
			, ROUND(AVG(comfort), 2) AS Comfort
			, ROUND(AVG(interest), 2) AS Interest
			, ROUND(AVG(safety), 2) AS Safety
			, ROUND(AVG("access"), 2) AS Access
			, ROUND(AVG(amenities), 2) AS Amenities
			, ROUND(AVG(total), 2) AS Total
		';

		$block_sql = "
			SELECT 	  $ratings
					, 'Block' AS Area
			FROM	(SELECT 
						  W2.beauty_n
						, W2.beauty_m
						, W2.comfort
						, W2.interest
						, W2.safety
						, W2.\"access\"
						, W2.amenities
						, W2.total
						, (SELECT ST_DISTANCE(W2.geom, ST_MakePoint($lng, $lat)::geography)) AS distance
				FROM 	walkspan W2 
				WHERE 	ST_Intersects(W2.geom, ST_Buffer(ST_MakePoint($lng, $lat)::geography, (0.2 * 1609)))
				AND		(objtype = 'NO')
				ORDER BY distance
				LIMIT 1) b
		";

		$neighborhood_sql = "
			SELECT 	  $ratings
					, neighborho AS Area
			FROM 	walkspan W
			WHERE	(W.neighborho = (SELECT neighbrhd 
				FROM public.neighborhoods 
				WHERE ST_Intersects(
					ST_SetSRID(geom, 4326), 
					ST_SetSRID(ST_MakePoint($lng, $lat), 4326)) 
				AND (neighbrhd IS NOT NULL) LIMIT 1))
			AND		(objtype = 'NO')
			GROUP BY neighborho
		";
		
		$quarter_mile_sql = "
			SELECT 	  $ratings
					, 'Quarter-Mile' AS Area
			FROM 	walkspan W
			WHERE	ST_Intersects(W.\"geom\", ST_Buffer(ST_MakePoint($lng, $lat)::geography, (0.25 * 1609))) 
			AND		(objtype = 'NO')
		";

		$half_mile_sql = "
			SELECT 	  $ratings
					, 'Half-Mile' AS Area
			FROM 	walkspan W
			WHERE	ST_Intersects(W.\"geom\", ST_Buffer(ST_MakePoint($lng, $lat)::geography, (0.50 * 1609))) 
			AND		(objtype = 'NO')
		";
		
		$query = null;
		switch ($area_type) {
			// Block Scores Query
			case 'block':
				$query = $block_sql;
				break;
			// Neighborhood Scores Query
			case 'neighborhood':
				$query = $neighborhood_sql;
				break;
			// Quarter Mile Scores Query
			case 'quarter-mile':
				$query = $quarter_mile_sql;
				break;
			case 'half-mile':
				$query = $half_mile_sql;
				break;
			// Union all three queries
			case 'all':
				$query = $neighborhood_sql . ' UNION ' . 
					$quarter_mile_sql . ' UNION ' . 
					$half_mile_sql;
				break;
		}
		
		if (! isset($query)) exit(1);
		
		$conn = get_postgresql_db_connection();
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		
		$ratings = array();
		while ($row = pg_fetch_row($result)) {
			$ratings[] = array(
				  'BeautyN' => $row[0]
				, 'BeautyM' => $row[1]
				, 'Comfort' => $row[2]
				, 'Interest' => $row[3]
				, 'Safety' => $row[4]
				, 'Access' => $row[5]
				, 'Amenities' => $row[6]
				, 'Total' => $row[7]
				, 'Area' => $row[8]
			);
		}
		
		// Close connection to postgreSQL DB
		pg_close($conn);
		
		update_api_call_count($api_key);
		
		echo json_encode($ratings);	
	}
	
	function get_data_count() {
		$city = isset($_REQUEST['city']) ? trim(strtolower(htmlentities($_REQUEST['city']))) : null;
		$feature = isset($_REQUEST['feature']) ? htmlentities($_REQUEST['feature']) : null;
		$type = isset($_REQUEST['type']) ? htmlentities($_REQUEST['type']) : null;
		$api_key = isset($_REQUEST['key']) ? htmlentities($_REQUEST['key']) : null;

		$query = "
			SELECT 	COUNT(*) 
			FROM 	walkspan W
			WHERE	($feature = $type)
		";

		if (! isset($query)) exit(1);
		
		$conn = get_postgresql_db_connection();
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		
		$data_count = pg_fetch_row($result);

		// Close connection to postgreSQL DB
		pg_close($conn);
		
		echo json_encode($data_count);
	}
	
	function get_data_sample() {
		$city = isset($_REQUEST['city']) ? trim(strtolower(htmlentities($_REQUEST['city']))) : null;
		$feature = isset($_REQUEST['feature']) ? htmlentities($_REQUEST['feature']) : null;
		$type = isset($_REQUEST['type']) ? htmlentities($_REQUEST['type']) : null;
		$api_key = isset($_REQUEST['key']) ? htmlentities($_REQUEST['key']) : null;

		$query = "
			SELECT 	  ((laty1 + laty2) / 2)::DECIMAL(10,5) AS latitude
					, ((longx1 + longx2) / 2)::DECIMAL(10,5) AS longitude
					, street
					, $feature as \"value\" 
			FROM 	walkspan W
			WHERE	($feature = $type)
			LIMIT 10;
		";

		if (! isset($query)) exit(1);
		
		$conn = get_postgresql_db_connection();
		
		$result = pg_query($conn, $query) 
			or die ('Error: ' + pg_last_error($conn) + '\n');
		
		$data_sample = array();
		while ($row = pg_fetch_row($result)) {
			$data_sample[] = array(
				  'Latitude' => $row[0]
				, 'Longitude' => $row[1]
				, 'Street' => $row[2]
				, 'Value' => $row[3]
			);
		}
		
		// Close connection to postgreSQL DB
		pg_close($conn);
		
		echo json_encode($data_sample);
	}
	
	function update_api_call_count ($api_key) {
		
		$query = "
			UPDATE users SET 
					api_calls = COALESCE(api_calls, 0) + 1 
			WHERE 	id = (SELECT user_id FROM user_keys 
				WHERE key = '" . pg_escape_string($api_key) . "' LIMIT 1)
		";
		
		$conn = get_postgresql_db_auth_connection();
		
		$result = pg_query($conn, $query);
	}
	
	function get_postgresql_db_connection ($db_name='walkspan') {
		
		global $data_conn;
		
		$pgdbconn = pg_connect(
			" host=" . $data_conn->host . 
			" port=" . $data_conn->port .
			" dbname=" . $db_name . 
			" user=" . $data_conn->user . 
			" password=" . $data_conn->password
		) or die ('An error occurred.\n');
		
		return $pgdbconn;
	}
?>
