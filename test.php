<?php
header("Content-Type: text/html; charset=windows-1250");
include "vendor/autoload.php";
include "help-functions.php";

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

function tofloat($num) {
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

function getItaProduct(string $artikul, string $cookieSearch, object $client, string &$errorMsg, string &$cnt, string &$price1, string &$price2) {
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

function getItaCategory(object $db, string $mikat, string $cookie, object $client, string &$errorMsg) {
	$url = 'https://b2b-itatools.pl/ProduktyWyszukiwanie.aspx?search=&mikat='.$mikat;

	//echo "$url <br>";
	$response = executeGet($url, $cookie, $client);
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
		$catids = $crawler->filter('div.kafelek')->each(function (Crawler $node, $i) {
			$href = $node->filter('a')->first()->attr('href');
			$pos = strrpos($href, "kategoria_");
			$pos2 = strrpos($href, "'");
			if ($pos !== false && $pos2 !== false && $pos+10 < $pos2) {
				$cat = substr($href, $pos+10,  $pos2-($pos+10));
				//echo "$cat </br>";
				return $cat;
			} else {
				return "";
			}
		});
		
		foreach ($catids as $catid) {
			if ($catid !== "") {
				echo "- subcategory: $catid -</br>";
				getItaSubCategory($db, $mikat, $catid, $cookie, $client, $errorMsg);
			}
		}		 
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
}

function getItaSubCategory(object $db, string $cat, string $mikat, string $cookie, object $client, string &$errorMsg) {
	$url = 'https://b2b-itatools.pl/ProduktyWyszukiwanie.aspx?search=&mikat='.$mikat;

	$response = executeGet($url, $cookie, $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
		
	
		if ($crawler->filter('input#ctl00_MainContent_tbLogin')->count() > 0) {
			$errorMsg = 'Need to log in. Possible Expired cookies.';
			echo 'Need to log in. Possible Expired cookies.'.'<br>';
			return;
		}

		$artikulids = $crawler->filter('tbody.tbxRows tr')->each(function (Crawler $node, $i) {
			// init default href to skip exception
			$href ='id_artykulu=0';
			
			// Availability column is 5th from the end. 
			$tdCount = $node->filter('td')->count();
			if ($node->filter('td')->eq($tdCount - 5)->filter('a')->count() > 0) {
				$href = $node->filter('td')->eq($tdCount - 5)->filter('a')->first()->attr('href');
			}
			else {
				// If there is no "Planned deliveries", try to get href from picture column (2nd column)
				if ($node->filter('td')->eq(1)->filter('div a')->count() > 0) {
					$href = $node->filter('td')->eq(1)->filter('div a')->first()->attr('href');
				}
			}
			
			//ProduktySzczegoly.aspx?id_artykulu=rBv72rbPPyDT3sAt-rxLl
			$start = strpos($href, 'id_artykulu=');
			$artikulId = substr($href, $start + 12);			
			
			// start with "_doPostBack" mean it failed
			if ($artikulId !== "0" && !str_starts_with($artikulId, "_doPostBack") ) {
				return $artikulId;
			} else {
				return "";
			}
		});
		
		foreach ($artikulids as $artikulid) {
			insertImportPlanRow($db, $cat, $mikat, $artikulid);
			echo "-- $artikulid --</br>";
		}		 
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
}

// Insert into import_plan (cat, subcat, artikul, itaInfoStatus_id) values ('11', '111', 'adsf-JJh8z554xxcv', 0) 
function insertImportPlanRow (object $db, string $cat, string $subcat, string $artikul) {
	$importPlanRowExists = false;
	$selected = $db->select("SELECT 1 AS id FROM `import_plan` WHERE cat ='$cat' AND subcat ='$subcat' AND artikul ='$artikul'");
	if (! empty($selected)) {
		if ($selected[0]['id'] > 0) {
			$importPlanRowExists = true;
		}
	}
	
	if ($importPlanRowExists)	{		
	} else {
		// insert row
		$sqlInsert = "INSERT into import_plan (cat, subcat, artikul, itaInfoStatus_id)
			   values (?,?,?,?)";
		$paramType = "sssi";
		$paramArray = array(
			$cat,
			$subcat,
			$artikul,
			0
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

function updateImportPlanRowStatus (object $db, int $id, int $status, string $productnumber) {
	$exists = false;
	$selected = $db->select("SELECT 1 AS id FROM `import_plan` WHERE Id =".$id);
	if (! empty($selected)) {
		if ($selected[0]['id'] > 0) {
			$exists = true;
		}
	}
	
	if ($exists)	{		
		if (($status == 3 || $status == 2) && !empty($productnumber)) {
			$sql = "UPDATE `import_plan` SET itaInfoStatus_id = $status, productnumber ='$productnumber', imported = NOW() WHERE Id = $id"; 
		} else {
			$sql = "UPDATE `import_plan` SET itaInfoStatus_id = $status WHERE Id = $id"; 		
		}
		
		$res = $db->execute($sql);		
	}	
}

function productWasImported(object $db, int $id) {
	$sqlSelect = "SELECT count(1) as cnt FROM import_plan as ip WHERE ip.Id =$id and ip.productid IS NOT NULL ";
	$result = $db->select($sqlSelect);
    if (! empty($result)) {
		echo 'cnt = '.$result[0]['cnt'];
		if ($result[0]['cnt'] > 0) {
			return true;
		}
	}
	
	return false;
}
		
function importOneProduct (object $db, string $cookie, object $client,
	$token, $jsonResponse, $apiServer, $apiKey,
	int $id, string $cat, string $subcat, string $artikul, string $cateshop) {
	$url = 'https://b2b-itatools.pl/ProduktySzczegoly.aspx?id_artykulu='.$artikul;
	
	$response = executeGet($url, $cookie, $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
		
	
		if ($crawler->filter('input#ctl00_MainContent_tbLogin')->count() > 0) {
			$errorMsg = 'Need to log in. Possible Expired cookies.';
			echo 'Need to log in. Possible Expired cookies.'.'<br>';
			return;
		}
		
		$header = $crawler->filter('div#szczegolyProduktu h1 span')->eq(2)->text();
		//$header = str_replace("\r", '', $header);
		//$header = str_replace("\n", '', $header);
		
		// dummy translation
		$pos = strpos($header, "Frez");
		if ($pos !== false ) {
			$header = str_replace("Frez", "Fréza", $header);
		}
		//Łożysko
		$pos = strpos($header, "Łożysko");
		if ($pos !== false ) {
			$header = str_replace("Łożysko", "Ložisko", $header);
		}
		$pos = strpos($header, "łożysko");
		if ($pos !== false ) {
			$header = str_replace("łożysko", "ložisko", $header);
		}
		
		$cnt = $crawler->filter('div#daneDodatkowe p')->eq(2)->text();
		$productnumber = $crawler->filter('div#daneDodatkowe p')->eq(4)->text();
		// Catalogue index: 112.030.11
		if (!empty($productnumber)) {
			$productnumber = trim(substr($productnumber, 17));
		}
		
		// ---- Net price (including discounts) ---- 
		$price1 = "";
		$price1html = $crawler->filter('div#szczegolyProduktu p.cena_netto')->first()->html('Missing price1', false);
		
		if ($price1html == 'Missing price1') { 
			$price1 =  '';
		}
		else {
			$price1 = substr($price1html, 0, strpos($price1html, '<'));
		}
		
		// ---- Net price (before discount) ---		
		$price2 = "";		
		$price2table = $crawler->filter('table#tabelaInfoDodatkowe tr');
		foreach ($price2table as $domElement) {
			if (str_starts_with($domElement->nodeValue, 'Net price (before discount):')) {
				// get number from "Net price (before discount):175,00 EUR"
				$start = strpos($domElement->nodeValue, ':');
				$price2 = substr($domElement->nodeValue, $start+1, (strpos($domElement->nodeValue, ' ', $start)-$start-1));
			}			
		}
		
		// ---- Foto ---
		$pic = "";
		try {
			$pic = $crawler->filter('div#listaZdjec a')->first()->attr('href');
		} catch (Exception $e) {
		}

		if ($pic !== "") {
			$picurl = "https://b2b-itatools.pl/".$pic;
			//echo " try to save $picurl to /importpics/$productnumber.bmp";
			//echo __DIR__;			
			
			$ch = curl_init($picurl);
			$fp = fopen(__DIR__."/importpics/$productnumber.bmp", 'wb');
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_exec($ch);
			curl_close($ch);
			fclose($fp);
		}
		
		if (!empty($productnumber) && !empty($price1) && !empty($price2) && !empty($header) /*&& !productWasImported($db, $id)*/) {
			updateImportPlanRowStatus($db, $id, 1, "");
			
			// TODO: check if there is no product with such productnumber - create product in eshop
			// go to https://www.stopkovefrezy.cz/search-engine.htm?slovo=DGM.060020055.0LBB4&search_submit=&hledatjak=2
			
			$cntint = (strlen($cnt) > 2 ) ? 10 : intval($cnt);
			
			//echo " --- pn: $productnumber p1: $price1 p2: $price2 h: $header  cnt: $cntint cat: $cateshop ---"; 			
			
			$r = createSingleProduct($token, $jsonResponse, $apiServer, $apiKey, $header, $header, $cntint, tofloat($price1), tofloat($price2), $productnumber, true, $cateshop);
			//INSERT INTO `import_cat`(`cat`, `cateshop`, `eshopname`) VALUES ('20112','33-953-954-0','')
			if ($r === true) {
				echo "-- $productnumber: OK --";
				updateImportPlanRowStatus($db, $id, 3, $productnumber);
				
				
			} else {
				echo "-- $productnumber: NOT OK --";
				updateImportPlanRowStatus($db, $id, 2, $productnumber);
			}			
		}
	
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
}

function  importProducts (object $db, string $cookie, object $client) {
	$sqlSelect = "SELECT ip.Id, ip.artikul, ip.cat, ip.subcat, ip.itaInfoStatus_id, ic.cateshop 
FROM import_plan as ip 
LEFT JOIN import_cat as ic on ic.cat = ip.subcat
WHERE ip.itaInfoStatus_id =0 and ip.subcat=36612
ORDER by ip.Id LIMIT 100 ";
	$result = $db->select($sqlSelect);
    if (! empty($result)) {
		$all = createCurlConnection();
		$token = $all[0];
		$jsonResponse = $all[1];
		$apiServer = $all[2];
		$apiKey = $all[3];
		
		$i = 1;
		foreach ($result as $row) {
			echo "$i   :   ".$row['Id']."   :   ";
			importOneProduct ($db, $cookie, $client, 
				$token, $jsonResponse, $apiServer, $apiKey,
				$row['Id'], $row['cat'], $row['subcat'], $row['artikul'], $row['cateshop']);
			//updateSingleProduct($token, $jsonResponse, $apiServer, $apiKey, $row['productId'], $row['AmountNew'], $row['webPriceNew'], $row['sellPriceNew'], $row['productnumber']);
			echo "</br>";
			$i++;
			//echo $row['productnumber']." ".$row['productId']." ".$row['AmountNew']." ".$row['PriceNew']." </br>"; 
		}
		
		//curl_close($curl);
	} else {
		echo "No products in import plan";
	}
}


$cookie = 'mistral=md5=5CF8AF96B465FC3C85E4A9B2718A203B; _ga=GA1.2.1362453477.1607516709; czater__first-referer=https://b2b-itatools.pl/Default.B2B.aspx; czater__63d2198880f9ca34993a3cc417bc1912fd5fb897=eae29a7bfd11b99d10de1c243836d880; ASP.NET_SessionId=0210mkeidqvj3xg5ka1ss3jh; _gid=GA1.2.589065378.1625689549; _gat=1';

$db = new DataSource();
$conn = $db->getConnection();

// get all products from all subcategories from category and write them to import plan
/*
$mikat ='12126';
$errorMsg = '';
$price = getItaCategory($db, $mikat, $cookie, $client, $errorMsg);
*/

// import products due to import plan
importProducts($db, $cookie, $client);


//		$all = createCurlConnection();
//		$token = $all[0];
//		$jsonResponse = $all[1];
//		$apiServer = $all[2];
//		$apiKey = $all[3];
//		
//$r = createSingleProduct($token, $jsonResponse, $apiServer, $apiKey, "name", "secondName", 13, 99.99, 0.99, "123.123.123.123", true, "33-953-954-0");
////INSERT INTO `import_cat`(`cat`, `cateshop`, `eshopname`) VALUES ('20112','33-953-954-0','')
//if ($r === true) {
//	echo "-- OK --";
//}

//$r = productWasImported ($db, 904);
//var_dump($r);
//echo "result: $r";

?>


<!DOCTYPE html>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=windows-1250" />
<script src="jquery-3.2.1.min.js"></script>
</head>

<body>
    <h2>Import ITA product. </h2>

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