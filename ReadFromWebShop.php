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
		'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:94.0) Gecko/20100101 Firefox/94.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
		'Accept-Encoding' => 'identity',//'gzip, deflate, br',
		'Accept-Language' => 'en-US,en;q=0.5',
		'Cache-Control' => 'max-age=0',
		'Host' =>	'www.stopkovefrezy.cz',
		'Referer' =>	'https://www.stopkovefrezy.cz/20191-STACO-bit-1-4-TX10x25mm-baleni-20ks-zluta-d9967.htm',
		'Sec-Fetch-Dest' =>	'document',
		'Sec-Fetch-Mode' =>	'navigate',
		'Sec-Fetch-Site' =>	'same-origin',
		'Sec-Fetch-User' =>	'?1',
		'Upgrade-Insecure-Requests' =>	'1',
		'Connection' => 'keep-alive',
		'Cookie' => 'left_menu=1; _ga=GA1.2.210065263.1625828279; _pk_id.101456.9fd9=13c43893c2e225fe.1633940525.7.1637058333.1637051987.; ssupp.vid=viAmI6_HapIQu; ssupp.visits=15; PHPSESSID=2b7meruq3kstl2lnin1jp8pq12; AUTH_TOKEN=eyJhbGciOiJSUzI1NiJ9.eyJ1c2VybmFtZSI6ImRwbGV0ZW55b3ZAZ21haWwuY29tIiwic2VjcmV0IjpudWxsLCJhbHRlcm5hdGl2ZUVtYWlsIjpudWxsLCJpc1NlY3JldENoZWNrZWQiOm51bGwsInN5c3RlbU5hbWUiOiJ3d3cud2ViYXJlYWwuY3oiLCJzb3VyY2VOYW1lIjoiZnA4NGhwOTciLCJzb3VyY2VVc2VySWQiOjEwMTQ1NiwicmlnaHRzIjoiY29udGVudDEsY29udGVudDIsY29udGVudDMsY2…BlsXJiCXR-EQ49t3PIgSTudWm2bPY2SrtimCJJEoPWu2TQlYhUgESyy0OBtG6_UKwZRcdwNZ-lLWUiIVu0VpghQlQgdidXzrw6PfKFfNihzzOX6f-zOZJ67dr9QM5o2da5uEDA2wfEfGyZ_mpqTPcvh_uRlmUXNkTxuPoqwskCVGnys1X0mNGHx9RtMxjVuVPWfAsB-ThS5xDxeC0h9dnczjT6FtO_a-WrAC9_vCUwQZVmH_qjyZbU-sj6UIr3rYcyquTpAZiqZsN1zIWraThdRhXhDX0sbS0zc4FvWO9NZI_ADIqLNFF16wU3P1blIf0p62ZaMN6uSWmBmSUhgPVxCSpw-Hrfflfs; basket_id=2b7meruq3kstl2lnin1jp8pq12; _gid=GA1.2.2006627702.1637051980; show_cookie_message_101456_cz=no; _pk_ses.101456.9fd9=*; _gat_gtag_UA_130496584_1=1'
		]]);
		
  return $response;
}

