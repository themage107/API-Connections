<?php
session_start();

/************** add api keys here **************/

// WooCommerce REST API AUTH
$username = "";
$password = "";

// wooCommerce Website Base e.g: https://www.website.com
$urlBase = "";

// airtable
$airtableKey = "";

// airtable table
$table = "";

// IMPORTANT MESSAGE:
// other airtable data located in the per item loop

// large amounts of lookups, 5 seems to reliable
$requestLimit = 5;

// hubspot
$hubspotKey = "";
$hubspotContactsLookup = "https://api.hubapi.com/crm/v3/objects/contacts/search?hapikey=" . $hubspotKey;
$hubspotOrdersLookup = "https://api.hubapi.com/crm/v3/objects/deals/search?hapikey=" . $hubspotKey;

/************** end api keys **************/



// parameters for looping through Woo pages increases which orders are looked at, limit never changes, page default is 1
if(!isset($_SESSION["offset"])){
		
	// woo order total to check
	$urlLookup = $urlBase . "/wp-json/wc/v3/reports/orders/totals";
	
	// initiate new hubspot c_url call
	$cURLLook = curl_init();

	curl_setopt($cURLLook, CURLOPT_RETURNTRANSFER, true);	
	curl_setopt($cURLLook, CURLOPT_URL, $urlLookup);
	curl_setopt($cURLLook, CURLOPT_USERPWD, $username . ":" . $password);

	// execute the call and store in variable
	$resultLookup = curl_exec($cURLLook);

	// close the Hubspot Pull
	curl_close($cURLLook);

	// transform the json into arrays for PHP	
	$totalCheck = json_decode($resultLookup, true);
		
	// should be 7 in count
	$totalOrders = 0;
	for($i = 0; $i < count($totalCheck); $i++){
		$totalOrders += intval($totalCheck[$i]["total"]);
	}
	
	// session vars for loop logic, lets you offset orders and airtables
	$_SESSION["totalOrders"] = $totalOrders;
	$_SESSION["offset"] = 0;
	$_SESSION["airtableOffset"] = 0;
	$_SESSION["stopPoint"] = 0;
	
	// check URI for offset info
	$urlExplode = $_SERVER['REQUEST_URI'];		
	$urlExplodeFirstStep = explode("?", $urlExplode);
	
	// now explode params
	$urlExplodeParams = explode("&", $urlExplodeFirstStep[1]);
	
	$_SESSION["offset"] = intval(substr($urlExplodeParams[0], 2, strlen($urlExplodeParams[0])));
	$_SESSION["airtableRunNumber"] = intval(substr($urlExplodeParams[1], 2, strlen($urlExplodeParams[1])));
	$_SESSION["stopPoint"] = intval(substr($urlExplodeParams[2], 2, strlen($urlExplodeParams[2])));
	
}

?>

<div>
<label for="api">Progress:</label>

<?php

$totalMax = 0;
if($_SESSION["totalOrders"] > $_SESSION["stopPoint"]){
	$totalMax = $_SESSION["stopPoint"];
} else {
	$totalMax = $_SESSION["totalOrders"];
}

?>

<progress id="api" value="<?php echo $_SESSION['offset']; ?>" max="<?php echo $totalMax;?>"></progress>

<?php 
$numShow = 0;
if(($_SESSION['offset'] + $requestLimit) > $_SESSION["stopPoint"]) {
	$numShow = $_SESSION["stopPoint"];
} else {
	$numShow = $_SESSION['offset'] + $requestLimit;
}
?>

<p>Processed Orders: <?php echo $numShow; ?> / Stop Point: <?php echo $_SESSION["stopPoint"];?></p>
<p>Total Orders in WooCommerce <?php echo $_SESSION["totalOrders"] ?></p>

</div>


<?php

if(($_SESSION['offset'] + $requestLimit) >= $_SESSION["totalOrders"] || ($_SESSION['offset'] + $requestLimit) >= $_SESSION["stopPoint"]){
	echo "<p>Progress Complete</p>";
}

