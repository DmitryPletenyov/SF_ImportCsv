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

function updateItaProductStatus (object $db, int $productId, int $status, string $artikul, string $errorMsg) {
	$itaProductExists = false;
	$selected = $db->select("SELECT 1 AS id FROM `ita_products` WHERE product_id =".$productId);
	if (! empty($selected)) {
		if ($selected[0]['id'] > 0) {
			$itaProductExists = true;
		}
	}
	
	if ($itaProductExists)	{
		$sql = "UPDATE `ita_products` SET status_id = $status, artikul ='$artikul', errorMsg = '$errorMsg' WHERE product_id = $productId";
		$res = $db->execute($sql);
		
		// if works ok - return nothing
		//if (! empty($res)) {
		//	echo "Success update $res ";
		//} else {
		//	echo "Error in updateItaProductStatus $productId $status $artikul ";
		//}
		
	} else {
		$sqlInsert = "INSERT into ita_products (product_id, artikul, status_id, errorMsg)
			   values (?,?,?,?)";
		$paramType = "isis";
		$paramArray = array(
			$productId,
			$artikul,
			$status,
			$errorMsg
		);
		$insertId = $db->insert($sqlInsert, $paramType, $paramArray);
		
		// if works ok - return nothing
		//if (! empty($insertId)) {
		//	echo "Success insert $insertId ";
		//} else {
		//	echo "Error in updateItaProductStatus $productId $status $artikul";
		//}
	}	
}

function getArtikulIdByCatalogIndex(string $catalogIndex, string $cookieSearch, object $client, string &$errorMsg) {
	$artikulId = "";
	
	$url = 'https://b2b-itatools.pl/ProduktyWyszukiwanie.aspx?mikat=-2147483648&search='.$catalogIndex;

	$response = executeGet($url, $cookieSearch, $client);

	$statusCode = $response->getStatusCode();


	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
		
		if ($crawler->filter('input#ctl00_MainContent_tbLogin')->count() > 0) {
			$errorMsg = 'Need to log in. Possible Expired cookies.';
			echo 'Need to log in. Possible Expired cookies.'.'<br>';
			return $artikulId;
		}
		//var_dump($crawler->html());
		
		// href="javascript:__doPostBack('ctl00$MainContent$miMistralKategorie','kategoria_32362')"
		$foundItem = $crawler->filter('div.kafelek');
		
		//echo "Found count ".$foundItem->count()."<br>";
		
		// 26 found items means nothing was found
		if ($foundItem->count() > 0 && $foundItem->count() < 26) {
			// something was found
			// try to get category id 
			$href = $foundItem->first()->filter('a')->first()->attr('href');
			
			if (strlen($href) > 0) {
				$start = strpos($href, 'kategoria_');
				$href = substr($href, $start);
				$end = strpos($href, "'");
				$catId = substr($href, 10, $end - 10);
				
				$url = 'https://b2b-itatools.pl/ProduktyWyszukiwanie.aspx?search=&mikat='.$catId;
				
				$response = executeGet($url, $cookieSearch, $client);
				$statusCode = $response->getStatusCode();
				if ($statusCode == 200) {
					$content = $response->getContent();
					$crawler = new Crawler($content);
					
					$crawler = $crawler->filter('tbody.tbxRows tr');
					
					// find row in result table
					$rowNumber = -1;
					$rowWasFound = false;
					foreach ($crawler as $domElement) {
						$rowNumber++;
						
						//$t = $domElement->nodeValue;
						//echo "row number $rowNumber : $t";
						if (str_contains ($domElement->nodeValue, 'Catalogue index:'.$catalogIndex)) {
							$rowWasFound = true;
							
							// init default href to skip exception
							$href ='id_artykulu=0';
							
							// Availability column is 5th from the end. 
							$tdCount = $crawler->eq($rowNumber)->filter('td')->count();
							if ($crawler->eq($rowNumber)->filter('td')->eq($tdCount - 5)->filter('a')->count() > 0) {
								$href = $crawler->eq($rowNumber)->filter('td')->eq($tdCount - 5)->filter('a')->first()->attr('href');
							}
							else {
								// If there is no "Planned deliveries", try to get href from picture column (2nd column)
								if ($crawler->eq($rowNumber)->filter('td')->eq(1)->filter('div a')->count() > 0) {
									$href = $crawler->eq($rowNumber)->filter('td')->eq(1)->filter('div a')->first()->attr('href');
								}
							}
							
							//ProduktySzczegoly.aspx?id_artykulu=rBv72rbPPyDT3sAt-rxLl
							$start = strpos($href, 'id_artykulu=');
							$artikulId = substr($href, $start + 12);
							break;
						}
						//echo " <br/>";
					}
					
					if ($rowWasFound == false) {
						$errorMsg = "Row for ".$catalogIndex." was not found";
						echo "Row for ".$catalogIndex." was not found".'<br>';
					}
				}else {
					$errorMsg ="PN = $catalogIndex Status code =".$statusCode.' for '.$url;
					echo 'Status code ='.$statusCode.' for '.$url.'<br>';
				}
			}
		} else {
			$errorMsg ="Nothing was found by $catalogIndex";
			//echo 'Nothing was found'.'<br>';
		}	
	} else {
		$errorMsg ="PN = $catalogIndex Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
	
	return $artikulId;
}

