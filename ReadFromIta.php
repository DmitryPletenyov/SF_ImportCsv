<?php
header("Content-Type: text/html; charset=windows-1250");
include "vendor/autoload.php";

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Phppot\DataSource;

require_once 'DataSource.php';

$client = HttpClient::create();
$xml = '';

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

function getItaCategory(string $mikat, string $cookie, object $client, string &$errorMsg, string &$xml, int $depth) {
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
			$catName= $node->filter('a')->first()->filter('span')->first()->text();
			$cleanCatName = iconv('UTF-8', 'US-ASCII//TRANSLIT', $catName);
			if ($pos !== false && $pos2 !== false && $pos+10 < $pos2) {
				$cat = substr($href, $pos+10,  $pos2-($pos+10));
				//echo "$cat </br>";
				return [$cat, $cleanCatName];
			} else {
				return ["", ""];
			}
		});
		

		echo str_repeat("--", $depth)." category $mikat [$depth]</br>";	
		foreach ($catids as $catid) {
			if ($catid !== "") {	
				$clearCatName = htmlspecialchars($catid[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');			
				getItaSubCategory( $mikat, $catid[0], $cookie, $client, $errorMsg, $xml, $depth+1, $clearCatName);
			}
		}
		
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
}

function getItaSubCategory(string $cat, string $mikat, string $cookie, object $client, string &$errorMsg, string &$xml, int $depth, string $catName) {
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

		echo str_repeat("--", $depth)." subcategory: $mikat [$depth] _ $catName _</br>";
		$catids = $crawler->filter('div.kafelek')->each(function (Crawler $node, $i) {
			$href = $node->filter('a')->first()->attr('href');
			$pos = strrpos($href, "kategoria_");
			$pos2 = strrpos($href, "'");
			$catName= $node->filter('a')->first()->filter('span')->first()->text();
			$cleanCatName = iconv('UTF-8', 'US-ASCII//TRANSLIT', $catName);
			//echo "__$cleanCatName";
			if ($pos !== false && $pos2 !== false && $pos+10 < $pos2) {
				$cat = substr($href, $pos+10,  $pos2-($pos+10));
				//echo "$cat </br>";
				return [$cat, $cleanCatName];
			} else {
				return ["", ""];
			}
		});
		
		if (empty($catids)) {
			// look in table on ProduktyWyszukiwanie.aspx
			// <a href="ProduktySzczegoly.aspx?id_artykulu=RXYO0qyr7cAGM2zx_7Uurg"><img src="Miniaturki/A2/A2CEF0438914476B4DCA9EF793F01611.jpg"></a>
			$artikulids = $crawler->filter('tbody.tbxRows tr')->each(function (Crawler $node, $i) {
				// init default href to skip exception
				$href ='id_artykulu=0';
				
				// Availability column is 4th from the end. 
				// catalog index column is 6th from the end. 
				$tdCount = $node->filter('td')->count();
				$netPrice = convertToDecimal($node->filter('td')->eq($tdCount - 3)->text());
				//echo "_$netPrice _";
				$amount = $node->filter('td')->eq($tdCount - 4)->text();
				$catIndex = $node->filter('td')->eq($tdCount - 6)->text();
				
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
			
				return [$catIndex, $amount, $artikulId, $netPrice];
				
			});
			
			$i=1;
			foreach ($artikulids as $item) {
				$webPrice = ($item[3]*27/0.6)*0.9;
				
				echo str_repeat("&nbsp;&nbsp;", $depth)."$i : $item[0] $webPrice CZK ($item[1])</br>";
				$xml .= '<product ean="'.$item[0].'" amount="'.$item[1].'" aid="'.$item[2].'" cid="'.$mikat.'" wp="'.$webPrice.'" catn="'.$catName.'">'.'</product>';
				$i++;
			}	
		} else {
			//echo "it's category ($depth) </br>";
			if ($depth < 5) {
				foreach ($catids as $catid) {
					if ($catid[0] !== "") {
						$clearCatName = htmlspecialchars($catid[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
						getItaSubCategory( $cat, $catid[0], $cookie, $client, $errorMsg, $xml, $depth+1, $clearCatName);
					}
				}
			} else {
				echo "! recursion depth ($depth) is too big</br>";
			}
		}			
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
}



$cookie = 'mistral=md5=61250E72C58AAA6AE675A424AFB42D67; _ga=GA1.2.1910299678.1625828240; smvr=eyJ2aXNpdHMiOjMsInZpZXdzIjoyMSwidHMiOjE2MzM1ODgyODQ3MzYsIm51bWJlck9mUmVqZWN0aW9uQnV0dG9uQ2xpY2siOjAsImlzTmV3U2Vzc2lvbiI6ZmFsc2V9; smuuid=17be5bac9e4-2cd94eb94f4e-9942bc3f-7a637464-8f34a8ef-a1b1a0de73e7; smclient=6234972f-3221-4ed3-93d8-50ee2fc95330; ASP.NET_SessionId=tnem403bka3dbwdo4e2mzhfq; _gid=GA1.2.2095113585.1633541387; _smvs=NEXT; _gat=1';

	
if (isset($_POST["read"])) {

echo "start <br/>";
$errorMsg = '';

//$mikat ='12270';
//$mikat ='12126';
//$mikat ='11793'; 	// router bits
//$mikat ='36035'; 	// STACO
//$mikat ='17323'; 	// DIA frezy
//$mikat ='10407'; 	// spiralove frezy

// Read products from ITA by category id recursevly. Insert top level category ids in array.

	$categories = ['9084', '20229', '20922', '33921', '35741', '39862'];
	$index = 20;

	foreach ($categories as $category) {
		$xml = '<root_product>';
			
		$mikat = $category;
		$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);

		$xml .= "</root_product>";
		$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

		//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
		
		$sxe = new SimpleXMLElement($xml);
		$dom = new DOMDocument('1,0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($sxe->asXML());			

		$dom->save("OutputXML/template$index.xml");
		$index++;
		
	}
	
/*
// OVVO products
	$xml = '<root_product>';
		
	$mikat ='43498'; 	// OVVO
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);

	$xml .= "</root_product>";
	//setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
	$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

	//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
	$sxe = new SimpleXMLElement($xml);
	$dom = new DOMDocument('1,0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($sxe->asXML());			

	$dom->save("OutputXML/products_OVVO_".date("d_m_Y").".xml");
	*/
	/*
// Minibatt products
	$xml = '<root_product>';
		
	$mikat ='39862'; 	// Minibatt
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);

	$xml .= "</root_product>";
	//setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
	$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

	//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
	$sxe = new SimpleXMLElement($xml);
	$dom = new DOMDocument('1,0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($sxe->asXML());			

	$dom->save("OutputXML/products_Minibatt_".date("d_m_Y").".xml");
	*/
	/*
// Virutex products
	$xml = '<root_product>';
		
	$mikat ='33921'; 	// Virutex
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);

	$xml .= "</root_product>";
	//setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
	$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

	//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
	$sxe = new SimpleXMLElement($xml);
	$dom = new DOMDocument('1,0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($sxe->asXML());			

	$dom->save("OutputXML/products_Virutex_".date("d_m_Y").".xml");
	*/
/*
// CMT products
	$xml = '<root_product>';
	
	$mikat ='16953'; 	// CMT tools
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);
	$mikat ='11793'; 	// CMT frezy
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);
	$mikat ='21516'; 	// CMT pily
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);	
	$mikat ='13728'; 	// CMT korunky
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);

	$xml .= "</root_product>";
	//setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
	$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

	//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
	$sxe = new SimpleXMLElement($xml);
	$dom = new DOMDocument('1,0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($sxe->asXML());			

	$dom->save("OutputXML/products_CMT_".date("d_m_Y").".xml");	
	*/
/*	
// STACO
	$xml = '<root_product>';
	$mikat ='36035'; 	// STACO
	$price = getItaCategory( $mikat, $cookie, $client, $errorMsg, $xml,  1);
	
	$xml .= "</root_product>";
	//setlocale(LC_CTYPE, 'cs_CZ.UTF-8');
	$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		

	//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
	$sxe = new SimpleXMLElement($xml);
	$dom = new DOMDocument('1,0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($sxe->asXML());			

	$dom->save("OutputXML/products_STACO_".date("d_m_Y").".xml");	
	*/
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
    width: 750px;
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

</head>

<body>
    <h2>Read amounts from ITA</h2>

    <div id="response"
        class="<?php if(!empty($type)) { echo $type . " display-block"; } ?>">
        <?php if(!empty($message)) { echo $message; } ?>
        </div>
    <div class="outer-scontainer">
        <div class="row">

            <form class="form-horizontal" action="" method="post"
                name="frmCSVImport" id="frmCSVImport"
                enctype="multipart/form-data">
                <div class="input-row">
					<button type="submit" id="submit" name="read" class="btn-submit">Read</button>
                    <br />

                </div>

            </form>

        </div>
		

    </div>

</body>

</html>