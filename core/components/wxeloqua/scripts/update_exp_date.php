<?php
set_time_limit(2000);
define('MODX_API_MODE', true);
require_once 'modx-index-file';

if (!($modx instanceof modX)) exit();

$modx->log(modX::LOG_LEVEL_ERROR, 'EXP - Running expiration date update script.');
include('eloquaRequest.php');  
$count = 10;
$dateField = 100344;
$daysField = 100345;
$eloquaRequest = new EloquaRequest('company', 'username', "password", 'https://secure.eloqua.com/API/REST/1.0');
$i = 1;
$nPages = 1;
$now = time();
$modx->log(modX::LOG_LEVEL_ERROR, 'EXP - Eloqua Request class included, time='.$now);
while($i <= $nPages) {
$modx->log(modX::LOG_LEVEL_ERROR, 'EXP - Pulling page #'.$i.' of '.$nPages);
	$response = $eloquaRequest->get('/data/contacts?search=C_Competitor_Contract_Expiration_Date1>1/1/2000&depth=complete&count='.$count.'&page='.$i);
	
	$contacts = $response->elements;
	foreach($contacts as $contact) {
		$fieldValues = $contact->fieldValues;
		foreach($fieldValues as $fieldValue){
			if ($fieldValue->id == $dateField) $date = $fieldValue->value;
		}
		$days = floor(($date - $now)/86400);
		$contactId = $contact->id;
		$modx->log(modX::LOG_LEVEL_ERROR, 'contact email ='.$contact->emailAddress);
		foreach($fieldValues as $key => $fieldValue){
			if ($fieldValue->id == $daysField) $contact->fieldValues[$key]->value = $days;
		}
		//print_r($contact);
		$putResponse = $eloquaRequest->put('/data/contact/'.$contactId,$contact);
		
	}
	$nPages = ceil($response->total/$response->pageSize);
	$i++;
}

$modx->log(modX::LOG_LEVEL_ERROR, 'EXP - Finished');

?>