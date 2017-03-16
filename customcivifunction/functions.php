<?php
/**
 * Twenty Fifteen functions and definitions
 *
 * Set up the theme and provides some helper functions, which are used in the
 * theme as custom template tags. Others are attached to action and filter
 * hooks in WordPress to change core functionality.
 *
 * When using a child theme you can override certain functions (those wrapped
 * in a function_exists() call) by defining them first in your child theme's
 * functions.php file. The child theme's functions.php file is included before
 * the parent theme's file, so the child theme functions would be used.
 *
 * @link https://codex.wordpress.org/Theme_Development
 * @link https://codex.wordpress.org/Child_Themes
 *
 * Functions that are not pluggable (not wrapped in function_exists()) are
 * instead attached to a filter or action hook.
 *
 * For more information on hooks, actions, and filters,
 * {@link https://codex.wordpress.org/Plugin_API}
 *
 * @package WordPress 
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */


//[openclinicaform]
function oc_form( $atts ){
	require_once "OpenClinicaSoapWebService.php";
	require_once "oc-settings.php";

	$ocUniqueProtocolId = $_GET['study'];
	$studyOID = '';
	$subjectOID = $_GET['subjectOID'];
	$subject = $_GET['subject'];
	$status = $_GET['status'];
	
	if ($status == "done") {
		return  "Thank you for submitting the form!";
	}
	
	if (!empty($ocUniqueProtocolId)){

	$meta = new OpenClinicaSoapWebService($ocWsInstanceURL, $user, $password);
	//return print_r($meta);
	

	$getMetadata = $meta->studyGetMetadata($ocUniqueProtocolId);
	
	$odmMetaRaw = $getMetadata->xpath('//v1:odm');

	$odmMeta = simplexml_load_string($odmMetaRaw[0]);
	$odmMeta->registerXPathNamespace('odm', OpenClinicaSoapWebService::NS_ODM);
	$studyOID = $odmMeta->Study->attributes()->OID;
	
	$events = array();
	$forms = array();
	$groups = array();
	$items = array();
	//events
	foreach ($odmMeta->Study->MetaDataVersion->StudyEventDef as $eventDefs){
		$eventId = (string)$eventDefs->attributes()->OID;
		$eventName = (string)$eventDefs->attributes()->Name;
		$refs = array();
		$eventRepeating = (string)$eventDefs->attributes()->Repeating;
		foreach ($eventDefs->FormRef as $formRefs){
			$formRef = (string)$formRefs->attributes()->FormOID;
			$refs[] = $formRef;
		}
		$events[$eventId]=array("name"=>$eventName,"repeating"=>$eventRepeating, "refs"=>$refs);
	}
	//forms
	foreach ($odmMeta->Study->MetaDataVersion->FormDef as $formDefs){
		$formId = (string)$formDefs->attributes()->OID;
		$formName = (string)$formDefs->attributes()->Name;
		$refs = array();
		foreach ($formDefs->ItemGroupRef as $igRefs){
			$igRef = (string)$igRefs->attributes()->ItemGroupOID;
			$refs[] = $igRef;
		}
		$forms[$formId]= array ("name"=>$formName,"refs"=>$refs);
	}
	//groups
	foreach ($odmMeta->Study->MetaDataVersion->ItemGroupDef as $igDefs){
		$igId = (string)$igDefs->attributes()->OID;
		$igName = (string)$igDefs->attributes()->Name;
		$refs = array();
		foreach ($igDefs->ItemRef as $iRefs){
			$iRef = (string)$iRefs->attributes()->ItemOID;
			$refs[] = $iRef;
		}
		$groups[$igId]= array ("name"=>$igName,"refs"=>$refs);
	}
	//items
	foreach ($odmMeta->Study->MetaDataVersion->ItemDef as $iDefs){
		$iId = (string)$iDefs->attributes()->OID;
		$iName = (string)$iDefs->attributes()->Name;
		$namespaces = $iDefs->getNameSpaces(true);
		$OpenClinica = $iDefs->children($namespaces['OpenClinica']);
		$text = (string)$iDefs->Question->TranslatedText;
		$clref='';
		$clref= (string)$iDefs->CodeListRef->attributes()->CodeListOID;
		//removing all whitespace chars
		
		$text2 = preg_replace('/\s+/', '', $text);
		//echo $OpenClinica->asXML();
		$fOID = array();
		foreach ($OpenClinica as $oc){
			$subelement = $oc->children($namespaces['OpenClinica']);
			foreach ($subelement as $sube){
				$subattr = $sube->attributes();
				$fOID[] = (string)$subattr['FormOID'];
				$type = (string)$sube->ItemResponse->attributes()->ResponseType;

			}
		}
		//if the field in the crf was marked with patiententered class then add it to the items
		if ((strpos($text2, 'class="patiententered"') !== FALSE))
		$items[$iId]= array ("name"=>$iName,"foid"=>$fOID, "type"=>$type, "leftitem"=>$text, "clref"=>$clref);
	}
	
	if (empty($items)) {return 'There are no data points marked in this study.';}
	
	$formelements = '';
	$rowCounter=0;

	foreach ($events as $ekey=>$ev){
		$rowCounter++;
		//display all the forms associated with the event
		//echo '</td><td>';
		$formR = $ev['refs'];
		$usedFormR = array();
		foreach ($formR as $fr){
			$parentFormParts = explode("_",$fr);
			$parentForm = $parentFormParts[0]."_".$parentFormParts[1];
	
			if (!in_array($parentForm,$usedFormR)){
				$usedFormR[]=$parentForm;

				$firstFR = null;
				foreach ($formR as $fr2){
					$fRefPart = explode("_",$fr2);
					$fRefParent = $fRefPart[0]."_".$fRefPart[1];
						if ($firstFR==null) $firstFR = $fr2;
				}
	
	
				$igr = $forms[$firstFR]['refs'][0];
				foreach ($forms[$firstFR]['refs'] as $igr){
	
					$irefs = $groups[$igr]['refs'];
					//display all the items associated with the current form version
					foreach ($irefs as $ikey=>$item){
						if (in_array($firstFR,$items[$item]['foid'])){
							
							if ($items[$item]['type']=='text'){
							$formelements.= '<tr><td>'.$items[$item]['leftitem'].'</td><td><input type="text" id="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'" name="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'"/></td></tr>';
							}
							else if($items[$item]['type']=='textarea'){
								$formelements.= '<tr><td>'.$items[$item]['leftitem'].'</td><td><textarea id="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'" name="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'"></textarea></td></tr>';
								
							}
							
							else if($items[$item]['type']=='single-select'){
								$formelements.='<tr><td>'.$items[$item]['leftitem'].'</td><td><select id="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'" name="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'">';
								
								foreach ($odmMeta->Study->MetaDataVersion->CodeList as $codeLists){
									if ($codeLists->attributes()->OID == $items[$item]['clref']){
										
										$dataType = $codeLists->attributes()->DataType;
										
										foreach($codeLists->CodeListItem as $clitems){
											$value = $clitems->attributes()->CodedValue;
											$otext = $clitems->Decode->TranslatedText;
											
											$formelements.='<option value="'.$value.'">'.$otext.'</option>';
										}
									}
								}
								
								$formelements.="</select></td></tr>";
								
							}
							
							else if($items[$item]['type']=='radio'){
								$formelements.='<tr><td>'.$items[$item]['leftitem'].'</td><td>';
								//<select id="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'" name="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'">';
								
								foreach ($odmMeta->Study->MetaDataVersion->CodeList as $codeLists){
									if ($codeLists->attributes()->OID == $items[$item]['clref']){
								
										$dataType = $codeLists->attributes()->DataType;
								
										foreach($codeLists->CodeListItem as $clitems){
											$value = $clitems->attributes()->CodedValue;
											$otext = $clitems->Decode->TranslatedText;
												
											$formelements.=$otext.' <input type="radio" id="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'" name="'.$ekey.'__'.$firstFR.'__'.$igr.'__'.$item.'" value="'.$value.'"/><br/>';
										}
									}
								}
								
								$formelements.="</td></tr>";
							} 
							
							
						}
	
					}
				}
	
	
			}
		}
	}
	
	
	
	$templatedir = get_template_directory_uri();
	$url=$_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
	$referer = explode("?",$url);
	
	$retForm = '<form name = "patiententered" action="'.$templatedir.'/wp-form-submitter.php" method="POST"><table border="0">';
	$retFormEnd = '<tr><td><input type="submit" name="Submit" value="Send" /></td><td></td></tr></table></form>';
	$retHidden = '<input type="hidden" name="studyname" value="'.$ocUniqueProtocolId.'"/>
				<input type="hidden" name="studyOID" value="'.$studyOID.'"/>
				<input type="hidden" name="subjectOID" value="'.$subjectOID.'"/>
				<input type="hidden" name="subject" value="'.$subject.'"/>
				<input type="hidden" name="referer" value="'.$referer[0].'"/>';
	
	
	$retForm.= $formelements;
	$retForm.= $retHidden;
	$retForm.= $retFormEnd;
	
	
	
	//return '<div id="oc_custom_form">'.var_dump($items).'</div>';
	return '<div id="oc_custom_form">'.$retForm.'</div>';}
	else {
		
		return;
	}
}
add_shortcode( 'openclinicaform', 'oc_form' );
