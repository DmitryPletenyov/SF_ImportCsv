<?php
set_time_limit(0); // nastaví curl_exec maximum execution time (aby se stihli nahrat všechny produkty!)

function pr($dump) {
	/* zformatuje var_dump vypis
	*/
    echo "<pre>";
    var_dump($dump);
    echo "</pre>";
}

function createCurlConnection(){
	/* prihlasi se k api rozhrani
	* preda na vystupu token a dalsi informace potrebne k praci s API
	*/
	$apiServer = 'api.webareal.cz';
	$apiKey = 'ae33538bd9a94d659c95471cda11e480f458432a41de6db0e8d70cc3c5309720';
	$username = 'david.elis@seznam.cz';
	$password = 'io2WJDAs';

	$curl = curl_init();

	curl_setopt_array($curl, array(
	    CURLOPT_URL => "https://$apiServer/login",
	    CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_ENCODING => "",
	    CURLOPT_MAXREDIRS => 10,
	    CURLOPT_TIMEOUT => 30,
	    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	    CURLOPT_CUSTOMREQUEST => "POST",
	    CURLOPT_POSTFIELDS => "{\n  \"username\": \"$username\",\n  \"password\": \"$password\"\n}",
	    CURLOPT_HTTPHEADER => array(
	        "X-Wa-api-token: $apiKey",
	    ),
	));

	$response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    }
	$jsonResponse = json_decode($response);
	$token = $jsonResponse->token;
	$all[0] = $token;
	$all[1] = $jsonResponse;
	$all[2] = $apiServer;
	$all[3] = $apiKey;

	return $all;
}

function lastId() {
	/* zjisteni posledniho ID produktu v e-shopu
	* napojuje se na funkci createcurlconnection
	*/
	$all = createCurlConnection();
	$token = $all[0];
	$jsonResponse = $all[1];
	$apiServer = $all[2];
	$apiKey = $all[3];
	$get = curl_init();
    curl_setopt_array($get, array(
        CURLOPT_URL => "https://$apiServer/products?limit=1",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_ENCODING => "",
        CURLOPT_HEADER => FALSE,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_POSTFIELDS => "",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $jsonResponse->token",
            "X-Wa-api-token: $apiKey"
        ),
    ));

    $response = curl_exec($get);
    $err = curl_error($get);

    curl_close($get);
    $id = json_decode($response);
    $id = get_object_vars($id[0]);
    $id = $id['id'];
	return $id;
}

function addProduct($produkt) {
	/* prida produkt , zpravidla se vola opakovane
	* $produkt se ziskava zpravidla z csv souboru
	*/
	$all = createCurlConnection();
	$token = $all[0];
	$jsonResponse = $all[1];
	$apiServer = $all[2];
	$apiKey = $all[3];
	$curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://$apiServer/product",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_POST => TRUE,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => "$produkt",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $jsonResponse->token",
            "X-Wa-api-token: $apiKey"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);

    // vytvori log
    $activity = "ADD PRODUCT";
    $text = "přidán produkt s kodem ".json_decode($produkt['productNumber'])." a jménem ".json_decode($produkt['name']).".";
    createLog($activity, $text);

    curl_close($curl);
    pr($response); // debug
    $response = null;
    $err = null; 
    $curl = null; 
    $produkt = null;
}

function editProduct($id, $produkt) {
	/* $id - id produktu, ktery se upravuje
	* $produkt - upravene parametry v produktu 
	*/
	// DODĚLAT!!!!
}

