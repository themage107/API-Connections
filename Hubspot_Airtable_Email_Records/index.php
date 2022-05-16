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
</head> 

<body>

<button id="getStatsButton" type="button" onclick="getStats();">Get Stats</button>
<div id="contentAreaAPI">Progress will update and display here. You may see warnings for undefined indexes which can be ignored.</div>


<script>

/* load the api page */
function getStats(){
	
	jQuery('#getStatsButton').prop('disabled', true);
	jQuery('#getStatsButton').text('Retrieving Data');
	
	jQuery.ajax({
		type: "GET",
		url: "/api.php",
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
			alert('Email Report Complete');
		}
	}
}
	
</script>
	
</body>
</html>