$cookieSearch = 'mistral=md5=5CF8AF96B465FC3C85E4A9B2718A203B; _ga=GA1.2.1362453477.1607516709; czater__first-referer=https://b2b-itatools.pl/Default.B2B.aspx; czater__63d2198880f9ca34993a3cc417bc1912fd5fb897=eae29a7bfd11b99d10de1c243836d880; ASP.NET_SessionId=0210mkeidqvj3xg5ka1ss3jh; _gid=GA1.2.927750015.1617223665';

$db = new DataSource();
$conn = $db->getConnection();

$top10Rows = 
"Select p.id, p.productnumber, ip.status_id from products p
	LEFT JOIN ita_products ip ON p.id = ip.product_id
WHERE ip.status_id IS NULL or ip.status_id = 0
ORDER BY id 
LIMIT 100";
/*
"Select p.id, p.productnumber, ip.status_id from products p
	LEFT JOIN ita_products ip ON p.id = ip.product_id
WHERE ip.status_id IS NULL or ip.status_id = 0
ORDER BY id 
LIMIT 100";
*/

$result = $db->select($top10Rows);
if (! empty($result)) {
	foreach ($result as $row) {	
		$catalogIndex = $row['productnumber'];
		
		//if code run in parallel (2 browser windows) we need to check if we start to process this ita product before
		$itaProductWasRequestedBefore = false;
		$selected = $db->select("SELECT 1 AS id FROM `ita_products` WHERE product_id =".$row['id']." AND status_id > 0");
		if (! empty($selected)) {
			if ($selected[0]['id'] > 0) {
				$itaProductWasRequestedBefore = true;
			}
		}
	
		if (!$itaProductWasRequestedBefore) {
			//echo "======= Start ".$catalogIndex." =======";
			
			// set ita status to ArtikulRequested
			updateItaProductStatus($db, $row['id'], 1, "", "");

			$errorMsg = '';
			$artikulId = getArtikulIdByCatalogIndex($catalogIndex, $cookieSearch, $client, $errorMsg);
			
			
			if ($artikulId != '') {
				// set ita status to ArtikulSuccess
				updateItaProductStatus($db, $row['id'], 3, $artikulId, $errorMsg);
				echo $catalogIndex.' = '.$artikulId.'<br>';
			} else {
				// set ita status to ArtikulFailed
				updateItaProductStatus($db, $row['id'], 2, "", $errorMsg);
				echo $catalogIndex.' - '."doesn't exist in ITA. $errorMsg".'<br>';
			}
			
			//echo "======= Finish ".$catalogIndex." =======";
		}
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
    <h2>Import ITA artikul id by catalog index. </h2>

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