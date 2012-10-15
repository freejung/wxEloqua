<?php
# Get necessary services
$webinex = $modx->getService('webinex','Webinex',$modx->getOption('webinex.core_path',null,$modx->getOption('core_path').'components/webinex/').'model/webinex/',$scriptProperties);
if (!($webinex instanceof Webinex)) return 'could not instantiate Webinex';
$wxEloqua = $modx->getService('wxeloqua','wxEloqua',$modx->getOption('wxEloqua.core_path',null,$modx->getOption('core_path').'components/wxeloqua/').'model/wxeloqua/',$scriptProperties);
if (!($wxEloqua instanceof wxEloqua)) return 'could not instantiate wxEloqua';

set_time_limit(0);

# Get snippet parameters
$timeshift = $modx->getOption('timeshift',$scriptProperties,30*86400);
$cloudConnectorActions = $modx->getOption('cloudConnectorActions',$scriptProperties,'{}');
$dateTimes = $modx->getOption('dateTimes',$scriptProperties,'{}');
$calendarId = $modx->getOption('calendarId',$scriptProperties,1925);
$webinarContext = $modx->getOption('webinarContext',$scriptProperties,'dealerwebinars');
$debug = $modx->getOption('debug',$scriptProperties,0);
$instance = $modx->getOption('instance',$scriptProperties,0);
$pageSize = $modx->getOption('pageSize',$scriptProperties,0);
$webinarProvider = $modx->getOption('webinarProvider',$scriptProperties,0);

$modx->setLogLevel(modX::LOG_LEVEL_INFO);
if ($debug) $modx->setLogLevel(modX::LOG_LEVEL_DEBUG);
$modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :Running Eloqua Cloud Connector.');

# if using a webinar provider API, instantiate the appropriate provider service.
# provider services must include functions register($registrationsArray), getAttendance($presentation), attendanceExists($presentation) and attendanceData($presentation)
switch ($webinarProvider) {
    case 'GoToWebinar':
        $provider = $modx->getService('wxgotowebinar','wxGoToWebinar',$modx->getOption('wxgotowebinar.core_path',null,$modx->getOption('core_path').'components/wxgotowebinar/').'model/wxgotowebinar/');
        if (!($provider instanceof wxGoToWebinar)) return 'could not instantiate wxGoToWebinar';
        break;
    default:
       $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :no webinar provider specified');
}

# process script parameters into arrays

# $stepActions - an associative array stepName => actions
# where actions is of the form ('pushfieldssets' => array-of-pushfieldsets, 'pullfieldsets' => array-of-pullfieldsets, 
#            'pulllistconditions' => array of pull list conditions, 'pushlistconditions' => array of push list conditions, 
#            completeconditions => array of conditions that must be met before the step will process)
# pullfieldsets is an array of arrays of the form ('entitytype' => ('id'=>id, 'name'=>name, 'type'=>type), 'pullfields' => array-of-pullfields, 
#            'emailfield' => data card email field, 'codefield' => unique data card identifier)
# pushfieldsets is an array of arrays of the form ('entitytype' => ('id'=>id, 'name'=>name, 'type'=>type), 'pushfields' => array-of-pushfields, 
#            'emailfield' => data card email field, 'codefield' => unique data card identifier)
# pull list conditions determine lists to which to add contacts based on data pulled from Eloqua and saved to a modx field 
#        they are arrays of the form ('modxfield' => fieldname, 'fieldvalue' => value to compare), 
#        'truelist' => list to add if value matches, 'falselist' => list to add if value doesn't match)
# push list conditions determine lists to which to add contacts based on data pushed to Eloqua and saved to an eloqua field 
#        they are arrays of the form ('eloquafield' => fieldname, 'fieldvalue' => value to compare), 
#        'truelist' => list to add if value matches, 'falselist' => list to add if value doesn't match)
# completeconditions are arrays of the form ('field' => presentation array index, 'value' => value to compare, 
#        'success' => 1 if condition succeeds when field=value, 0 if condition fails when field=value)

$stepActions = $modx->fromJSON($cloudConnectorActions);

# $dateTimeFields - an array of Eloqua fieldnames that correspond to a date/time value in MODX
# and need to be set with local date/time values
# must be a subset of the Eloqua fields from $fieldMap
$dateTimeFields = $modx->fromJSON($dateTimes);

# Get all upcoming presentations for which the "reg" field is set
$c = $modx->newQuery('wxPresentation');
$whereArray = array('primary' => 1,
    'reg:!=' => '',
    'eventdate:>=' => date('Y-m-d H:i:s',(time()-$timeshift)));
$c->where($whereArray);
$presentations = $modx->getCollection('wxPresentation',$c);

