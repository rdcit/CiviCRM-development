<?php
require_once "OpenClinicaSoapWebService.php";
require_once "OpenClinicaODMFunctions.php";
require_once "ocODMExtended.php";
require_once "oc-settings.php";

//var_dump($_POST);
//echo '<br/><br/>';
?>

<?php 

$eventsScheduled = array();

$subjectOID = $_POST['subjectOID'];
$subject = $_POST['subject'];
$studyOID = $_POST['studyOID'];
$study = $_POST['studyname'];


//connect to OC Web Services
$client = new OpenClinicaSoapWebService($ocWsInstanceURL, $user, $password);
$odmXML = new ocODMclinicalDataE($studyOID, 1, array());

//SCHEDULING EVENTS
foreach($_POST as $fieldid=>$fieldval){

	if (strpos($fieldid, '__') !== FALSE && !empty($fieldval)){
		
		$fields = explode("__", $fieldid);
		$event = $fields[0];
		
		if (!in_array($event, $eventsScheduled)){
			$eventsScheduled[]=$event;
			$schedule = $client->eventSchedule($subject, $event,
					'', date("Y-m-d"), date("h:i"), '',
					'', $study, null);
			//check if scheduling the event was successful
			if ($schedule->xpath('//v1:result')[0]=='Success'){
	//			echo 'Scheduling event ('.$event.') for subject ('.$subject.'):<span class="success"> ' . $schedule->xpath('//v1:result')[0] . '</span><br/>';
			}
			//if the scheduling is failed
			else {
		//		echo 'Scheduling event ('.$event.') for subject ('.$subject.'):<span class="error"> ' . $schedule->xpath('//v1:result')[0] . ' </span><br/>';
		//		echo '<span class="error">'.$schedule->xpath('//v1:error')[0].'</span><br/>';
			}
		
		}
		
	}
}

//Preparing odmxml


foreach($_POST as $fieldid=>$fieldval){
	
	if (strpos($fieldid, '__') !== FALSE && !empty($fieldval)){
		

		$fields = explode("__", $fieldid);
		$event = $fields[0];
		$form = $fields[1];
		$group = $fields[2];
		$item = $fields[3];
		
		
		if (!empty(trim($fieldval))){
			$odmXML->add($studyOID, $subjectOID, $event, 1, $form, $group,1, $item, trim($fieldval));
			
			$import = $client->dataImport(ocODMtoXML(array($odmXML)));
	//		echo 'import: ' . $import->xpath('//v1:result')[0] . "<br/>";

		}
		
	}
	
	
}
//header("HTTP 302 Found");
//header('Refresh: 3; URL= '.$_POST['referer']);

?>	
<html>
<head>
<META http-equiv="refresh" content="0;URL=http://<?php echo $_POST['referer']."?status=done";?>"> 
</head>
<body>
</body>
</html>