<?php
header("Content-Type: text/html; charset=windows-1250");
include "vendor/autoload.php";
include "help-functions.php";

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Phppot\DataSource;
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

$cookieSearch = 'mistral=md5=61250E72C58AAA6AE675A424AFB42D67; _ga=GA1.2.1910299678.1625828240; smvr=eyJ2aXNpdHMiOjI4LCJ2aWV3cyI6NTY2LCJ0cyI6MTYzOTU2ODA2MzM5OCwibnVtYmVyT2ZSZWplY3Rpb25CdXR0b25DbGljayI6MCwiaXNOZXdTZXNzaW9uIjpmYWxzZX0=; smuuid=17be5bac9e4-2cd94eb94f4e-9942bc3f-7a637464-8f34a8ef-a1b1a0de73e7; smclient=6234972f-3221-4ed3-93d8-50ee2fc95330; ASP.NET_SessionId=tnem403bka3dbwdo4e2mzhfq; _smvs=NEXT; _gid=GA1.2.555270066.1639493049';

function IsNullOrEmptyString($str){
    return (!isset($str) || trim($str) === '');
}

function xml_adopt($root, $new) {
    $node = $root->addChild($new->getName(), (string) $new);
    foreach($new->attributes() as $attr => $value) {
        $node->addAttribute($attr, $value);
    }
    foreach($new->children() as $ch) {
        xml_adopt($node, $ch);
    }
}

function cleanText(string $input) {
	if (!isset($input) || trim($input) === '')
		return "";
	//htmlspecialchars($catid[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
	return  iconv('utf-8', 'ASCII//TRANSLIT', str_replace("°", "", str_replace("α", "a", $input)));
}

function getItaProduct(string $artikul, string $cookieSearch, object $client, string &$plname, string &$pldesc, string &$plparams) {
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
		
		// ---- plname pldesc ---		
		$plname = "";
		try {
			$plname = cleanText($crawler->filter('div#szczegolyProduktu h1 span')->eq(2)->text()); 
		} catch (Exception $e) {
		}
		
		$desc = "";
		$desc = $crawler->filter('div#tab_opis_produktu')->text(); 
		$lastChar = strpos($desc, "Katalogi w wersji elektronicznej");
		if ($lastChar >= 0) {
			$desc = substr($desc, 0, $lastChar);
		}
		$pldesc = cleanText($desc);
		
		// params
		$params = simplexml_load_string('<plparams></plparams>');
		$paramArray = $crawler->filter('div#tab_cechy table#tabelaInfoDodatkoweCechy tr')->each(function (Crawler $node, $i) {
			$pname = $node->filter('th')->first()->text();
			$pvalue = $node->filter('td')->first()->text();
			//echo "before iconv: $pname $pvalue</br>";
			try {
				$r = simplexml_load_string('<param><name>'.cleanText($pname).'</name><value>'.cleanText($pvalue).'</value></param>');
			} catch (Exception $e) {
				echo "error in iconv: $pname $pvalue</br>";
			}
		
		
			//$r = simplexml_load_string('<param><name>'.cleanText($pname).'</name><value>'.cleanText($pvalue).'</value></param>');
			return $r;
		});
		
		foreach ($paramArray as $p) {
			xml_adopt($params, $p);
		}
		
		//echo "XXX: ".$params->asXML()."\n";
		$plparams = $params;
				
	} else {
		$errorMsg ="Artikul = $artikul Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}	
}

if (isset($_POST["add_attr"])) {
	echo "start <br/>";
	$errorMsg = '';

	$nextEan="PRO.APS"; 		// last ean before  Maximum execution time of ...
	//$nextEan="";
	// Read products from template.
	

	$fileName = 'OutputXML/templateAll.xml'; //'OutputXML/templateAllTest.xml'; //'OutputXML/templateAll.xml';
	if (file_exists($fileName)) {
		$xmlTemplate = simplexml_load_file($fileName);	 
		$nodes = $xmlTemplate->children();
	 
		$feedFileName = 'OutputXML/feed_27.12.21.xml'; //feed_25.11.21_validated.xml	
		//$sxe = new SimpleXMLElement($feedFileName, NULL, TRUE);
		//echo htmlentities($sxe->asXML());
		
		foreach ($nodes as $nodeName => $nodeValue) {
			$ean = (string)$nodeValue['ean'];
			
			// if nextEan is set, skip reading while we don't find this ean
			if ($nextEan != "" && $nextEan != $ean) {
				continue;
			}
			if ($nextEan != "") {
				$nextEan="";
			}
			
			$aid = (string)$nodeValue['aid']; //https://b2b-itatools.pl/ProduktySzczegoly.aspx?id_artykulu=3RTNci7wixgSiX_7A2fQbQ
			
			$xml = "";
			
			//search ean in feed file
			if (file_exists($feedFileName)) {
				$content = utf8_encode(file_get_contents($feedFileName));
				$xmlFeed = simplexml_load_string($content);
				//$xmlFeed = simplexml_load_file($feedFileName);
				//echo $xmlFeed->asXML();
				
				$feedNodes = $xmlFeed->children();
				foreach ($feedNodes as $feedNodeName => $feedNodeValue) {					
					//var_dump($feedNodeValue);
					$feedEan = (string)$feedNodeValue->ean;
					
					if ($feedEan != $ean) {
						continue;
					}
					
					//echo "$feedEan == $ean</br>";
					// get node from feed
					$feedName = (string)$feedNodeValue->name;
					$feedDesc = (string)$feedNodeValue->desc;					
					$feedPlname = (string)$feedNodeValue->plname;
					$feedPldesc = (string)$feedNodeValue->pldesc;
					
					$needGetName = IsNullOrEmptyString($feedName) && IsNullOrEmptyString($feedDesc) && IsNullOrEmptyString($feedPlname) && IsNullOrEmptyString($feedPldesc) ;
					
					// check if aid is invalid
					if (str_starts_with($aid, '_doPostBack' )) {
						echo "$feedEan: invalid aid $aid</br>";
						break;
					}
					
					// if missing name,desc, plname and pldesc - it's from ITA. Let's get them
					if ($needGetName) {						
						$plname = ""; $pldesc = ""; $plparams = "";
						getItaProduct($aid, $cookieSearch, $client, $plname, $pldesc, $plparams);
						
						//echo "plname: $plname     pldesc: $pldesc <br/>";
						
						if (!IsNullOrEmptyString($plname)) {
							$feedNodeValue->plname = $plname; 
						}						
						if (!IsNullOrEmptyString($pldesc)) {
							$feedNodeValue->pldesc = $pldesc; 
						}
						
						if (!is_null($plparams)) {
							xml_adopt($feedNodeValue, $plparams);
						}						
						
						if (!IsNullOrEmptyString($plname) || !IsNullOrEmptyString($pldesc)) {
							//save as XML
							echo "$feedEan :".htmlentities($feedNodeValue->asXML())."</br>";
							//echo htmlentities($xmlFeed->asXML());
							$xmlFeed->asXML($feedFileName);
						}
					}
									
				}				
				
			}else {
				exit("Failed to open {$feedFileName}");
			}		
		}

	} else {
		exit("Failed to open {$fileName}");
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
    <h2>Add name and description (in polish language) to ITA products</h2>
	<h3>Read ITA products from templateAll.xml -> look at ITA page -> get data -> add to product node in feed.xml</h3>
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
					<button type="submit" id="submit" name="add_attr" class="btn-submit">Add name and description </button>
                    <br />

                </div>

            </form>

        </div>

    </div>

</body>

</html>