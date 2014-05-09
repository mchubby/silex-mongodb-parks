<?php
//Allow PHP's built-in server to serve our static content in local dev:
if (php_sapi_name() === 'cli-server' && is_file(__DIR__.'/static'.preg_replace('#(\?.*)$#','', $_SERVER['REQUEST_URI']))
   ) {
  return false;
}

require 'vendor/autoload.php';
use Symfony\Component\HttpFoundation\Response;
$app = new \Silex\Application();

//$app['debug'] = true;

$app->get('/', function () use ($app) {
  return $app->sendFile('static/index.html');
});

$app->get('/hello/{name}', function ($name) use ($app) {
  return new Response( "Hello, {$app->escape($name)}!");
});

//// An alternative method for serving our static content via Silex:
//$app->get('/css/{filename}', function ($filename) use ($app){
//  if (!file_exists('static/css/' . $filename)) {
//    $app->abort(404);
//  }
//  return $app->sendFile('static/css/' . $filename, 200, array('Content-Type' => 'text/css'));
//});

$app->get('/parks', function () use ($app) {
  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
  $client = new MongoClient($db_connection);
  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
  $parks = new MongoCollection($db, 'tcl_metro_a');
  $result = $parks->find();

  $response = "[";
  foreach ($result as $park){
    $response .= json_encode($park);
    if( $result->hasNext()){ $response .= ","; }
  }
  $response .= "]";
  return $app->json(json_decode($response));
});

$app->get('/parks/within', function () use ($app) {
  $db_connection = getenv('OPENSHIFT_MONGODB_DB_URL') ? getenv('OPENSHIFT_MONGODB_DB_URL') . getenv('OPENSHIFT_APP_NAME') : "mongodb://localhost:27017/";
  $client = new MongoClient($db_connection);
  $db = $client->selectDB(getenv('OPENSHIFT_APP_NAME') ? getenv('OPENSHIFT_APP_NAME') : "test");
  $parks = new MongoCollection($db, 'tcl_metro_a');

  #clean these input variables:
  $lat1 = floatval($app->escape($_GET['lat1']));
  $lat2 = floatval($app->escape($_GET['lat2']));
  $lon1 = floatval($app->escape($_GET['lon1']));
  $lon2 = floatval($app->escape($_GET['lon2']));
  
  if(!(is_float($lat1) && is_float($lat2) &&
       is_float($lon1) && is_float($lon2))){
    $app->json(array("error"=>"lon1,lat1,lon2,lat2 must be numeric values"), 500);
  }else{
    $parks->ensureIndex(array( 'pos' => '2d'));
    $result = $parks->find( 
      array( 'pos' => 
        array( '$within' => 
          array( '$box' =>
            array(
              array( $lon1, $lat1),
              array( $lon2, $lat2)
    )))));
  }
  try{ 
    $response = "[";
    foreach ($result as $park){
      $response .= json_encode($park);
      if( $result->hasNext()){ $response .= ","; }
    }
    $response .= "]";
    return $app->json(json_decode($response));
  } catch (Exception $e) {
    return $app->json(array("error"=>json_encode($e)), 500);
  }
});

$app->run();
?>