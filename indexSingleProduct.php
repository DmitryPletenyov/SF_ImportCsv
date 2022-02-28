<?php
header("Content-Type: text/html; charset=windows-1250");
include "vendor/autoload.php";

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Phppot\DataSource;

require_once 'DataSource.php';

// https is ok
$client = HttpClient::create();

//  https is not ok
//$client = HttpClient::create(['verify_peer' => false, 'verify_host' => false]);

function executeGet(string $url, string $cookie, object $client) {
	$response = $client->request('GET',     $url,
    ['headers' => [
		'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:83.0) Gecko/20100101 Firefox/83.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
		'Accept-Encoding' => 'gzip, deflate, br',
		'Host' =>	'b2b-itatools.pl',
		'Upgrade-Insecure-Requests' => '1',
		'Connection' => 'keep-alive',
		'Origin' => 'https://b2b-itatools.pl',
		'Referer' => $url,
		'TE' => 'Trailers',
		'Cookie' => $cookie]]);
		
  return $response;
}

function convertToDecimal (string $num) {
    $dotPos = strrpos($num, '.');
    $commaPos = strrpos($num, ',');
    $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
        ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
  
    if (!$sep) {
        return floatval(preg_replace("/[^0-9]/", "", $num));
    }

    return floatval(
        preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . '.' .
        preg_replace("/[^0-9]/", "", substr($num, $sep+1, strlen($num)))
    );
}

function evaluateWebPrice (object $db, int $productId, float $price1, float &$sellPrice) {
	$sql = "SELECT pe.overwriteCW, pe.overwritePC, pe.NC, pe.PC, pe.CW FROM price_evaluation as pe
	join products p on pe.productnumber = p.productnumber
WHERE p.id = $productId";
	$result = $db->select($sql);
	if (! empty($result)) {
		
		// sell price
		if ($result[0]['overwritePC'] === null){
			if ($result[0]['NC'] !== null && $result[0]['PC'] !== null) {
				$sellPrice = ($price1 * $result[0]['NC'] / $result[0]['PC']);
			}			
		}
		else {
			$sellPrice = $result[0]['overwritePC'];
		}
		
		//web price
		if ($result[0]['overwriteCW'] === null){
			if ($result[0]['NC'] === null || $result[0]['PC'] === null || $result[0]['CW'] === null) {
				return 0;
			}
			$webprice = ($price1 * $result[0]['NC'] / $result[0]['PC']) * $result[0]['CW'];
			return $webprice;
		}
		else {
			return $result[0]['overwriteCW'];
		}
	}
	
	return 0;
	
	/*
	SELECT p.productnumber, p.price, p.available, ip.webprice, ip.price1, ip.availability 
	FROM products as p join ita_products as ip on p.id = ip.product_id
	WHERE ip.status_id = 3 and ip.itainfostatus_id = 3 and ip.webprice != 0
	*/
}

function updateItaInfoStatus (object $db, int $productId, int $status, string $cnt, float $price1, float $price2, float $webprice, float $sellprice, string $errorMsg) {
	$itaProductExists = false;
	$selected = $db->select("SELECT 1 AS id FROM `ita_products` WHERE product_id =".$productId);
	if (! empty($selected)) {
		if ($selected[0]['id'] > 0) {
			$itaProductExists = true;
		}
	}
	
	if ($itaProductExists)	{
		if (strlen($cnt) > 0) {
			$sql = "UPDATE `ita_products` SET itaInfoStatus_id = $status, availability ='$cnt', price1=$price1, price2=$price2, webprice=$webprice, sellprice=$sellprice, errorMsgItaInfo = '$errorMsg' WHERE product_id = $productId"; }
		else {
			$sql = "UPDATE `ita_products` SET itaInfoStatus_id = $status,  errorMsgItaInfo = '$errorMsg' WHERE product_id = $productId"; 
		}
		
		$res = $db->execute($sql);		
	}	
}

function getItaInfo(string $artikul, string $cookieSearch, object $client, string &$errorMsg, string &$cnt, string &$price1, string &$price2) {
	$url = 'https://b2b-itatools.pl/ProduktySzczegoly.aspx?id_artykulu='.$artikul;

	//echo "$url <br>";
	$response = executeGet($url, $cookieSearch, $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
		
	
		if ($crawler->filter('input#ctl00_MainContent_tbLogin')->count() > 0) {
			$errorMsg = 'Need to log in. Possible Expired cookies.';
			echo 'Need to log in. Possible Expired cookies.'.'<br>';
			//var_dump($crawler->html());
			return;
		}
		
		// ---- Net price (including discounts) ---- 
		$price1html = $crawler->filter('div#szczegolyProduktu p.cena_netto')->first()->html('Missing price1', false);
		
		if ($price1html == 'Missing price1') { 
			$price1 =  '';}
		else {
			$price1 = substr($price1html, 0, strpos($price1html, '<'));
		}
		
		// ---- Items' count  ---- 
		$cnt = $crawler->filter('div#daneDodatkowe p')->eq(2)->text();

		// ---- Net price (before discount) ---
		$price2table = $crawler->filter('table#tabelaInfoDodatkowe tr');
		foreach ($price2table as $domElement) {
			if (str_starts_with($domElement->nodeValue, 'Net price (before discount):')) {
				// get number from "Net price (before discount):175,00 EUR"
				$start = strpos($domElement->nodeValue, ':');
				$price2 = substr($domElement->nodeValue, $start+1, (strpos($domElement->nodeValue, ' ', $start)-$start-1));
			}			
		}	
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}	
}

