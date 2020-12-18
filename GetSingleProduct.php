<?php
header("Content-Type: text/html; charset=windows-1250");
include "vendor/autoload.php";

use Goutte\Client;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;

$client = HttpClient::create();

$response = $client->request('GET',     'https://b2b-itatools.pl/ProduktySzczegoly.aspx?id_artykulu=frYtxkWtdj7OVYk5F1v-4w',
    ['headers' => [
		'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:83.0) Gecko/20100101 Firefox/83.0',
        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
		'Cookie' => 'mistral=md5=5CF8AF96B465FC3C85E4A9B2718A203B; _ga=GA1.2.1362453477.1607516709; czater__first-referer=https://b2b-itatools.pl/Default.B2B.aspx; czater__63d2198880f9ca34993a3cc417bc1912fd5fb897=eae29a7bfd11b99d10de1c243836d880; ASP.NET_SessionId=0210mkeidqvj3xg5ka1ss3jh; _gid=GA1.2.375319028.1608196068;']]);

$statusCode = $response->getStatusCode();

if ($statusCode == 200) {
	$content = $response->getContent();
	$crawler = new Crawler($content);
	
	// ---- Net price (including discounts) ---- 
	$price1html = $crawler->filter('div#szczegolyProduktu p.cena_netto')->first()->html('Missing price1', false);
	
	$price1 = substr($price1html, 0, strpos($price1html, '<'));
	//var_dump($price1);
	
	// ---- Items' count  ---- 
	$cnt = $crawler->filter('div#daneDodatkowe p')->eq(2)->text();

	// ---- Net price (before discount) ---
	$price2 = '';
	$price2table = $crawler->filter('table#tabelaInfoDodatkowe tr');
	foreach ($price2table as $domElement) {
		if (str_starts_with($domElement->nodeValue, 'Net price (before discount):')) {
			// get number from "Net price (before discount):175,00 EUR"
			$start = strpos($domElement->nodeValue, ':');
			$price2 = substr($domElement->nodeValue, $start+1, (strpos($domElement->nodeValue, ' ', $start)-$start-1));
			//var_dump($price2);
		}
		
	}
	
	echo 'Count: '.$cnt.'<br/>';
	echo 'Net price (including discounts): '.$price1.'<br/>';
	echo 'Net price (before discount): '.$price2.'<br/>';
	
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
    <h2>Crawler test. Get single product. </h2>

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