# Loop through the presentations, executing each configured Cloud Connector step
foreach ($presentations as $presentation) {
    $regArray = $modx->fromJSON($presentation->get('reg'));
    $presentationArray = $presentation->toFullArray();
    $presentationArray['calendarurl'] = $modx->makeUrl($calendarId, $webinarContext, array('ps' => $presentationArray['id']), 'http');
    $presentationArray['landingpageurl'] = $modx->makeUrl($presentationArray['wbn.id'], $webinarContext, '', 'http');
    if($grandparent = $presentation->getOne('Grandparent')) $presentationArray['grandparent'] = $grandparent->get('pagetitle');
    $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :processing presentation id= '.$presentation->get('id').'');
    if(!is_array($regArray['steps'])) {
        $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :this presentation has no Cloud Connector steps configured.');
        continue;
    }
    foreach($regArray['steps'] as $stepType => $stepId) {
        if (is_int($stepId)) {
            $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :processing step id='.$stepId.', step type '.$stepType);
            if($conditions = $stepActions[$stepType]['completeconditions']) {
                $passedAll = true;
                $success = true;
                foreach($conditions as $condition) {
                    if($presentationArray[$condition['field']] == $condition['value']) $success = $condition['success'];
                    if (!$success) $passedAll = false;
                }
                if (!$passedAll) {
                    $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :Conditions not passed for step '.$stepId.', skipping to the next step.');
                    continue;
                }else{
                    $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :Conditions passed, checking for members.');
                }
            }
            $numberOfMembers = $wxEloqua->getMembersAwaitingAction($stepId);
            if($numberOfMembers) {
                $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :'.$numberOfMembers.' members being processed in step '.$stepId);
                # Set up fields to push to Eloqua from the presentation full array 
                # and pull from Eloqua to all registration objects 
                # for each type of step
                $pullFieldSets = $stepActions[$stepType]['pullfieldsets'];
                $pushFieldSets = $stepActions[$stepType]['pushfieldsets'];
                $pullListConditions = $stepActions[$stepType]['pulllistconditions'];
                $pushListConditions = $stepActions[$stepType]['pushlistconditions'];
                $checkAttendance = $stepActions[$stepType]['checkattendance'];
                $addToLists = array();
                if(!empty($pullFieldSets)) {
                    $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :pulling fields');
                    $modxFieldSets = array();
                    $cardIdValue = str_replace('-', '', $presentationArray['gtwid']);
                    $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :card ID value:'.$cardIdValue);
                    foreach($pullFieldSets as $pullFieldSet) {
                        $entityType = new EntityType($pullFieldSet['entitytype']['id'], $pullFieldSet['entitytype']['name'], $pullFieldSet['entitytype']['type']);
                        $pullFields = $pullFieldSet['pullfields'];
                        $eloquaFields = array();
                        foreach ($pullFields as $eloquaField => $modxField) {
                            $eloquaFields[] = $eloquaField;
                        }
                        if($entityType->Name == 'Contact') {
                            $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :getting data from contact record');
                            $eloquaFields[] = 'C_EmailAddress';
                            $returnedContacts = $wxEloqua->getContactData($eloquaFields);
                            $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :returned contacts:');
                            foreach ($returnedContacts as $dynamicEntity) {
                                $contactId = $dynamicEntity->Id;
                                 $fieldValueCollection = $dynamicEntity->FieldValueCollection;
                                $email = $fieldValueCollection->getDynamicEntityField('C_EmailAddress');
                                 $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :email = '.$email);
                                 $modxFieldSets[$contactId]['email'] = $email;
                                 foreach ($pullFields as $eloquaField => $modxField){
                                     $modxFieldSets[$contactId]['fields'][$modxField] = $fieldValueCollection->getDynamicEntityField($eloquaField);
                                 }
                            }
                        }elseif($entityType->Type == 'DataCardSet') {
                            $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :getting data from data card');
                            $dataCards = $wxEloqua->getDataCards($eloquaFields, $entityType,$pullFieldSet['emailfield'], $pullFieldSet['codefield'], $cardIdValue);
                            foreach ($dataCards as $contactId => $dataCard) {
                                $email = $dataCard['email'];
                                 $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :email = '.$email);
                                 $modxFieldSets[$contactId]['email'] = $email;
                                 $fieldValueCollection = $dataCard['fields'];
                                 foreach ($pullFields as $eloquaField => $modxField){
                                     if ($fieldValueCollection instanceof DynamicEntityFields) {
                                         $modxFieldSets[$contactId]['fields'][$modxField] = $fieldValueCollection->getDynamicEntityField($eloquaField);
                                     }else{
                                         $modxFieldSets[$contactId]['fields'][$modxField] = '';
                                     }
                                 }
                            }
                        }
                    }
                    
                    // Save retrieved field values and set up lists as specified
                    foreach($modxFieldSets as $contactId => $fieldSet) {
                        $email = $fieldSet['email'];
                        $modxFields = $fieldSet['fields'];
                        if($prospect = $modx->getObject('wxProspect', array('username' => $email))) {
                            //save Eloqua contact ID to prospect profile
                            $profile = $prospect->getOne('Profile');
                            $profileFields = $profile->get('extended');
                            $profileFields['eloquaId'] = $contactId;
                            $profile->set('extended',$profileFields);
                            $profile->save();
                            //save fields to wxRegistration objects associated with this prospect/webinar.
                            $registrations = $modx->getCollection('wxRegistration', array('prospect' => $prospect->get('id'), 'presentation' => $presentationArray['id']));
                            foreach ($modxFields as $modxField => $value){
                                foreach ($registrations as $registration) {
                                    $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :setting '.$modxField.' to '.$value);
                                    $registration->set($modxField, $value);
                                    $registration->save();
                                }
                            }
                        }
                        foreach($pullListConditions as $condition) {
                            if($modxFields[$condition['modxfield']] == $condition['fieldvalue']) {
                                if ($regArray['lists'][$condition['truelist']]) $addToLists[$contactId][] = $regArray['lists'][$condition['truelist']];
                             }else{
                                 if ($regArray['lists'][$condition['falselist']]) $addToLists[$contactId][] = $regArray['lists'][$condition['falselist']];
                            }
                        }
                    }
                }
                # integrate with webinar provider if any to get attendance data
                $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :checking for check attendance');
                if($checkAttendance) {
                    if($webinarProvider) {
                        if(!$provider->attendanceExists($presentation)) {
                            $modx->log(modX::LOG_LEVEL_INFO, 'Elq Instance '.$instance.' :getting attendance from '.$webinarProvider);
                            if(!$provider->getAttendance($presentation)) $modx->log(modX::LOG_LEVEL_ERROR, 'Elq Instance '.$instance.' :failed to get attendance from '.$webinarProvider);
                        }
                    }
                }
                $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :checking for pushfieldsets: ');
                if(!empty($pushFieldSets)) {
                    $modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :pushing fields:');
                    if($webinarProvider) {
                        if(!$attendanceData = $provider->attendanceData($presentation)) $modx->log(modX::LOG_LEVEL_ERROR, 'Elq Instance '.$instance.' :Error - unable to get attendance data in step '.$stepId);
                    }
                    foreach($pushFieldSets as $pushFieldSet) {
                        $entityType = new EntityType($pushFieldSet['entitytype']['id'], $pushFieldSet['entitytype']['name'], $pushFieldSet['entitytype']['type']);
                        $pushFields = $pushFieldSet['pushfields'];
                        $eloquaFields = array();
                        $codeField = $pushFieldSet['codeField'];
                        foreach ($pushFields as $eloquaField => $sourceField) {
                        	$source = $sourceField['source'];
                        	$sourceKey = $sourceField['key'];
	                        switch($source) {
	                            case 'provider':
                                    $eloquaFields['default'][$eloquaField] = '';
                                    foreach ($attendanceData as $sourceEmail => $attendantData) {
                                        if ($value = $attendantData[$sourceKey]) $eloquaFields[$sourceEmail][$eloquaField] = $value;
                                    }
	                                break;
	                            case 'presentation':
	                            default:
	                                if ($value = $presentationArray[$sourceKey]) $eloquaFields['default'][$eloquaField] = $value;
	                        }
                        }
                        $memberFieldsArray = $wxEloqua->setEntityFields($entityType, $eloquaFields, $dateTimeFields, $codeField);
                        foreach ($memberFieldsArray as $contactId => $memberFields) {
                            foreach($pushListConditions as $condition) {
                                if($memberFields[$condition['eloquafield']] == $condition['fieldvalue']) {
                                    if ($regArray['lists'][$condition['truelist']]) $addToLists[$contactId][] = $regArray['lists'][$condition['truelist']];
                                 }else{
                                     if ($regArray['lists'][$condition['falselist']]) $addToLists[$contactId][] = $regArray['lists'][$condition['falselist']];
                                }
                            }
                        }
                    }
                }
                if(!empty($addToLists)) {
                    $listResult = $wxEloqua->addToLists($addToLists);
                }
                $failedMembers = $wxEloqua->completeStep();
                if(!empty($failedMembers)) {
                    $modx->log(modX::LOG_LEVEL_ERROR, 'Elq Instance '.$instance.' :Error - members failed: '.count($failedMembers));
                    foreach($failedMembers as $failedMember) {
                        $modx->log(modX::LOG_LEVEL_ERROR, 'Elq Instance '.$instance.' :Failed Member: '.$failedMember->EntityId);
                    }
                }
            }
        }
    }
}
$modx->log(modX::LOG_LEVEL_DEBUG, 'Elq Instance '.$instance.' :Completed Eloqua cloud connector run');
return '';