function cleanText(string $input) {
	if (!isset($input) || trim($input) === '')
		return "";
	//htmlspecialchars($catid[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
	return str_replace("'", "", iconv('utf-8', 'ASCII//TRANSLIT', $input));
}
function createProductNodeShort(string $ean, string $amount, string $webPrice) {
	return '<product ean="'.$ean.'" amount="'.$amount.'" price="'.$webPrice.'">'.'</product>';
}
function createProductNode(string $ean, string $amount, string $webPrice, string $name, string $catname, string $desc, string $imgurl, string $nextimgurl) {
	return '<product ean="'.$ean.'" amount="'.$amount.'" price="'.$webPrice.'" name="'.$name.'" catname="'.$catname.'" desc="'.$desc.'" img="'.$imgurl.'" nextimg="'.$nextimgurl.'">'.'</product>';
}
function searchWebShop(string $ean, object $client, string &$errorMsg) {
	$url = 'https://www.stopkovefrezy.cz/search-engine.htm?slovo='.$ean.'&search_submit=&hledatjak=2';

	$response = executeGet($url, '', $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
	
		if ($crawler->filter('div.product')->count() == 0) {
			$errorMsg = 'Nothing found.';
			echo "Nothing found. Possible missing EAN   {$ean}   in WebShop."."<br/>";
			return '';
		}
		
		$productUrlSpecific = $crawler->filter('div.product')->first()->filter('a')->first()->attr('href');
		return "https://www.stopkovefrezy.cz$productUrlSpecific";		
	}
	else {
	//	$errorMsg ="Error = $ean Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
	return '';
}

function readProduct(string $productUrl, string $ean, object $client, string &$errorMsg, 
	string &$name, string &$catname, string &$webprice, string &$desc, string &$imgurl, string &$nextimgurl) {
	$url = $productUrl;

	$response = executeGet($url, '', $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
		
		// double check product page contain ean that we need 		
		$eanOnPage = $crawler->filter('div.detail-info form table.cart')->first()->filter('.product-number-text')->last()->text();
		if ($ean != $eanOnPage) {
			$errorMsg = "Ean mismatch. Search $ean but found $eanOnPage";
			echo "Ean mismatch. Search $ean but found $eanOnPage".'<br>';
			return;
		}
		
		//PRODUCTNAME
		//CATEGORYTEXT
		//PRICE
		//EAN
		//DESCRIPTION
		//IMGURL
		//NEXT IMGURLs
		
		$productName = cleanText($crawler->filter('div.product-detail-container > h1')->text());
		$productName = htmlspecialchars($productName, ENT_XML1 | ENT_COMPAT, 'UTF-8');
		$price = $crawler->filter('span.price-value')->attr('content');
		$categoryText ="";
		$categories = $crawler->filter('#wherei p a')->each(function (Crawler $node, $i) {
			$cleared = cleanText($node->text());
			return $cleared;
		});
		$res = array_shift($categories);
		$categoryText = implode(" - ", $categories);
		$productPic = ($crawler->filter('img#detail_src_magnifying_small')->count() > 0)
			? $crawler->filter('img#detail_src_magnifying_small')->attr('src')
			: "";
		if(strlen($productPic) > 0) {
			$productPic = 'https://www.stopkovefrezy.cz'.$productPic;
		}
		
		$nextProductPicsString = "";
		$nextProductPics = $crawler->filter('div.photogall > a >img')->each(function (Crawler $node, $i) {
			$url = $node->attr('src');
			return 'https://www.stopkovefrezy.cz'.$url;
		});
		$nextProductPicsString = implode(", ", $nextProductPics);
		if(strlen($nextProductPicsString) > 0) {
			$nextProductPicsString = '['.$nextProductPicsString.']';
		}
		
		$description = "";
		// we think description is in <p>
		$descItems = $crawler->filter('div#description > div.spc > p')->each(function (Crawler $node, $i) {
			return $node->text();
		});
		if (empty($descItems)) {
			// we think description is in <div>. Skip tables
			$descItems = $crawler->filter('div#description > div.spc')->children('div')->each(function (Crawler $node, $i) {
				if ($node->nodeName() != 'table' && strlen($node->text()) > 10 && $node->filter('table')->count() == 0) {
					//echo "{$i}: ".$node->text()."<br/>";
					return $node->text();
				}
			return '';
		});
		}
		$description = cleanText(implode(" ", array_filter($descItems)));
		
		//echo "$ean <br/>";
		
		$name = $productName;
		$catname = $categoryText;
		$webprice = $price;
		$desc = $description;
		$imgurl = $productPic;
		$nextimgurl = $nextProductPicsString;
			
	} else {
		$errorMsg ="Error = $url Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
}

	
if (isset($_POST["read"])) {
	echo "start <br/>";
	$errorMsg = '';

	//$xml = '<root_product>';
	$nextEan="ITA.01040505"; 		// last ean before  Maximum execution time of ...
	//$nextEan="";
	// Read products from WebShop by EAN.

	$fileName = 'OutputXML/templateAll.xml'; //'OutputXML/templateAll.xml')
	if (file_exists($fileName)) {
		$xmlTemplate = simplexml_load_file($fileName);
	 
		$nodes = $xmlTemplate->children();
	 
		foreach ($nodes as $nodeName => $nodeValue) {
			$ean = (string)$nodeValue['ean'];
			
			// if nextEan is set, skip reading while we don't find this ean
			if ($nextEan != "" && $nextEan != $ean) {
				//echo "1: {$ean} <br/>";
				continue;
			}
			if ($nextEan != "") {
				//echo "2: {$ean} <br/>";
				$nextEan="";
			}
			
			//echo "3: {$ean} <br/>";
			//continue;
			//
			//echo "4: {$ean} <br/>";
			
			$amount = (string)$nodeValue['amount'];
			$wp = (string)$nodeValue['wp'];
			
			$xml = "";
			echo "$ean <br/>";
			
			$productUrl = searchWebShop( $ean, $client, $errorMsg);
			if (empty($productUrl)) {
				// no such product on webshop - take info from xmlTemplate
				$xml .= createProductNodeShort($ean, $amount, $wp);
			} else {
				$name=""; $catname=""; $webprice=""; $desc=""; $imgurl=""; $nextimgurl="";
				$errorMsg = "";
				readProduct($productUrl, $ean, $client, $errorMsg, $name, $catname, $webprice, $desc, $imgurl, $nextimgurl);
				if (empty($errorMsg) && !empty($name) && !empty($webprice)) { 
					// we successfully read product properties from webshop. write them to output xml
					$xml .= createProductNode($ean, $amount, $webprice, $name, $catname, $desc, $imgurl, $nextimgurl);
				} else {
					// we can't read info from webshop - take info from xmlTemplate
					// shouldn't be here
					$xml .= createProductNodeShort($ean, $amount, $wp);
				}				
			}
			
			// write to file
			file_put_contents("OutputXML/output1.xml", $xml.PHP_EOL, FILE_APPEND | LOCK_EX);
        }
		
		//$xmlTemplate->product
		//print_r($xmlTemplate);
		
	 	//$eans = [
		///*'20191.STACO', '20783.STACO'*/	
		//'DGM.060016065.0SC4',
		//'88287.STACO',
		//'113.130.11'
		//];
		//$index = 20;
		//
		//foreach ($eans as $ean) {
		//	$xml = '<root_product>';
		//		
		//	$price = searchWebShop( $ean, $client, $errorMsg, $xml);
		//
		//	$xml .= "</root_product>";
		//	$xml = iconv('WINDOWS-1250', 'ASCII//TRANSLIT', $xml);		
		//
		//	echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
		//	/*
		//	$sxe = new SimpleXMLElement($xml);
		//	$dom = new DOMDocument('1,0');
		//	$dom->preserveWhiteSpace = false;
		//	$dom->formatOutput = true;
		//	$dom->loadXML($sxe->asXML());			
		//
		//	$dom->save("OutputXML/template$index.xml");
		//	$index++;
		//	*/
		//}
		
	} else {
		exit("Failed to open {$fileName}");
	}
	/*
	$xml .= "</root_product>";
	$sxe = new SimpleXMLElement($xml);
	$dom = new DOMDocument('1,0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($sxe->asXML());			

	$dom->save("OutputXML/output1.xml");
	*/
	
	//echo '<pre>', htmlentities($xml, ENT_XML1, "cp1252"), '</pre>';
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
    <h2>Read from WebShop</h2>

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