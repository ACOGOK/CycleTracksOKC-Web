<?php

require_once('Util.php');
require_once('UserFactory_dev.php');
require_once('TripFactory_dev.php');
require_once('CoordFactory_dev.php');
require_once('Decompress.php');

define( 'DATE_FORMAT',        'Y-m-d h:i:s' );
define( 'PROTOCOL_VERSION_1', 1 );
define( 'PROTOCOL_VERSION_2', 2 );
define( 'PROTOCOL_VERSION_3', 3 );

Util::log( "+++++++++++++ Development: New Upload +++++++++++++");

/*
Util::log ( "++++ HTTP Headers ++++" );
$headers = array();
foreach($_SERVER as $key => $value) {
    if (substr($key, 0, 5) <> 'HTTP_') {
        continue;
    }
    $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
    
    Util::log ( "{$header}: {$value}" );
    //$headers[$header] = $value;
}
Util::log ( "++++++++++++++++++++++" );   
*/

<<<<<<< HEAD
$version = isset( $_POST['version'] )    ? $_POST['version']    : null; 

if ($version == null){
	$mypost = gzinflate($_POST[1]);
	//$mypost=gzopen($_POST[1], r);
	Util::log ( "++post data [0]: " );
	Util::log( $_POST[0] );
	Util::log ( "++post data [1]: " );
	Util::log( $_POST[1] );
	Util::log ( "++post data: " );
	Util::log( $_POST );
	
}

$coords   = isset( $_POST['coords'] )  ? $_POST['coords']  : null; 
$device   = isset( $_POST['device'] )  ? $_POST['device']  : null; 
$notes    = isset( $_POST['notes'] )   ? $_POST['notes']   : null; 
$purpose  = isset( $_POST['purpose'] ) ? $_POST['purpose'] : null; 
$start    = isset( $_POST['start'] )   ? $_POST['start']   : null; 
$userData = isset( $_POST['user'] )    ? $_POST['user']    : null; 


Util::log ( "version: {$version}");
=======
// take protocol from HTTP header if present; otherwise URL query var or POST body
if (isset($_SERVER['HTTP_CYCLEATL_PROTOCOL_VERSION'])) {
  $version = intval($_SERVER['HTTP_CYCLEATL_PROTOCOL_VERSION']);
} elseif (isset($_GET['version'])) {
  $version = intval($_GET['version']);
} else {
  $version = intval($_POST['version']);
}

Util::log ( "protocol version: {$version}");
>>>>>>> attempt to decompress and manually parse POST body if protocol version 3

// older protocol types use a urlencoded form body
if ( $version == PROTOCOL_VERSION_1 || $version == PROTOCOL_VERSION_2 || $version == null) {
  $coords   = isset( $_POST['coords'] )  ? $_POST['coords']  : null; 
  $device   = isset( $_POST['device'] )  ? $_POST['device']  : null; 
  $notes    = isset( $_POST['notes'] )   ? $_POST['notes']   : null; 
  $purpose  = isset( $_POST['purpose'] ) ? $_POST['purpose'] : null; 
  $start    = isset( $_POST['start'] )   ? $_POST['start']   : null; 
  $userData = isset( $_POST['user'] )    ? $_POST['user']    : null; 
} 
<<<<<<< HEAD

