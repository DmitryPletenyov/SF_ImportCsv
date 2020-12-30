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
							$href = $crawler->eq($rowNumber)->filter('td')->eq(1)->filter('div a')->first()->attr('href');
							
							//ProduktySzczegoly.aspx?id_artykulu=rBv72rbPPyDT3sAt-rxLl
							$start = strpos($href, 'id_artykulu=');
							$artikulId = substr($href, $start + 12);
							//echo ' '.$catalogIndex.' = '.$artikulId;
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

$cookieSearch = 'ASP.NET_SessionId=raxb2yanq15ug1is5etvjwsk; mistral=md5=976C3B5336B4EC1F8207F9F0487BE3B6; _ga=GA1.2.1386337413.1609321364; _gid=GA1.2.2084395790.1609321364; czater__first-referer=https://b2b-itatools.pl/Default.B2B.aspx; czater__63d2198880f9ca34993a3cc417bc1912fd5fb897=c02edda4a204966c53f5f779d51b0bae; czater__open2_63d2198880f9ca34993a3cc417bc1912fd5fb897=0; czater__teaser_shown=1609321500298; _gat=1';

$db = new DataSource();
$conn = $db->getConnection();

$top10Rows = "Select id, productnumber from products LIMIT 3";
$result = $db->select($top10Rows);
if (! empty($result)) {
	foreach ($result as $row) {		
		$catalogIndex = $row['productnumber'];
		//echo "======= Start ".$catalogIndex." =======";
		$artikulId = getArtikulIdByCatalogIndex($catalogIndex, $cookieSearch, $client);
		
		if ($artikulId != '') {
			echo $catalogIndex.' = '.$artikulId.'<br>';
		} else {
			echo $catalogIndex.' - '."doesn't exist in ITA".'<br>';
		}
		
		//echo "======= Finish ".$catalogIndex." =======";
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