<?php
/**
 * Plugin Name: ImagineID Widget
 * Plugin URI: http://rdcit.org
 * Description: An ImagineID specific plugin to add custom functionalities to CiviCRM
 * Version: 1.0
 * Author: Csaba Halmagyi
 * Author URI: http://rdcit.org
 * License: LGPL
 */

function calcAgeAtRecruitment($dob,$recrDate){

	$date1 = new DateTime($dob);
	$date2 = new DateTime($recrDate);
	$interval = $date1->diff($date2);
	//$age = $interval->y . " years, " . $interval->m." months, ".$interval->d." days";
	//$age = $interval->y;
	$age = $interval->y . " year(s), " . $interval->m." month(s)";
	return $age;
	
}

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

function getGroupId($groupName){
	
	$custom_group = civicrm_api3('CustomGroup', 'get', array(
			'sequential' => 1,
			'name' => $groupName,
	));
	
	if (!empty($custom_group)) {
		$gId = $custom_group['id'];
	}
	return $gId;
}



add_action('civicrm_custom', 'setAgeAtRecruitment', 92,4);
function setAgeAtRecruitment( $op, $groupID, $entityID, &$params){
	
	
	if ( $op != 'create' && $op != 'edit' ) {
	 return;
	 }
	
	define('MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
	include 'fieldnames.inc.php';
	
	$recruitmentGroupId = getGroupId($recruitGroupName);
	
	if ($groupID == $recruitmentGroupId) {
		
		$contactData = civicrm_api3('Contact', 'get', array(
				'sequential' => 1,
				'id' => $entityID
		));
		$dob = $contactData['values'][0]['birth_date'];
		
		$dateRecruitedFieldId = getFieldId($groupName,$dateRecruited);
		$ageWhenRecruitedFieldId = getFieldId($groupName,$ageWhenRecruited);
		
		
		
		//file_put_contents(MY_PLUGIN_DIR."/id.txt", $dateRecruitedFieldId." ".$dob);
		
		$result = civicrm_api3('CustomValue', 'get', array(
				'sequential' => 1,
				'entity_id' => $entityID,
				'id' => $dateRecruitedFieldId
		));
		
		foreach ($result['values'] as $r){
			if($r['id'] == $dateRecruitedFieldId){
				$dateRecruitedActualValue = $r['latest'];
			}
			else if($r['id'] == $ageWhenRecruitedFieldId){
				$ageRecruitedActualValue = $r['latest'];
			}
		}
		
		
		//file_put_contents(MY_PLUGIN_DIR."/res.txt", $ageWhenRecruitedVal);
		
		if($dateRecruitedActualValue !='' && $dob !='' ) {
			$ageWhenRecruitedNewVal = calcAgeAtRecruitment($dob, $dateRecruitedActualValue);
		
			if ($ageWhenRecruitedNewVal != $ageRecruitedActualValue){
					
				$updateAge = civicrm_api3('CustomValue', 'create', array(
						'sequential' => 1,
						'entity_id' => $entityID,
						'custom_'.$ageWhenRecruitedFieldId => $ageWhenRecruitedNewVal
				));
		
				return;
		
			}
		
		}
	 
	}
	

	

}

?>