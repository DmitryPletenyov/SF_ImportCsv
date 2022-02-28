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

function formatUrl(string $input) {
	return "https://www.stopkovefrezy.cz$input";
}

function readCategory(string $caturl, int $depth, object $client, string &$xml, string &$errorMsg) {
	if ($depth == 7) return "";
	
	$url = formatUrl($caturl);

	$response = executeGet($url, '', $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
	
		$subcats = $crawler->filter('ul.subcat > li > a')->each(function (Crawler $node, $i) {
			$name = cleanText($node->text());
			$value = $node->attr('href');
			return [$name, $value];
		});
		
		if (!empty($subcats)) {
			$xml .="<subcategories>";
			
			foreach ($subcats as $subcat) {
				$subcatname = $subcat[0];
				$subcaturl = $subcat[1];
				
				echo str_repeat("--", $depth)." $subcatname</br>";		

				$xml .= "<subcat><name>$subcatname</name><url>".formatUrl($subcaturl)."</url>";			
				$res = readCategory($subcaturl, $depth+1, $client, $xml, $errorMsg);
				$xml .= "</subcat>";
			}
			$xml .="</subcategories>";
		}		
		
		return "";		
	}
	else {
	//	$errorMsg ="Error = $ean Status code =".$statusCode.' for '.$url;
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
	}
	return '';
}
	
if (isset($_POST["read"])) {
	echo "start <br/>";
	set_time_limit(0);
	$errorMsg = '';

	$url = 'https://www.stopkovefrezy.cz/category-map';

	$response = executeGet($url, '', $client);
	$statusCode = $response->getStatusCode();

	if ($statusCode == 200) {
		$content = $response->getContent();
		$crawler = new Crawler($content);
	
		$cats = $crawler->filter('div.category-all > div.grid-cell > h2.nav-category > a')->each(function (Crawler $node, $i) {
			$name = cleanText($node->text());
			$value = $node->attr('href');
			return [$name, $value];
		});
		//var_dump($cats);		
		
		// root node
		$xml = "<categories>";
		foreach ($cats as $cat) {
			$catname = $cat[0];
			$caturl = $cat[1];
			
			echo "$catname </br>";
			//echo "$catname -> $caturl</br>";
			
			$xml .= "<cat><name>$catname</name><url>".formatUrl($caturl)."</url>";
			$res = readCategory($caturl, 1,  $client, $xml,$errorMsg);
			$xml .= "</cat>";
			
		}
		$xml .="</categories>";
		
		// write to file
		file_put_contents("OutputXML/categoryTree.xml", $xml.PHP_EOL, FILE_APPEND | LOCK_EX);
	}
	else {
		echo 'Status code ='.$statusCode.' for '.$url.'<br>';
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
    width: 1000px;
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
    <h2>Read Category Tree from WebShop</h2>

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
					<button type="submit" id="submit" name="read" class="btn-submit">Read Category Tree</button>
                    <br />

                </div>

            </form>

        </div>
		

    </div>

</body>

</html>