function getProductInfo($id) {
	$all = createCurlConnection();
	$token = $all[0];
	$jsonResponse = $all[1];
	$apiServer = $all[2];
	$apiKey = $all[3];
	$curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://$apiServer/product/$id",
        CURLOPT_RETURNTRANSFER => TRUE,
        CURLOPT_POST => TRUE,
        CURLOPT_HEADER => FALSE,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => array(
            "Authorization: Bearer $jsonResponse->token",
            "X-Wa-api-token: $apiKey"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    pr(json_decode($response)); // debug

    curl_close($curl);
    $response = null;
    $err = null; 
    $curl = null; 
    $produkt = null;

    $activity = "INFO";
    $text = "zjištění informací o produktu s ID $id.";
    createLog($activity, $text);
}

function normalize($url) {
	/* z textu udela pratelsky prijatelnou url
	* odstrani diaktritiku, mezery apod. 
	*/
	$normalize = array(
        'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r', '(' => '', ')' => '', '+' => '-', ' ' => '-', ',' => '', '.' => '',
        '=' => '-'
	);
	$url = strtr($url, $normalize);
	return $url;
}

function descriptionChangeWord($word) {
	/* zmeni slova v textu
	*/
	$superlativ_y = array("výborný", "skvělý", "znamenitý", "výtečný", "vynikající", "prvotřídní", "profesionální");
	$superlativ_e = array("výborné", "skvělé", "znamenité", "výtečné", "vynikající", "prvotřídní", "profesionální");
	$superlativ_a = array("výborná", "skvělá", "znamenitá", "výtečná", "vynikající", "prvotřídní", "profesionální");
	if (in_array($word, $superlativ_y)) {return $superlativ_y[rand(0, count($superlativ_y)-1)];}
	if (in_array($word, $superlativ_e)) {return $superlativ_e[rand(0, count($superlativ_e)-1)];}
	if (in_array($word, $superlativ_a)) {return $superlativ_a[rand(0, count($superlativ_a)-1)];}

	$strong_e = array("pevné", "stabilní", "robustní");
	$strong_a = array("pevná", "stabilní", "robustní");
	$strong_y = array("pevný", "stabilní", "robustní");
	if (in_array($word, $strong_e)) {return $strong_e[rand(0, count($strong_e)-1)];}
	if (in_array($word, $strong_a)) {return $strong_a[rand(0, count($strong_a)-1)];}
	if (in_array($word, $strong_y)) {return $strong_y[rand(0, count($strong_y)-1)];}

	$moveable = array("pohyblivé", "posuvné", "mobilní");
	$moveable = array("pohyblivá", "posuvná", "mobilní");
	$moveable = array("pohyblivý", "posuvný", "mobilní");
	if (in_array($word, $moveable)) {return $moveable[rand(0, count($moveable)-1)];}
	if (in_array($word, $moveable)) {return $moveable[rand(0, count($moveable)-1)];}
	if (in_array($word, $moveable)) {return $moveable[rand(0, count($moveable)-1)];}
	// vymyslet vice slov!!!!	
}

function descriptionOurWeb() {
	/* vytvori odstavec, ktery bude odkazovat na jine nase weby
	* ITA - <a href=\"https://www.itatools.cz/\" target=\"_blank\">itatools.cz</a>
	* Aparatea - <a href=\"https://www.aparatea.cz/#page\" target=\"_blank\">aparatea.cz</a>
	*/
	$text = array(
		0 => "", //aby to nebylo vsude, vynechane prazdne misto
		1 => "Dále můžete navštívit naše spřátelené webové stránky, a to <a href=\"https://www.itatools.cz/\" target=\"_blank\">itatools.cz</a> a <a href=\"https://www.aparatea.cz/#page\" target=\"_blank\">aparatea.cz</a>.",
		2 => "Také se můžete podívat na naše stránky <a href=\"https://www.itatools.cz/\" target=\"_blank\">itatools.cz</a> a <a href=\"https://www.aparatea.cz/#page\" target=\"_blank\">aparatea.cz</a>",
		3 => "Podívejte se i na náš web <a href=\"https://www.aparatea.cz/#page\" target=\"_blank\">aparatea.cz</a>.",
		4 => ""
	);
	return $text[rand(0, count($text)-1)];
}

function descriptionSharpeningPCD() {
	/* vlozi text ohledne ostreni diamantu 
	*OSTŘENÍ - <a href=\"https://www.stopkovefrezy.cz/blog/ostreni-a-servis-diamantovych-nastroju\" target=\"_blank\"> - nezapomenout na </a>
	*/
	$text = array(
		0 => "", //aby to nebylo vsude, vynechane prazdne misto
		1 => "Pro prodloužení životnosti nástroje Vám nabízíme profesionální ostření <a href=\"https://www.stopkovefrezy.cz/blog/ostreni-a-servis-diamantovych-nastroju\" target=\"_blank\">diamantových nástrojů</a> ve firmě ITA TOOLS.",
		2 => "Váháte mezi tvrdokovým a diamantovým nástrojem? V takovém případě by Vás zajímala naše nabídka odborného ostření diamantových nástrojů. Pro větší podrobnosti klikněte <a href=\"https://www.stopkovefrezy.cz/blog/ostreni-a-servis-diamantovych-nastroju\" target=\"_blank\">zde</a>",
		3 => "Váháte mezi tvrdokovým a diamantovým nástrojem? V takovém případě Vás bude zajímat naše nabídka <a href=\"https://www.stopkovefrezy.cz/blog/ostreni-a-servis-diamantovych-nastroju\" target=\"_blank\">odborného ostření diamantových nástrojů</a>.",
		4 => "Jednou ze zásadních výhod dia nástrojů je možnost opakovaného <a href=\"https://www.stopkovefrezy.cz/blog/ostreni-a-servis-diamantovych-nastroju\" target=\"_blank\">ostření</a>. <a href=\"https://www.stopkovefrezy.cz/blog/ostreni-a-servis-diamantovych-nastroju\" target=\"_blank\">Ostření</a> probíhá ve specializovaných strojích a umoňuje získat téměř jakékoli úhly a profily."
	);
	return $text[rand(0, count($text)-1)];
}

function createLog($activity, $text) {
	$log = fopen("logs/log".date("Y-m-d").".txt", "a+");
	$add_to_log = "[".$activity."] V čase ".date("d. m. Y H:i")." ".$text."\n";
	fwrite($log, $add_to_log);
	fclose($log);
}
?>
