<?php
session_start();


/************* api keys add info here for hookups *************/

// hubspot     
$hubspotKey = "";


// airtable
$airtableKey = "";

// airtable parameters	
$base = '';
$table = '';	

/************* ********************************* *************/

// parameters for looping through hubspot, offset increases which emails are looked at, limit never changes
if(!isset($_SESSION["loopOffset"])){
	
	// set the variable to the loop at 0, add below
	$_SESSION["loopOffset"] = 0;	
	
	// get the total number of emails to check against
	$urlLookup = "https://api.hubapi.com/marketing-emails/v1/emails?hapikey=" . $hubspotKey . "&limit=1";

	// initiate new hubspot c_url call
	$cURLLook = curl_init();

	curl_setopt($cURLLook, CURLOPT_RETURNTRANSFER, true);	
	curl_setopt($cURLLook, CURLOPT_URL, $urlLookup);

	// execute the call and store in variable
	$resultLookup = curl_exec($cURLLook);

	// close the Hubspot Pull
	curl_close($cURLLook);

	// transform the json into arrays for PHP	
	$totalCheck = json_decode($resultLookup, true);
	$_SESSION["totalEmails"] = $totalCheck['total'];
}

// hubspot can do 50 for limits, 10 seems to prevent it from timing out on these api calls, is updated at end of loop for remainder emails
$requestLimit = 10;	

?>
	

<div>
<label for="api">Progress:</label>
<progress id="api" value="<?php echo $_SESSION['loopOffset'] + $requestLimit;?>" max="<?php echo $_SESSION["totalEmails"];?>"></progress>
</div>

<?php

if(($_SESSION['loopOffset'] + $requestLimit) >= $_SESSION["totalEmails"]){
	echo "<p>Progress Complete</p>";
}



// hubspot call
$urlString = "https://api.hubapi.com/marketing-emails/v1/emails/with-statistics?hapikey=" . $hubspotKey . "&offset=" . $_SESSION["loopOffset"] . "&order=-created&limit=" . $requestLimit;


// initiate new hubspot c_url call
$cURL = curl_init();

curl_setopt($cURL, CURLOPT_RETURNTRANSFER, true);	
curl_setopt($cURL, CURLOPT_URL, $urlString);

// execute the call and store in variable
$result = curl_exec($cURL);

// close the Hubspot Pull
curl_close($cURL);

// transform the json into arrays for PHP	
$arrayPrint = json_decode($result, true);

// end the loop if there is nothing to show (to break out of the while true)
if (count($arrayPrint['objects']) != 0){
	
	// loop through the records to push to Airtable	
	for($i = 0; $i < count($arrayPrint['objects']); $i++){ 	
	
		
		// check to see if the record is in airtable
		
		// get the records from air table				
		$airtable_url = 'https://api.airtable.com/v0/' . $base . '/' . $table;

		// this is where we'll search for the email, needs to be email ID from HS pull
		$emailID = $arrayPrint['objects'][$i]["id"]; 
		$filter = '&filterByFormula=({Email%20ID}=' . $emailID . ')';	
	
		$url = 'https://api.airtable.com/v0/' . $base . '/' . $table . '?maxRecords=10&view=All%20Emails' . $filter;
		$headers = array(
			'Authorization: Bearer ' . $airtableKey
		);
		
		// set new curl  request to look for the email ID
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPGET, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		$entries = curl_exec($ch);
		curl_close($ch);
		$airtable_response = json_decode($entries, TRUE);
		
	
		// basic field settings for POST/PATCH

		// datetime manipulation turn linux epoch to unix by removing the last two numbers
		$currTime = $arrayPrint['objects'][$i]['publishDate'];
		$currTime = substr($currTime, 0, 10);
		
		// if there is a value to the time assign it a datetime format, otherwise it's 0 epoch time
		if($currTime > 1){
			$emailSent = new DateTime("@$currTime");
		}
		else
		{
			$emailSent = new DateTime("@0000000000");
		}			
		
		// setup Airtable arrays to send json POST/PATCH request		
		$data = array(
			'Send Date' => $emailSent->format('n/j/Y'),
			'Email ID' => $arrayPrint['objects'][$i]["id"], 
			'Email Name' => $arrayPrint['objects'][$i]["name"],			
			'Campaign' => $arrayPrint['objects'][$i]["campaignName"],
			'Subject' => $arrayPrint['objects'][$i]["subject"],
			'Email Type' => $arrayPrint['objects'][$i]["state"],
			'campaign_id' => $arrayPrint['objects'][$i]["primaryEmailCampaignId"]
		);
			
		if($arrayPrint['objects'][$i]["state"] != 'DRAFT' || $arrayPrint['objects'][$i]["state"] != 'AUTOMATED_DRAFT'){
			$dataAdd = array(
				'Delivered' => $arrayPrint['objects'][$i]["stats"]["counters"]["delivered"],
				'Opens' => $arrayPrint['objects'][$i]["stats"]["counters"]["open"],
				'Unique Clicks' => $arrayPrint['objects'][$i]["stats"]["counters"]["click"],
				'Unsubscribes' => $arrayPrint['objects'][$i]["stats"]["counters"]["unsubscribed"],
				'Web Link' => $arrayPrint['objects'][$i]["absoluteUrl"]
			);
				
			$data = array_merge($data, $dataAdd);
		}
			
	
		// check to see if there is data, if none was returned in the call, create a row
		if(count($airtable_response["records"]) > 0){						
			
			// Airtable call for post
			$url = 'https://api.airtable.com/v0/' . $base . '/' . $table . '/' . $airtable_response["records"][0]["id"];													
			
			//create a new cURL resource for Airtable
			$ch = curl_init($url);
			
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
																
		} 
		else 
		{
			// new record to POST on Airtable
			
			// Airtable call for post
			$url = 'https://api.airtable.com/v0/' . $base . '/' . $table;						
		
			//create a new cURL resource for Airtable
			$ch = curl_init($url);
						
		} // end add record to airtable
				
		
		// now that curl settings are done above, run the call and loop again
		// the data array object goes into the fields array and is enocded as JSON
		$payload = json_encode(array("fields" => $data));

		// attach encoded JSON string to the POST/PATCH fields
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

		// set the content type to application/json and give the API key
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Authorization: Bearer ' . $airtableKey));
		
		// return response instead of outputting
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		//execute the POST request
		$airtableResult = curl_exec($ch);						
		
		//close cURL resource for the Airtable connections
		curl_close($ch);		

		// sleep so the airtable post/patch doesn't exceed rate limit
		sleep(1);
		
		} // Airtable POST/PATCH loop ends and now script moves to next record
	
		// increase the offset of the request paramater in Hubspot;
		if($_SESSION["loopOffset"] + $requestLimit < $_SESSION["totalEmails"]){
			$_SESSION["loopOffset"] = $_SESSION["loopOffset"] + $requestLimit;
		} 
		else 
		{					
			$_SESSION["loopOffset"] = $_SESSION["totalEmails"] - $requestLimit;
		}
	
	} // end loop
	
?>