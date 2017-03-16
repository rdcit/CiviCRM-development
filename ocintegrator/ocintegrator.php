<?php
/**
 * Plugin Name: OpenClinica Integrator
 * Plugin URI: http://rdcit.org
 * Description: A plugin to connect CiviCRM with OpenClinica through webservices
 * Version: 1.0
 * Author: Csaba Halmagyi
 * Author URI: http://rdcit.org
 * License: LGPL
 */

//GLOBAL VARIABLES USED BY THE PLUGIN

$OC_INSTANCE = null;
$STUDY_OID = null;
$STUDY_SUBJECT_OID = null;

$STUDIES_ALL = null;
$SITES_ALL = null;

$UPDATE = true;


function getFieldId($groupName, $fieldName){
	
	$gId = 0;
	$custom_group = civicrm_api3('CustomGroup', 'get', array(
  		'sequential' => 1,
		'name' => $groupName,
	));
	
	if (!empty($custom_group)) {
		$gId = $custom_group['id'];
	}
	//////////////
	$fId = 0;
	$custom_field = civicrm_api3('CustomField', 'get', array(
  		'sequential' => 1,
 		 'custom_group_id' => $gId,
 		 'label' => $fieldName,
		));
	
	if (!empty($custom_field)) {
		$fId = $custom_field['id'];
	}
	
	return $fId;
}


add_action('civicrm_customFieldOptions', 'update_study_select', 89,3);


/**
 * Interrogates the OC instances for the studies and updates the select options for Study Name field.
 * 
 * @param unknown $fieldID
 * @param unknown $options
 * @param string $detailedFormat
 */
