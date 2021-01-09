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

function updateItaProductStatus (object $db, int $productId, int $status, string $artikul) {
	$itaProductExists = false;
	$selected = $db->select("SELECT 1 AS id FROM `ita_products` WHERE product_id =".$productId);
	if (! empty($selected)) {
		if ($selected[0]['id'] > 0) {
			$itaProductExists = true;
		}
	}
	
	if ($itaProductExists)	{
		$sql = "UPDATE `ita_products` SET status_id = $status, artikul ='$artikul' WHERE product_id = $productId";
		$res = $db->execute($sql);
		
		// if works ok - return nothing
		//if (! empty($res)) {
		//	echo "Success update $res ";
		//} else {
		//	echo "Error in updateItaProductStatus $productId $status $artikul ";
		//}
		
	} else {
		$sqlInsert = "INSERT into ita_products (product_id, artikul, status_id)
			   values (?,?,?)";
		$paramType = "isi";
		$paramArray = array(
			$productId,
			$artikul,
			$status
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

function getArtikulIdByCatalogIndex(string $catalogIndex, string $cookieSearch, object $client) {
	$artikulId = "";
	
	$url = 'https://b2b-itatools.pl/ProduktyWyszukiwanie.aspx?mikat=-2147483648&search='.$catalogIndex;

	$response = executeGet($url, $cookieSearch, $client);

	$statusCode = $response->getStatusCode();


	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
		
		if ($crawler->filter('input#ctl00_MainContent_tbLogin')->count() > 0) {
			echo 'Need to log in. Possible Expired cookies.'.'<br>';
		}
		//var_dump($crawler->html());
		
		// href="javascript:__doPostBack('ctl00$MainContent$miMistralKategorie','kategoria_32362')"
		$foundItem = $crawler->filter('div.kafelek');
		
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
					}
					
					if ($rowWasFound == false) {
						echo "Row for ".$catalogIndex." wasn't wound".'<br>';
					}
				}else {
					echo 'Status code ='.$statusCode.' for '.$url.'<br>';
				}
			}
		} else {
			//echo 'Nothing was found'.'<br>';
		}	
	} else {
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
	
	return $artikulId;
}

$cookieSearch = 'mistral=md5=976C3B5336B4EC1F8207F9F0487BE3B6; _ga=GA1.2.1386337413.1609321364; czater__first-referer=https://b2b-itatools.pl/Default.B2B.aspx; czater__63d2198880f9ca34993a3cc417bc1912fd5fb897=c02edda4a204966c53f5f779d51b0bae; ASP.NET_SessionId=raxb2yanq15ug1is5etvjwsk; _gid=GA1.2.2044561183.1610191500; _gat=1; czater__open2_63d2198880f9ca34993a3cc417bc1912fd5fb897=0; czater__teaser_shown=1610191540164';

$db = new DataSource();
$conn = $db->getConnection();

$top10Rows = 
"Select p.id, p.productnumber, ip.status_id from products p
	LEFT JOIN ita_products ip ON p.id = ip.product_id
WHERE ip.status_id IS NULL or ip.status_id = 0
ORDER BY id 
LIMIT 60";

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
			updateItaProductStatus($db, $row['id'], 1, "");

			$artikulId = getArtikulIdByCatalogIndex($catalogIndex, $cookieSearch, $client);
			
			
			if ($artikulId != '') {
				// set ita status to ArtikulSuccess
				updateItaProductStatus($db, $row['id'], 3, $artikulId);
				echo $catalogIndex.' = '.$artikulId.'<br>';
			} else {
				// set ita status to ArtikulFailed
				updateItaProductStatus($db, $row['id'], 2, "");
				echo $catalogIndex.' - '."doesn't exist in ITA".'<br>';
			}
			
			//echo "======= Finish ".$catalogIndex." =======";
		}
	}
}

// Limit of 120 sec. It takes 10 sec to process each record. 
// maybe add query string parameter (1-9, 10-19, 20-29, ...) and run in parallel? DB lock?
// Add db table with ITA internal id (artikulId)

			
/*
$catalogIndex = 'DTK.18.045.16.0SR';
$artikulId = getArtikulIdByCatalogIndex($catalogIndex, $cookieSearch, $client);
$catalogIndex = 'EVLC Jig1';
$artikulId = getArtikulIdByCatalogIndex($catalogIndex, $cookieSearch, $client);
$catalogIndex = '193.12.032.090.12Ra';
$artikulId = getArtikulIdByCatalogIndex($catalogIndex, $cookieSearch, $client);
*/



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
    <h2>Crawler test. Get ITA artikul id by catalog index. </h2>

    <div id="response"
        class="<?php if(!empty($type)) { echo $type . " display-block"; } ?>">
        <?php if(!empty($message)) { echo $message; } ?>
        </div>
    <div class="outer-scontainer">
        <div class="row">

            <form class="form-horizontal" action="" method="post"
                name="frmExcelImport" id="frmExcelImport"
                enctype="multipart/form-data">
                <div class="input-row">
                    <label class="col-md-4 control-label">Choose .xlsx
                        File</label> <input type="file" name="file"
                        id="file" accept=".xlsx">
                    <button type="submit" id="submit" name="import" class="btn-submit">Read .xlsx file</button>
                    <br />

                </div>

            </form>

        </div>
		
		<?php 
		if (!empty($xml)) { 
			echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>'; }
		?>
		


    </div>

</body>

</html>