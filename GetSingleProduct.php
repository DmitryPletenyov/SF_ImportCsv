<?php
header("Content-Type: text/html; charset=windows-1250");
include "vendor/autoload.php";

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Phppot\DataSource;

require_once 'DataSource.php';

$client = HttpClient::create();

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

function updateItaInfoStatus (object $db, int $productId, int $status, string $cnt, string $price1, string $price2, string $errorMsg) {
	$itaProductExists = false;
	$selected = $db->select("SELECT 1 AS id FROM `ita_products` WHERE product_id =".$productId);
	if (! empty($selected)) {
		if ($selected[0]['id'] > 0) {
			$itaProductExists = true;
		}
	}
	
	if ($itaProductExists)	{
		if (strlen($cnt) > 0 || strlen($price1) > 0 || strlen($price2) > 0) {
			$sql = "UPDATE `ita_products` SET itaInfoStatus_id = $status, availability ='$cnt', price1='$price1', price2='$price2', errorMsgItaInfo = '$errorMsg' WHERE product_id = $productId"; }
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
			return;
		}
		//var_dump($crawler->html());
		
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


$cookie = 'md5=976C3B5336B4EC1F8207F9F0487BE3B6; _ga=GA1.2.1386337413.1609321364; czater__first-referer=https://b2b-itatools.pl/Default.B2B.aspx; czater__63d2198880f9ca34993a3cc417bc1912fd5fb897=c02edda4a204966c53f5f779d51b0bae; ASP.NET_SessionId=raxb2yanq15ug1is5etvjwsk; _gid=GA1.2.2115415070.1611087710; czater__open2_63d2198880f9ca34993a3cc417bc1912fd5fb897=0; czater__teaser_shown=1611087752406; _gat=1';

$db = new DataSource();
$conn = $db->getConnection();

$top10Rows = 
"Select p.id, p.productnumber, ip.status_id, ip.itaInfoStatus_id, ip.artikul from products p
	LEFT JOIN ita_products ip ON p.id = ip.product_id
WHERE ip.status_id = 3 AND ip.itaInfoStatus_id  = 0
ORDER BY id 
LIMIT 500";

$result = $db->select($top10Rows);
if (! empty($result)) {
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
			updateItaInfoStatus($db, $row['id'], 1, "", "", "", "");

			$errorMsg = '';
			$cnt = '';
			$price1 = '';
			$price2 = '';
			$price = getItaInfo($artikul, $cookie, $client, $errorMsg, $cnt, $price1, $price2);
			
			
			
			if ($cnt != '' && $price1 != '' && $price2 != '') {
				// set ita status to ArtikulSuccess
				updateItaInfoStatus($db, $row['id'], 3, $cnt, $price1, $price2, $errorMsg);
				echo "$catalogIndex   Count: $cnt   Net price (including discounts): $price1 EUR   Net price (before discount): $price2 EUR<br/>";
			} else {
				// set ita status to ArtikulFailed
				updateItaInfoStatus($db, $row['id'], 2, $cnt, $price1, $price2, $errorMsg);
				echo "$catalogIndex   ErrorMsg $errorMsg<br/>";
			}
			
			//echo "======= Finish $catalogIndex $artikul =======<br/>";
		}
	}
}




if (isset($_POST["import"]) || isset($_POST["importxml"])) {
    
	$fileName = $_FILES["file"]["tmp_name"];
	
    if ($_FILES["file"]["size"] > 0) {
        

		

	}
}


?>
<!DOCTYPE html>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=windows-1250" />
<script src="jquery-3.2.1.min.js"></script>

<style>
body {
    font-family: Arial;
    width: 550px;
}

.outer-scontainer {
    background: #F0F0F0;
    border: #e0dfdf 1px solid;
    padding: 20px;
    border-radius: 2px;
}

.input-row {
    margin-top: 0px;
    margin-bottom: 20px;
}

.btn-submit {
    background: #333;
    border: #1d1d1d 1px solid;
    color: #f0f0f0;
    font-size: 0.9em;
    width: 100px;
    border-radius: 2px;
    cursor: pointer;
}

.outer-scontainer table {
    border-collapse: collapse;
    width: 100%;
}

.outer-scontainer th {
    border: 1px solid #dddddd;
    padding: 8px;
    text-align: left;
}

.outer-scontainer td {
    border: 1px solid #dddddd;
    padding: 8px;
    text-align: left;
}

#response {
    padding: 10px;
    margin-bottom: 10px;
    border-radius: 2px;
    display: none;
}

.success {
    background: #c7efd9;
    border: #bbe2cd 1px solid;
}

.error {
    background: #fbcfcf;
    border: #f3c6c7 1px solid;
}

div#response.display-block {
    display: block;
}
</style>
<script type="text/javascript">
$(document).ready(function() {

});
</script>
</head>

<body>
    <h2>Get ITA info. </h2>

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