function update_study_select($fieldID, &$options, $detailedFormat = false ) {
		//define('MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
	//file_put_contents(MY_PLUGIN_DIR."/obj.txt", "##:".$fieldID."d".$detailedFormat,FILE_APPEND);

	include 'settings/ocinstances.php';
	//$fields=serialize($options);
	//	file_put_contents(MY_PLUGIN_DIR."/id.txt", $fields);
	$groupName = "OpenClinica";
	$fieldName = "Study Name";
	
	$ocFieldID = getFieldId($groupName,$fieldName);	
	
	if ( $fieldID == $ocFieldID ) {
		
		$current_user = wp_get_current_user();
		$username = $current_user->user_login;
		
		define('MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
		
		$studies = array();
		$sites = array();
		$sitesAll = array();
		
		foreach($ocInstances as $instance){
			$db = $instance['ocDB'];
			$dbhost = $instance['ocDBHost'];
			$dbuser = $instance['ocDBUser'];
			$dbpass = $instance['ocDBPass'];

			try {
				$dbh = new PDO("pgsql:dbname=$db;host=$dbhost", $dbuser, $dbpass );
			}
			catch( PDOException $Exception ) {
				// PHP Fatal Error. Second Argument Has To Be An Integer, But PDOException::getCode Returns A
				// String.
				//throw new MyDatabaseException( $Exception->getMessage( ) , $Exception->getCode( ) );
				//file_put_contents(MY_PLUGIN_DIR."/error.txt", "Error:".$Exception->getMessage( ).print_r($ocInstances,true).PHP_EOL,FILE_APPEND);
			}
		
			//file_put_contents(MY_PLUGIN_DIR."/error.txt", "Instance:".print_r($instance,true).PHP_EOL,FILE_APPEND);
			//return all studies the user is assigned to
			$query = "select s.study_id, s.name, s.unique_identifier, s.oc_oid, sur.user_name, sur.role_name
			from study_user_role sur, study s
			where s.study_id = sur.study_id
			and sur.user_name = '".trim($username)."' and s.parent_study_id is null
			and s.status_id=1 and sur.status_id=1";
			$sth = $dbh->prepare($query);
			$sth->execute();
			$user_studies = $sth->fetchAll(PDO::FETCH_ASSOC);
		
			foreach($user_studies as $us){
				$id = $instance['name']." - ".$us['name'];
				if(!isset($studies[$id])){
					$studies[$id]=array("instance"=>$instance['name'],"studyname"=>$us['name'],"oc_id"=>$us['study_id'], "oc_oid"=>$us['oc_oid']);
				}
			}
		
			//return all sites the user is assigned to
			$query = "select s.study_id, s.name, s.unique_identifier, s.oc_oid, sur.user_name, sur.role_name
			from study_user_role sur, study s
			where s.study_id = sur.study_id
			and sur.user_name = '".trim($username)."' and s.parent_study_id is not null
			and s.status_id=1 and sur.status_id=1";
			$sth = $dbh->prepare($query);
			$sth->execute();
			$sites[$instance['name']] = $sth->fetchAll(PDO::FETCH_ASSOC);
		
			//return all active sites
			$query = "select s.study_id, s.name, s.unique_identifier, s.oc_oid, s.parent_study_id
			from study s
			where  s.parent_study_id is not null
			and s.status_id=1";
			$sth = $dbh->prepare($query);
			$sth->execute();
			$sites_all = $sth->fetchAll(PDO::FETCH_ASSOC);
		
			foreach($sites_all as $sall){
				$id = $instance['name']."::".$sall['oc_oid'];
				if(!isset($sitesAll[$id])){
					$sitesAll[$id]=array("instance"=>$instance['name'],"sitename"=>$sall['name'],"oc_parent_id"=>$sall['parent_study_id'], "oc_oid"=>$sall['oc_oid']);
				}
			}
		
		}
		
		//file_put_contents(MY_PLUGIN_DIR."/sites.txt", print_r($sitesAll, true).PHP_EOL,FILE_APPEND);
		//file_put_contents(MY_PLUGIN_DIR."/studies.txt", print_r($studies, true).PHP_EOL,FILE_APPEND);
		
		if ( $detailedFormat ) {
			$options['First'] = array("id"=>"First","value"=>"First");
		} else {
			foreach($studies as $key=>$studydata){
				$options[$studydata['instance']." - ".$studydata['studyname']] = $studydata['instance']." - ".$studydata['studyname'];
				
				foreach($sitesAll as $fullsiteid=>$sitedata){
					if($sitedata['instance'] == $studydata['instance'] && $sitedata['oc_parent_id'] == $studydata['oc_id']){
						$options[$sitedata['instance']." - ".$studydata['studyname']." : ".$sitedata['sitename']] = $sitedata['instance']." - ".$studydata['studyname']." : ".$sitedata['sitename'];
					}
				}
				
			}
		}
		
		global $STUDIES_ALL;
		global $SITES_ALL;
		$STUDIES_ALL = $studies;
		$SITES_ALL = $sitesAll;
		
	}
	
}

add_action('civicrm_postProcess', 'collect_civi_keys', 90,2);

function collect_civi_keys($formName, &$form){
	define('MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
	
	

	$submitValues = $form->getSubmitValues();
	
		foreach($submitValues as $skey=>$sval){
		
		if(strpos($skey, 'custom_'.$ocInstanceFieldId."_") !== FALSE){
			$keyparts = explode('_-', $skey);
			global $MULTI_VALUE;
			$MULTI_VALUE = $keyparts[1];
			 
		}
/*		else if(strpos($skey, 'custom_'.$studyOIDFieldId."_") !== FALSE){
			$keyparts = explode('_-', $skey);
			$id = 'custom_'.$studyOIDFieldId.':'.$keyparts[1];
			$KEYS[]=$id;
		}
		else if(strpos($skey, 'custom_'.$subjectOIDFieldId."_") !== FALSE){
			$keyparts = explode('_-', $skey);
			$id = 'custom_'.$subjectOIDFieldId.':'.$keyparts[1];
			$KEYS[]=$id;
		}*/
	} 
	

	
}

add_action('civicrm_custom', 'insert_oc_data_to_civi', 91,4);
 function insert_oc_data_to_civi($op, $groupID, $entityID, &$params){
	define('MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
	include 'settings/ocinstances.php';
	require_once 'classes/OpenClinicaSoapWebService.php';
	
 	$group = civicrm_api3('CustomGroup', 'get', array(
      'sequential' => 1,
      'name' => "OpenClinica",
    ));

 	if (!empty($group)) {
 		$grId = $group['id'];
 	}
	file_put_contents(MY_PLUGIN_DIR."/gr.txt", $groupID."=".$grId.PHP_EOL, FILE_APPEND);

	
	//24994
	if($grId == $groupID){
		

		$subjectCaseBookFieldId = getFieldId("OpenClinica", "OC Subject Casebook");
		$bridgeIDField = getFieldId("OpenClinica", "BRIDGE ID");
		$studyFieldId = getFieldId("OpenClinica", "Study Name");
		
		$contactCustomData = civicrm_api3('CustomValue', 'get', array(
				'entity_id' => $entityID,
		));
		
		
		
		
		$maxMultiKey = 0;
		$maxMultiVal = count($contactCustomData['values'][$bridgeIDField])-3;
		foreach($contactCustomData['values'][$bridgeIDField] as $skey=>$sdata){
			if (is_int($skey) && $skey>$maxMultiKey){
				$maxMultiKey = $skey;
			}
			
		}
		$currCaseBookValue = $contactCustomData['values'][$subjectCaseBookFieldId][$maxMultiKey];
		$currSubjectValue = $contactCustomData['values'][$bridgeIDField][$maxMultiKey];
		$currStudyValue = $contactCustomData['values'][$studyFieldId][$maxMultiKey];
		
 		$contactData = civicrm_api3('Contact', 'get', array(
      					'sequential' => 1,
						'id' => $entityID
		    )); 
		
 		$dob = $contactData['values'][0]['birth_date'];
 		if($contactData['values'][0]['gender']=="Female") {
 			$gender = "f";
 		}
 		else if($contactData['values'][0]['gender']=="Male") {
 			$gender = "m";
 		}
 		else {
 			$gender = null;
 		} 
 		
 		$personID = $contactData['values'][0]['external_identifier'];
 		$subjectID = $currSubjectValue;
 		$secondaryID = $currSubjectValue;
 		
 		date_default_timezone_set('Europe/London');
 		$enrollment = Date("Y-m-d");
 		
 		//create subject here
 		
 		$ocStudy = explode(" - ", $currStudyValue);
 		foreach($ocInstances as $inst){
 			if ($inst['name'] == trim($ocStudy[0])){
 				$url = $inst['ocUrl'].'rest/clinicaldata/html/print/';
 				$user = $inst['ocUserName'];
 				$passwd = sha1($inst['ocPassword']);
 				$ocWsInstanceURL = $inst['ocWSUrl'];
 				
 				//S_SITE1/SS_BR0082/*/*';
 				if (strpos($ocStudy[1], ':') !== FALSE){
 					$studyandsite = explode(":", $ocStudy[1]);
 					$ocUniqueProtocolId = trim($studyandsite[0]);
 					$ocUniqueProtocolIDSiteRef = trim($studyandsite[1]);
 				}
 				else{
 					$ocUniqueProtocolId = trim($ocStudy[1]);
 					$ocUniqueProtocolIDSiteRef = null;
 				}
 			}	
 			
 		}
 		
 		$client = new OpenClinicaSoapWebService($ocWsInstanceURL, $user, $passwd);
 		$isStudySubject = $client->subjectIsStudySubject($ocUniqueProtocolId, $ocUniqueProtocolIDSiteRef, $subjectID);
 		
 		if ($isStudySubject->xpath('//v1:result')[0]=='Success'){
 			$subjOID = (string)$isStudySubject->xpath('//v1:subjectOID')[0];
 			
 		}
 		else{
 			//send a subjectCreateSubject request to the server
 			$createSubject = $client->subjectCreateSubject($ocUniqueProtocolId,
 					$ocUniqueProtocolIDSiteRef, $subjectID, $secondaryID,
 					$enrollment, $personID, $gender, $dob);
 			
 			if ($createSubject->xpath('//v1:result')[0] == 'Success') {
 			
 				$isStudySubject = $client->subjectIsStudySubject($ocUniqueProtocolId, $ocUniqueProtocolIDSiteRef, $subjectID);
 				if ($isStudySubject->xpath('//v1:result')[0]=='Success'){
 					$subjOID = (string)$isStudySubject->xpath('//v1:subjectOID')[0];
 				}
 				else{
 					$err = (string)$createSubject->xpath('//v1:error')[0];
 				}	
 			}
 			else {
 				$err = (string)$createSubject->xpath('//v1:error')[0];
 			}
 		}
 		
 		//$studyOID = "S_SITE1";
 		
 		$getMetadata = $client->studyGetMetadata($ocUniqueProtocolId);
 		$odmMetaRaw = $getMetadata->xpath('//v1:odm');
 		$odm = simplexml_load_string($odmMetaRaw[0]);
 		$odm->registerXPathNamespace('odm', OpenClinicaSoapWebService::NS_ODM);
 		
 		$odm->asXml(MY_PLUGIN_DIR."/meta.xml");
 		if($ocUniqueProtocolIDSiteRef==null){
 			$studyOID = $odm->Study->attributes()->OID; 			
 		}
 		else{
 			foreach ($odm->Study as $studyDef){
 				if($studyDef->GlobalVariables->ProtocolName == $ocUniqueProtocolId." - ".$ocUniqueProtocolIDSiteRef){
 					$studyOID = $studyDef->attributes()->OID;
 				}
 				
 			}
 		}

 		
		$newCaseBookValue = $url.$studyOID."/".$subjOID."/*/*";
		
		if($newCaseBookValue != $currCaseBookValue){
			
			$fieldId = 'custom_'.$subjectCaseBookFieldId.":".$maxMultiKey;
			
 			$result = civicrm_api3('CustomValue', 'create', array(
					'sequential' => 1,
					'entity_id' => $entityID,
					$fieldId => $newCaseBookValue
			));
			
		}
		
		file_put_contents(MY_PLUGIN_DIR."/subjval.txt", print_r($contactCustomData, true).PHP_EOL);
		file_put_contents(MY_PLUGIN_DIR."/contact.txt", print_r($contactData, true).PHP_EOL);
		file_put_contents(MY_PLUGIN_DIR."/maxval.txt", $maxMultiKey." ".$maxMultiVal." ".$currSubjectValue." ".$fieldId.PHP_EOL);
		file_put_contents(MY_PLUGIN_DIR."/error.txt", $err.PHP_EOL);
		file_put_contents(MY_PLUGIN_DIR."/subject.txt", $ocUniqueProtocolId.$ocUniqueProtocolIDSiteRef.$subjectID.$secondaryID.$enrollment.$personID.$gender.$dob.PHP_EOL);
		
	}


} 


?>