if ( $version == PROTOCOL_VERSION_3 && $coords != null){
	//try unzipping

	
//	Util::log( "decode base64 and unzip:" );
//	$decoded_coords = base64_decode($coords);
//	$inflated_coords = gzdecode($coords);
//	Util::log( "inflated coords data: {$inflated_coords}" );
=======
// new zipped body, still mostly urlencoded form
elseif ( $version == PROTOCOL_VERSION_3) {
  if ($_SERVER['HTTP_CONTENT_ENCODING'] == 'gzip' ||
      $_SERVER['HTTP_CONTENT_ENCODING'] == 'zlib') {
    $body = decompress_zlib($HTTP_RAW_POST_DATA);
  } else {
    $body = $HTTP_RAW_POST_DATA;
  }
  $query_vars = array();
  parse_str($body, $query_vars);
  $coords   = isset( $query_vars['coords'] )  ? $query_vars['coords']  : null;
  $device   = isset( $query_vars['device'] )  ? $query_vars['device']  : null;
  $notes    = isset( $query_vars['notes'] )   ? $query_vars['notes']   : null;
  $purpose  = isset( $query_vars['purpose'] ) ? $query_vars['purpose'] : null;
  $start    = isset( $query_vars['start'] )   ? $query_vars['start']   : null;
  $userData = isset( $query_vars['user'] )    ? $query_vars['user']    : null;
>>>>>>> attempt to decompress and manually parse POST body if protocol version 3
}

// validate device ID
if ( is_string( $device ) && strlen( $device ) === 32 )
{
	// try to lookup user by this device ID
	$user = null;
	if ( $user = UserFactory::getUserByDevice( $device ) )
	{
		Util::log( "found user {$user->id} for device $device" );
		//print_r( $user );
	}
	elseif ( $user = UserFactory::insert( $device ) )
	{
		// nothing to do
	}

	if ( $user )
	{
		// check for userData and update if needed
		if ( ( $userData = (object) json_decode( $userData ) ) &&
			 ( $userObj  = new User( $userData ) ) )
		{
			// Util::log( $userData );
			// Util::log( $userObj );
			// update user record
			if ( $tempUser = UserFactory::update( $user, $userObj ) )
				$user = $tempUser;
		}

		$coords  = (array) json_decode( $coords );
		$n_coord = count( $coords );
		Util::log( "n_coord: {$n_coord}" );

		// sort incoming coords by recorded timestamp
		// NOTE: $coords might be a single object if only 1 coord so check is_array
		if ( is_array( $coords ) )
			ksort( $coords );

		// get the first coord's start timestamp if needed
		if ( !$start )
			$start = key( $coords );

		// first check for an existing trip with this start timestamp
		if ( $trip = TripFactory::getTripByUserStart( $user->id, $start ) )
		{
			// we've already saved a trip for this user with this start time
			Util::log( "WARNING a trip for user {$user->id} starting at {$start} has already been saved" );

			header("HTTP/1.1 202 Accepted");
			$response = new stdClass;
			$response->status = 'success';
			echo json_encode( $response );
			exit;
		}
		else
			Util::log( "Saving a new trip for user {$user->id} starting at {$start} with {$n_coord} coords.." );

		// init stop to null
		$stop = null;

		// create a new trip, note unique compound key (user_id, start) required
		if ( $trip = TripFactory::insert( $user->id, $purpose, $notes, $start ) )
		{
			$coord = null;
			if ( $version == PROTOCOL_VERSION_3 )
				{
					foreach ( $coords as $coord )
				{ //( $trip_id, $recorded, $latitude, $longitude, $altitude=0, $speed=0, $hAccuracy=0, $vAccuracy=0 )
					CoordFactory::insert(   $trip->id, 
											$coord->r, //recorded timestamp
											$coord->l, //latitude
											$coord->n, //longitude
											$coord->a, //altitude
											$coord->s, //speed
											$coord->h, //haccuracy
											$coord->v ); //vaccuracy
				}

				// get the last coord's recorded => stop timestamp
				if ( $coord && isset( $coord->r ) )
					$stop = $coord->r;
				}
			else if ( $version == PROTOCOL_VERSION_2 )
			{
				foreach ( $coords as $coord )
				{
					CoordFactory::insert(   $trip->id, 
											$coord->rec, 
											$coord->lat, 
											$coord->lon,
											$coord->alt, 
											$coord->spd, 
											$coord->hac, 
											$coord->vac );
				}

				// get the last coord's recorded => stop timestamp
				if ( $coord && isset( $coord->rec ) )
					$stop = $coord->rec;
			}
			else // PROTOCOL_VERSION_1
			{
				foreach ( $coords as $coord )
				{
					CoordFactory::insert(   $trip->id, 
											$coord->recorded, 
											$coord->latitude, 
											$coord->longitude,
											$coord->altitude, 
											$coord->speed, 
											$coord->hAccuracy, 
											$coord->vAccuracy );
				}

				// get the last coord's recorded => stop timestamp
				if ( $coord && isset( $coord->recorded ) )
					$stop = $coord->recorded;
			}

			Util::log( "stop: {$stop}" );

			// update trip start, stop, n_coord
			if ( $updatedTrip = TripFactory::update( $trip->id, $stop, $n_coord ) )
			{
				Util::log( "updated trip {$updatedTrip->id} stop {$stop}, n_coord {$n_coord}" );
			}
			else
				Util::log( "WARNING failed to update trip {$trip->id} stop, n_coord" );

			header("HTTP/1.1 201 Created");
			$response = new stdClass;
			$response->status = 'success';
			echo json_encode( $response );
			exit;
		}
		else
			Util::log( "ERROR failed to save trip, invalid trip_id" );
	}
	else
		Util::log( "ERROR failed to save trip, invalid user" );
}
else
	Util::log( "ERROR failed to save trip, invalid device" );

header("HTTP/1.1 500 Internal Server Error");
$response = new stdClass;
$response->status = 'error';
echo json_encode( $response );
exit;

