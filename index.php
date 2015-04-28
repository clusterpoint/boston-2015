<?php
require'vendor/autoload.php';
require'password.php';

// Connect to ClusterPoint
$connectionStrings = array(
  'tcp://cloud-us-0.clusterpoint.com:9007',
  'tcp://cloud-us-1.clusterpoint.com:9007',
  'tcp://cloud-us-2.clusterpoint.com:9007',
  'tcp://cloud-us-3.clusterpoint.com:9007',
);
$cpsConn = new CPS_Connection(new CPS_LoadBalancer($connectionStrings), 'Wikipedia', 'demoapps@clusterpoint.com', cp_password, 'page', '//page/id', array('account' => 100028));
$cpsSimple = new CPS_Simple($cpsConn);


// Initialize Misc things
use Rain\Tpl;
$config = array(
   "tpl_dir"       => "templates/",
);
Tpl::configure( $config );
$t = new Tpl;
error_reporting(E_ERROR | E_WARNING | E_PARSE);
date_default_timezone_set('CST6CDT');


// Handle full-text search and other search terms
$t->assign('title',!isset($_GET['q'])?'Wikipedia Analytics':'Filtered by: '.$_GET['q']);
$t->assign('q',@$_GET['q']);


// Add conditions
$q = [];

if(isset($_GET['q'])){
    $q[] = CPS_Term($_GET['q']);
}

if(isset($_GET['user'])){
    $q[] = CPS_Term($_GET['user'], 'username');
}

$q[] = CPS_Term('1980/01/01 00:00:00 .. '.date("Y/m/d H:i:s",time()), 'timestamp');

$q = join('',$q);
if(!$q)$q='*';


// Add ordering
if($_GET['q']){
    $ordering = CPS_RelevanceOrdering('descending');
//    $ordering = CPS_DateOrdering('timestamp','descending');
}else{
    $ordering = CPS_DateOrdering('timestamp','descending');
}


// Fetch results and format
$articles = $cpsSimple -> search($q,null,null,null,$ordering,DOC_TYPE_ARRAY);
$articles = array_map('array_flatten',$articles);

$t->assign('articles', $articles);
$t->assign('results', $cpsSimple->getLastResponse()->getHits());
$t->assign('secs', $cpsSimple->getLastResponse()->getSeconds());


// Configure aggregation
$last_minute = CPS_Term(date("Y/m/d H:i:s",time()-60).' .. '.date("Y/m/d H:i:s",time()), 'timestamp');

$request = new CPS_SearchRequest($q=='*'?$last_minute: $q.$last_minute, 0, 20);
$request->setAggregate('category, count(id) as cnt group by category order by cnt desc limit 10');

// Execute and display results
$response = $cpsConn->sendRequest($request);
$aggregates = $response -> getAggregate(DOC_TYPE_ARRAY);
$data = array_pop($aggregates);
$t->assign('lastc', $data);












function array_flatten($array, $prefix = '') {
    $newArray = array();
    foreach($array as $key => $value) {
        if(is_array($value)) {
                $newArray = array_merge($newArray, array_flatten($value, $key));
        }
        else {
                $index = empty($prefix) ? $key : $prefix.'_'.$key;
                $newArray[$index] = $value;
             }
     }
     return $newArray;
}



$t->draw('index');