// wooCommerce Call
$urlString = $urlBase . '/wp-json/wc/v3/orders?offset=' . $_SESSION["offset"] . '&per_page=' . $requestLimit;

// initiate new hubspot c_url call
$cURL = curl_init();

// set parameters for WP
curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);	
curl_setopt($cURL, CURLOPT_URL, $urlString);
curl_setopt($cURL, CURLOPT_USERPWD, $username . ":" . $password);

// execute the call and store in variable
$result = curl_exec($cURL);

// close the Hubspot Pull
curl_close($cURL);

// transform to JSON for PHP
$ordersObject = json_decode($result, true);

// orders loop
for($i = 0; $i < count($ordersObject); $i++){
	
	if(($_SESSION['offset'] + $i) >= $_SESSION["stopPoint"] || ($_SESSION['offset'] + $i) >= $_SESSION["totalOrders"]){
		break;
	}
	
	$dealPipeline = '';
	$dealStage = '';
	$dealName = '';
	$startDate= '';
	$productTotal = 0;
	$couponAmount = 0;
	$couponCode = '';
	$exhibitorConsent = '';
	$existingProduct = '';
	$invoiceStatus = '';
	$orderTotal = 0;
	$lineItemSubTotal = 0;
	$transactionPaymentMethod = '';
	$transactionType = '';
	$eCommerceCustomerID = '';
	$firstName = '';
	$lastName = '';
	$email = '';
	$closeDate = '';
	$invoiceID = '';
	$lineItemAmount = 0;
	$contactID = "";
	$dealID = "";
	$system = "";
	
	// coupon data on the order
	if(count($ordersObject[$i]["coupon_lines"]) > 0){
		for($j = 0; $j < count($ordersObject[$i]["coupon_lines"]); $j++){	
						
			if($j != 0){
				$couponCode .= ", " . $ordersObject[$i]["coupon_lines"][$j]["code"];
				$couponAmount += floatval($ordersObject[$i]["coupon_lines"][$j]["discount"]);
			} else {
				$couponCode .= $ordersObject[$i]["coupon_lines"][$j]["code"];
				$couponAmount += floatval($ordersObject[$i]["coupon_lines"][$j]["discount"]);
			}
			
		}
	} else {
		$couponCode = '';
	}
	
	// loop for each line item
	for($j = 0; $j < count($ordersObject[$i]["line_items"]); $j++){	
		
		// loop for meta external id info
		$metaIDFound = false;
		for($k = 0; $k < count($ordersObject[$i]["line_items"][$j]["meta_data"]); $k++) {
			
			// found it in an array
			if(in_array("External ID", $ordersObject[$i]["line_items"][$j]["meta_data"][$k])) {
				$existingProduct = $ordersObject[$i]["line_items"][$j]["meta_data"][$k]["value"];				
				$metaIDFound = true;
				break;
			}
			
		}
		
		// loop for exhibitor consent
		for($k = 0; $k < count($ordersObject[$i]["line_items"][$j]["meta_data"]); $k++) {
			
			// found it in an array
			if(in_array("Exhibitor Consent", $ordersObject[$i]["line_items"][$j]["meta_data"][$k])) {
				$exhibitorConsent = $ordersObject[$i]["line_items"][$j]["meta_data"][$k]["value"];
				if($exhibitorConsent == "Yes"){
					$exhibitorConsent = "true";
				} else {
					$exhibitorConsent = "false";
				}
				break;
			} else {
				$exhibitorConsent = '';
			}
			
		}
		
		
		// didn't find metaID, look up the info in a product call
		if(!$metaIDFound){
			
			// check for variation_id then for product_id in the event of simple vs variable product
			if(array_key_exists("variation_id", $ordersObject[$i]["line_items"][$j]) && $ordersObject[$i]["line_items"][$j]["variation_id"] != 0){
				$productURL = $urlBase . '/wp-json/wc/v3/products/' . $ordersObject[$i]["line_items"][$j]["variation_id"];
			} else {
				$productURL = $urlBase . '/wp-json/wc/v3/products/' . $ordersObject[$i]["line_items"][$j]["product_id"];
			}
			
			// initiate new hubspot c_url call
			$cURLProduct = curl_init();

			// set parameters for WP
			curl_setopt($cURLProduct, CURLOPT_RETURNTRANSFER, true);	
			curl_setopt($cURLProduct, CURLOPT_URL, $productURL);
			curl_setopt($cURLProduct, CURLOPT_USERPWD, $username . ":" . $password);

			// execute the call and store in variable
			$product = curl_exec($cURLProduct);

			// close the Hubspot Pull
			curl_close($cURLProduct);

			// transform to JSON for PHP
			$productObject = json_decode($product, true);
			
			for($l = 0; $l < count($productObject["meta_data"]); $l++){
				if(in_array("_external_id", $productObject["meta_data"][$l])){
					$existingProduct = $productObject["meta_data"][$l]["value"];
					break;
				}
			}
		}
				
		// hubspot check contact id
		$email = $ordersObject[$i]["billing"]["email"];	
		$cURLContactLookup = curl_init();

		$contactSearchBody = '{"filterGroups":[{"filters":[{"propertyName": "email","operator": "EQ","value": "' . $email . '"}]}]}';
		
		// set parameters for Hubspot
		curl_setopt($cURLContactLookup, CURLOPT_RETURNTRANSFER, true);	
		curl_setopt($cURLContactLookup, CURLOPT_URL, $hubspotContactsLookup);
		curl_setopt($cURLContactLookup, CURLOPT_POSTFIELDS, $contactSearchBody);
		curl_setopt($cURLContactLookup, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		
		// execute the call and store in variable
		$contactReturn = curl_exec($cURLContactLookup);

		// close the Hubspot Pull
		curl_close($cURLContactLookup);

		// transform to JSON for PHP
		$contactObject = json_decode($contactReturn, true);
		
		//print_r($contactObject);
		if($contactObject['total'] == 1){			
			$contactID = $contactObject["results"][0]["id"];
		} else {
			$contactID = "";
		}

		
		// hubspot check order id
		$invoiceID = strval($ordersObject[$i]["id"]);
		$dealName = $existingProduct . ' | ' . $invoiceID;
		$cURLDealLookup = curl_init();

		$dealSearchBody = '{"filterGroups":[{"filters":[{"propertyName": "dealname","operator": "EQ","value": "' . $dealName . '"}]}]}';
		
		// set parameters for Hubspot
		curl_setopt($cURLDealLookup, CURLOPT_RETURNTRANSFER, true);	
		curl_setopt($cURLDealLookup, CURLOPT_URL, $hubspotOrdersLookup);
		curl_setopt($cURLDealLookup, CURLOPT_POSTFIELDS, $dealSearchBody);
		curl_setopt($cURLDealLookup, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		
		// execute the call and store in variable
		$dealReturn = curl_exec($cURLDealLookup);

		// close the Hubspot Pull
		curl_close($cURLDealLookup);

		// transform to JSON for PHP
		$dealObject = json_decode($dealReturn, true);
		
		//print_r($contactObject);
		if($dealObject['total'] == 1){			
			$dealID = $dealObject["results"][0]["id"];
		} else {
			$dealID = "";
		}

		
		// transaction type code
		$lineItemAmount = floatval($ordersObject[$i]["line_items"][$j]["total"]);
		$lineItemSubTotal = floatval($ordersObject[$i]["line_items"][$j]["subtotal"]);
		$eCommerceCustomerID = strval($ordersObject[$i]["customer_id"]);
		$firstName = $ordersObject[$i]["billing"]["first_name"];
		$lastName = $ordersObject[$i]["billing"]["last_name"];				
		$closeDate = $ordersObject[$i]["date_created"];		
		$productTotal = count($ordersObject[$i]["line_items"]);
		$invoiceStatus = $ordersObject[$i]["status"];
		$orderTotal = floatval($ordersObject[$i]["total"]);
		$transactionPaymentMethod = $ordersObject[$i]["payment_method_title"];
		$system = "WooCommerce";
		
		// comp
		if($couponCode == "comp100"){
			$transactionType = "comp";
		} else {
			$transactionType = "paid";
		}
		
		// deal stage
		if($invoiceStatus == 'completed'){
			$dealStage = 'Closed/Won';
		} else {
			$dealStage = 'Closed/Lost';
		}
		
		
		// pipelines
		if(strpos($existingProduct, "AFMCP") !== false){
			$dealPipeline = 'AFMCP Pipeline';
		} else if(strpos($existingProduct, "CARDIO") !== false){
			$dealPipeline = 'APM Cardio Pipeline';		
		} else if(strpos($existingProduct, "IMMUNE") !== false){
			$dealPipeline = 'APM Immunne Pipeline';
		} else if(strpos($existingProduct, "BIOE") !== false || strpos($existingProduct, "ENERGY") !== false){
			$dealPipeline = 'APM BioE Pipeline';
		} else if(strpos($existingProduct, "HORMONE") !== false){
			$dealPipeline = 'APM Hormone Pipeline';
		} else if(strpos($existingProduct, "GI") !== false){
			$dealPipeline = 'APM GI Pipeline';
		} else if(strpos($existingProduct, "ENVIRO") !== false || strpos($existingProduct, "DETOX") !== false || strpos($existingProduct, "BIOTRANS") !== false){
			$dealPipeline = 'APM Enviro Pipeline';
		} else if(strpos($existingProduct, "CST") !== false){
			$dealPipeline = 'CST Pipeline';
		} else if(strpos($existingProduct, "TFP") !== false){
			$dealPipeline = 'TFP Pipeline';
		} else if(strpos($existingProduct, "AIC") !== false){
			$dealPipeline = 'AIC Pipeline';
		} else if(strpos($existingProduct, "IFMCP") !== false  || strpos($existingProduct, "CERTIFICATION FEE") !== false){
			$dealPipeline = 'IFMCP Pipeline';
		} else if(strpos($existingProduct, "Membership:Org") !== false  || strpos($existingProduct, "MEMBERSHIP:Org") !== false){
			$dealPipeline = 'Org Memberships Pipeline';
		} else if(strpos($existingProduct, "Exhibitor") !== false){
			$dealPipeline = 'Exhibitor/Sponsorship Pipeline';
		} else {
			$dealPipeline = "Sales Pipeline";
		}
		

		// startDate		
		$productURL = $urlBase . '/wp-json/wc/v3/products/' . $ordersObject[$i]["line_items"][$j]["product_id"];
		// initiate new hubspot c_url call
		$cURLProductStartDate = curl_init();

		// set parameters for WP
		curl_setopt($cURLProductStartDate, CURLOPT_RETURNTRANSFER, true);	
		curl_setopt($cURLProductStartDate, CURLOPT_URL, $productURL);
		curl_setopt($cURLProductStartDate, CURLOPT_USERPWD, $username . ":" . $password);

		// execute the call and store in variable
		$productStartDate = curl_exec($cURLProductStartDate);

		// close the Hubspot Pull
		curl_close($cURLProductStartDate);

		// transform to JSON for PHP
		$productStartDateObject = json_decode($productStartDate, true);
				
		
		for($l = 0; $l < count($productStartDateObject["meta_data"]); $l++){
			if(in_array("start_date", $productStartDateObject["meta_data"][$l])){
				$startDate = $productStartDateObject["meta_data"][$l]["value"];
				$startDate = substr($startDate, 4, 2) . "/" . substr($startDate, 6, 2) . "/" . substr($startDate, 0, 4); 
				
				if(strlen($startDate) < 4){
					$startDate = "";
				}
				break;
				
			}
		}

		/********* Airtable Loop Checks for Base - 50k limit per base *********/
		
		// airtable base (change every 50k) - change to session var 
		if($_SESSION["airtableRunNumber"] < 50000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 50000 && $_SESSION["airtableRunNumber"] < 100000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 100000 && $_SESSION["airtableRunNumber"] < 150000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 150000 && $_SESSION["airtableRunNumber"] < 200000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 200000 && $_SESSION["airtableRunNumber"] < 250000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 250000 && $_SESSION["airtableRunNumber"] < 300000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 300000 && $_SESSION["airtableRunNumber"] < 350000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 350000 && $_SESSION["airtableRunNumber"] < 400000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 400000 && $_SESSION["airtableRunNumber"] < 450000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 450000 && $_SESSION["airtableRunNumber"] < 500000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 500000 && $_SESSION["airtableRunNumber"] < 550000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 550000 && $_SESSION["airtableRunNumber"] < 600000) {
			$base = '';
		} else if ($_SESSION["airtableRunNumber"] >= 600000 && $_SESSION["airtableRunNumber"] < 650000) {
			$base = '';
		}
		
		if($invoiceStatus == "processing" || $invoiceStatus == "pending"){
			$base = '';
		}

		
		$airtableData = array(
			'deal_name' => $dealName,
			'deal_pipeline' => $dealPipeline, 
			'deal_stage' => $dealStage,
			'start_date' => $startDate,
			'count_products_on_order' => $productTotal,			
			'coupon_amount' => $couponAmount,
			'coupon_code' => $couponCode,
			'exhibitor_consent' => $exhibitorConsent,
			'exisiting_product' => $existingProduct,
			'invoice_status' => $invoiceStatus,
			'total_order_amount' => $orderTotal, 
			'sub_total-amount' => $lineItemSubTotal,
			'transaction_payment_method' => $transactionPaymentMethod,
			'transaction_type' => $transactionType,
			'ecommerce_customer_id' => $eCommerceCustomerID,
			'first_name' => $firstName,
			'amount' => $lineItemAmount,
			'last_name' => $lastName,
			'email' => $email,
			'close_date' => $closeDate,
			'invoice_id' => $invoiceID,
			'associated_contact' => $contactID,
			'deal_id' => $dealID,
			'system' => $system
		);
		
		$payload = json_encode(array("fields" => $airtableData));
		
		// airtable pieces
		$airtable_url = 'https://api.airtable.com/v0/' . $base . '/' . $table;
				
		// set new curl  request to look for the email ID
		$airtableCURL = curl_init();
		curl_setopt($airtableCURL, CURLOPT_URL, $airtable_url);
		
		// attach encoded JSON string to the POST/PATCH fields
		curl_setopt($airtableCURL, CURLOPT_POSTFIELDS, $payload);

		// set the content type to application/json and give the API key
		curl_setopt($airtableCURL, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $airtableKey));
		
		// return response instead of outputting
		curl_setopt($airtableCURL, CURLOPT_RETURNTRANSFER, true);

		//execute the POST request
		curl_exec($airtableCURL);
				
		//close cURL resource for the Airtable connections
		curl_close($airtableCURL);		

		// sleep so the airtable post/patch doesn't exceed rate limit
		// sleep(1);
				

		// increment the run num so we don't hit more than 50k per base
		switch($invoiceStatus){
			case "processing":
			case "pending":			
			break;
			
			default:
				$_SESSION["airtableRunNumber"]++;
			break;			
		}
				
				
		// reset values in WP vars
		$dealName = '';
		$dealStage = '';
		$dealPipeline = '';
		$startDate = '';
		$productTotal = 0;
		$couponAmount = 0;
		$couponCode = '';
		$exhibitorConsent = '';
		$existingProduct = '';
		$invoiceStatus = '';
		$orderTotal = 0;
		$lineItemSubTotal = 0;
		$transactionPaymentMethod = '';
		$transactionType = '';
		$eCommerceCustomerID = '';
		$firstName = '';
		$lineItemAmount = 0;
		$lastName = '';
		$email = '';
		$closeDate = '';
		$invoiceID = '';
		$system = '';
		$contactID = '';
		$dealID = '';		

	}
	
}

// loop is done - increase the offset of the request paramater in WP;
if($_SESSION["offset"] + $requestLimit < $_SESSION["totalOrders"]){
	$_SESSION["offset"] += $requestLimit;
} 
else 
{					
	$_SESSION["offset"] = $_SESSION["totalOrders"] - $requestLimit;
}

?>