function getRowsCountToProcess (object $db, int $status, string $date) {
	$sql = "SELECT Count(ip.product_id) AS Cnt
	FROM ita_products ip    
    WHERE ip.itaInfoStatus_id = $status
    and ip.status_id = 3
    and ip.dt > '$date 00:00:00';";

	$result = $db->select($sql);
	if (! empty($result)) {
		return $result[0]['Cnt'];
	}
	
	return 0;
}

$cookie = '';
$updatePrices = true;
$today = new DateTime();
$lastRunDate = $today->sub(new DateInterval('P3D'));
//echo $lastRunDate->format('Y-m-d');echo "<br>";

$db = new DataSource();
$conn = $db->getConnection();
set_time_limit(0);

if (isset($_GET['c'])) {
    $cookie = $_GET['c'];
} else {    echo "No cookies in query string<br>";}

if (isset($_GET['u'])) {
    if ($_GET['u'] == "false") {$updatePrices = false;}
} else {    echo "No updatePrices in query string<br>";}

if (isset($_GET['d'])) {
    $lastRunDate = DateTime::createFromFormat('Y-m-d', $_GET['d']);
	//echo $lastRunDate->format('Y-m-d');
} else {    echo "No Last run date in query string<br>";}


$top10Rows = 
"Select p.id, p.productnumber, ip.status_id, ip.itaInfoStatus_id, ip.artikul 
from products p
	LEFT JOIN ita_products ip ON p.id = ip.product_id
WHERE ip.status_id = 3 AND ip.itaInfoStatus_id  = 0
ORDER BY id 
LIMIT 500";

// main task: process rows
$result = $db->select($top10Rows);
if (! empty($result)) {
	$i = 1;
	foreach ($result as $row) {	
		$catalogIndex = $row['productnumber'];
		$artikul = $row['artikul'];
		
		//if code run in parallel (2 browser windows) we need to check if we start to process this ita product before
		$itaInfoWasRequestedBefore = false;
		$selected = $db->select("SELECT 1 AS id FROM `ita_products` WHERE product_id =".$row['id']." AND itaInfoStatus_id > 0");
		if (! empty($selected)) {
			if ($selected[0]['id'] > 0) {
				$itaInfoWasRequestedBefore = true;
			}
		}
	
		if (!$itaInfoWasRequestedBefore) {
			//echo "======= Start $catalogIndex $artikul =======<br/>";
			
			// set ita status to Requested			
			updateItaInfoStatus($db, $row['id'], 1, "", 0, 0, 0, 0, "");

			$errorMsg = '';
			$cnt = '';
			$price1 = '';
			$price2 = '';
			$price = getItaInfo($artikul, $cookie, $client, $errorMsg, $cnt, $price1, $price2);
			
			
			
			if ($cnt != '' && $price1 != '' && $price2 != '') {
				$price1d = convertToDecimal($price1);
				$price2d = convertToDecimal($price2);
				$sellPrice = 0;
				$webprice = evaluateWebPrice($db, $row['id'], $price1d, $sellPrice);
				
				//echo "$price1d $price2d $sellPrice $webprice";
				
				// set ita status to ArtikulSuccess
				updateItaInfoStatus($db, $row['id'], 3, $cnt, $price1d, $price2d, $webprice, $sellPrice, $errorMsg);
				echo "$i   $catalogIndex   Count: $cnt   disc. price: $price1 EUR   price: $price2 EUR web price: $webprice CZK<br/>";
			} else {
				// set ita status to ArtikulFailed
				updateItaInfoStatus($db, $row['id'], 2, $cnt, 0, 0, 0, 0, $errorMsg);
				echo "$catalogIndex   ErrorMsg $errorMsg<br/>";
			}
			
			$i++;
			//echo "======= Finish $catalogIndex $artikul =======<br/>";
		}
	}
}

?>
<!DOCTYPE html>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=windows-1250" />
<script src="jquery-3.2.1.min.js"></script>

<link href="css/style.css" rel="stylesheet">

<script type="text/javascript">
$(document).ready(function() {	
	var rowsToProcess = <?php echo getRowsCountToProcess($db, 0, $lastRunDate->format('Y-m-d'))?>;
	
	// refresh page until we have rows to process
	if (rowsToProcess > 0) {
		setTimeout(function(){
			window.location.reload(1);
		}, 5000);	
	}
});
</script>
</head>

<body>
    <h2>ITA amount, prices -> DB. </h2>

    <div id="response"
        class="<?php if(!empty($type)) { echo $type . " display-block"; } ?>">
        <?php if(!empty($message)) { echo $message; } ?>
        </div>
    <div class="outer-scontainer">
        <div class="row">


        </div>


    </div>

</body>

</html>