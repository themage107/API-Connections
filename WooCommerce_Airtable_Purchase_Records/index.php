<?php 
session_start();

if(isset($_SESSION['visited'])){
	echo "<div>You have an active session record, please open this page in a new browser or private window.</div>";
} else {
	$_SESSION['visited'] = true;
}
?>
<!DOCTYPE html>
<html>
<head>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<style>
div {
	margin: 20px 0;
}

p {
	margin: 0;
}

#airtableOffsetWarning {
	display: none;
}
</style>
</head> 

<!-- Edited 4-07-22 -->

<body>

<div>
<label for="offset">Orders Offset</label>
<input type="text" name="offset" id="offset" value="0"></input>
<p style="color: red; font-weight: bold;" id="warning">Orders will start with the most recent order</p>
</div>
<div>
<label for="offsetAirtable">Start Airtable Row</label>
<input type="text" name="offsetAirtable" id="offsetAirtable" value="0"></input>
<p style="color: red; font-weight: bold;" id="warningAirtable">Record rows will start at 1</p>
</div>
<div>
<label for="stopPoint">Stop Point</label>
<input type="text" name="stopPoint" id="stopPoint" value="50000"></input>
<p style="color: red; font-weight: bold;" id="warningStopPoint">Orders will stop at order 50000</p>
</div>
<div id="airtableOffsetWarning" style="color: red;">Is Airtable row offest less than Orders?</div>
<button id="getStatsButton" type="button" onclick="getStats();">Move Orders</button>
<div id="contentAreaAPI">Progress will update and display here. You may see warnings for undefined indexes which can be ignored.</div>

<script>

/* add offset */
var pageURL = "/woo/api.php";
var o = 0;
var a = 0;
var s = 50000;

// api can run, no letters or bad values
var canRun = true;
var oGood = true;
var aGood = true;
var sGood = true;

// url parameters
var offsetField = jQuery('#offset');
var offset = "";

var offsetFieldAirtable = jQuery('#offsetAirtable')
var offsetAirtable = "";

var stopField = jQuery('#stopPoint')
var stopPoint = "";

// blur the inputs
offsetField.blur(function(){
	offset = offsetField.val();
	checkValOrders(offset, offsetField);
});

offsetFieldAirtable.blur(function(){
	offsetAirtable = offsetFieldAirtable.val();
	checkValOrders(offsetAirtable, offsetFieldAirtable);
});

stopField.blur(function(){
	stopPoint = stopField.val();
	checkValOrders(stopPoint, stopField);
});

// check vals
function checkValOrders(inputVal, field){
	
	// make sure its a num and then change params
	if(!isNaN(inputVal)){
		
		switch(field){
			case offsetField:
				o = inputVal;
				jQuery("#warning").text("Orders will start at order " + o.toString());
				oGood = true;
				if(o > a) {
					jQuery("#airtableOffsetWarning").show();
				} else {
					jQuery("#airtableOffsetWarning").hide();
				}
				break;
			
			case offsetFieldAirtable:
				a = inputVal;
				jQuery("#warningAirtable").text("Airtable records will start at " + a.toString() + " rows");
				aGood = true;
				if(o > a) {
					jQuery("#airtableOffsetWarning").show();
				} else {
					jQuery("#airtableOffsetWarning").hide();
				}
				break;
			
			case stopField:
				s = inputVal;
				jQuery("#warningStopPoint").text("Orders will stop at order " + s.toString());
				sGood = true;
				break;	
		}

	// NaN
	} else {

		switch(field){
			case offsetField:				
				jQuery("#warning").text("Enter a valid number without spaces, script will not run");
				oGood = false;
				break;
			
			case offsetFieldAirtable:
				jQuery("#warningAirtable").text("Enter a valid number without spaces, script will not run");
				aGood = false;
				break;
			
			case stopField:
				jQuery("#warningStopPoint").text("Enter a valid number without spaces, script will not run");
				sGood = false;
				break;	
		}
		
	}
	
	// set or reset can run
	if(oGood && aGood && sGood) {
		canRun = true;
	} else {
		canRun = false;
	}

}

/* load the api page */
function getStats(){
	if(canRun){
		jQuery('#getStatsButton').prop('disabled', true);
		jQuery('#getStatsButton').text('Retrieving Data');
		offsetField.prop('disabled', true);
		offsetFieldAirtable.prop('disabled', true);
		stopField.prop('disabled', true);
		
		// pass the parameters from the input to the URL, api will handle logic in session
		pageURL += "?o=" + o.toString() + "&a=" + a.toString() + "&s=" + s.toString(); 

		jQuery.ajax({
			type: "GET",
			url: pageURL,
			dataType: "html",
			success: function(data){
				jQuery('#contentAreaAPI').html(data);	
				checkStatus();
			}
		});		

		function checkStatus(){
			if(jQuery('#api').prop("value") < jQuery('#api').prop("max")){
				getStats();
			} else {
				alert('Orders  Complete');
			}
		}

	}
}
	
</script>
	
</body>
</html>