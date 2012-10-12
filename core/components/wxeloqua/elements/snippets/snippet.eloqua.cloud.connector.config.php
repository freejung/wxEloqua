<?php
if(!filter_has_var(INPUT_GET, 'StepID')){
  return 'Error - no step ID provided';
}else{
  if (!filter_input(INPUT_GET, "StepID", FILTER_VALIDATE_INT)){
     return 'Error - step ID is not an integer';
  }else{
    $stepId = intval($_GET['StepID']);
  }
}
if(!filter_has_var(INPUT_GET, 'stepType')) {
    $stepType=$modx->getOption('defaultStep', $scriptProperties, 'regStep');
}else{
    if (!filter_input(INPUT_GET, 'stepType', FILTER_VALIDATE_REGEXP, array('options'=>array('regexp'=>'/^[\w\s%]+$/')))) {
        $stepType=$modx->getOption('defaultStep', $scriptProperties, 'regStep');
    } else {
        $stepType = $_GET['stepType'];
    }
}
# GET variable wbn indicates the ID of the webinar associated with this step.
# 0 = no webinar selection
# 1 = new webinar
# anything else = ID of associated webinar
if(!filter_has_var(INPUT_GET, 'wbn')){
  $webinarId = 0;
}else{
  if (!filter_input(INPUT_GET, "wbn", FILTER_VALIDATE_INT)){
     $webinarId = 0;
  }else{
    $webinarId = intval($_GET['wbn']);
  }
}
$debug = $modx->getOption('debug',$scriptProperties,0);
if ($debug) echo('webinar id = '.$webinarId);

$webinex = $modx->getService('webinex','Webinex',$modx->getOption('webinex.core_path',null,$modx->getOption('core_path').'components/webinex/').'model/webinex/',$scriptProperties);
if (!($webinex instanceof Webinex)) return 'could not instantiate Webinex';

$wxEloqua = $modx->getService('wxeloqua','wxEloqua',$modx->getOption('wxEloqua.core_path',null,$modx->getOption('core_path').'components/wxeloqua/').'model/wxeloqua/',$scriptProperties);
if (!($wxEloqua instanceof wxEloqua)) return 'could not instantiate wxEloqua';


if (!$modx->user->isAuthenticated('mgr')) {
    $modx->sendRedirect(MODX_SITE_URL.ltrim(MODX_MANAGER_URL,'/').'?returnStepID='.$stepId.'&returnStepType='.$stepType);
}

$webinarDefaults = $modx->getOption('webinarDefaults',$scriptProperties,'{"pagetitle" : "New Webinar", "published" : "0"}');
$presentationDefaults = $modx->getOption('presentationDefaults',$scriptProperties,'{"eventdate" : "'.date('Y-m-d H:i:s', time()).'"}');
$formTpl = $modx->getOption('formTpl',$scriptProperties,'eloqua-cloud-connector-form.tpl');
$timeshift = $modx->getOption('timeshift',$scriptProperties,30*86400);
$contactLists = $modx->getOption('contactLists',$scriptProperties,'["reg", "att", "missed"]');

$c = $modx->newQuery('wxPresentation');

$whereArray = array('primary' => 1,
    'reg:!=' => '');
$c->where($whereArray);
$presentations = $modx->getCollection('wxPresentation',$c);

foreach($presentations as $presentation) {
    $regarray = $modx->fromJSON($presentation->get('reg'));
    if($regarray['steps'][$stepType] == $stepId) {
        $webinarId = $presentation->get('webinar');
    }
}

#if no webinar is found, ask the user to select a webinar
if(!$webinarId) {
    if ($debug) echo('no webinar ID found, requesting input');
    #get a list of upcoming webinars
    $c = $modx->newQuery('wxPresentation');
    $whereArray = array(
        'primary' => 1,
        'eventdate:>=' => date('Y-m-d H:i:s',(time()-$timeshift))
    );
    $c->where($whereArray);
    $presentations = $modx->getCollection('wxPresentation',$c);
    $options = '';
    foreach($presentations as $presentation) {
        $presentationArray = $presentation->toFullArray();
        $options .= '<option value="'.$presentationArray['wbn.id'].'" name="wbn">'.$presentationArray['wbn.pagetitle'].'</option>';
    }
    return $modx->getChunk($formTpl, array('options' => $options, 'stepId' => $stepId, 'stepType' => $stepType));
}

if($webinarId == 1) {
    if ($debug) echo('creating new webinar');
    $webinar = $modx->newObject('wxWebinar', $modx->fromJSON($webinarDefaults));
    $thisPresentation = $modx->newObject('wxPresentation', $modx->fromJSON($presentationDefaults));
    $thisPresentation->set('reg','{"steps" : {"'.$stepType.'" : '.$stepId.'}}');
    $thisPresentation->set('primary',1);
    $thesePresentations = array();
    $thesePresentations[1] = $thisPresentation;
    $webinar->addMany($thesePresentations);
    $webinar->save();
    $webinarId = $webinar->get('id');
}


if ($webinar = $modx->getObject('wxWebinar', $webinarId)) {
    #make sure that, whatever webinar is being used, it has the correct stepId stored in reg
    $thisPresentation = $webinar->primaryPresentation();
    $regarray = $modx->fromJSON($thisPresentation->get('reg'));
    $regarray['steps'][$stepType] = $stepId;
    if ($debug) print_r($regarray);
    $lists = $modx->fromJSON($contactLists);
    foreach ($lists as $list) {
        $listName = 'WBN_'.date('mdy', strtotime($thisPresentation->get('eventdate'))).'_'.$list.'_'.substr(str_replace(' ', '_', $webinar->get('pagetitle')),0,50).'_'.$webinarId;
        if($regarray['lists'][$list]) {
            if ($debug) echo('renaming contact list '.$regarray['lists'][$list].' to '.$listName);
            $wxEloqua->renameAsset($regarray['lists'][$list], $listName, 'ContactList', 'ContactGroup');
        }else{
            if ($debug) echo('<br>no list defined, creating list: '.$listName.'<br>');
            $regarray['lists'][$list] = $wxEloqua->createAsset($listName, 'ContactList', 'ContactGroup');
            if ($debug) echo('contact group created: '.$regarray['lists'][$list]);
        }
    }
    if ($debug) print_r($regarray);
    $thisPresentation->set('reg',$modx->toJSON($regarray));
    $webinar->save();
    #redirect to webinar edit page
    if (!$debug) {
        $modx->sendRedirect(MODX_SITE_URL.ltrim(MODX_MANAGER_URL,'/').'index.php?a=30&id='.$webinarId);
    }else{
        echo('<br>Webinar id = '.$webinarId);
    }
}else{
    #if webinar is not found, return an error
    return('Error: could not find specified webinar');
}

