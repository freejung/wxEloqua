<?php
# Get necessary services
$webinex = $modx->getService('webinex','Webinex',$modx->getOption('webinex.core_path',null,$modx->getOption('core_path').'components/webinex/').'model/webinex/',$scriptProperties);
if (!($webinex instanceof Webinex)) return 'could not instantiate Webinex';

# Get snippet parameters
$presentationId = $modx->getOption('presentationId',$scriptProperties,0);
$list = $modx->getOption('list',$scriptProperties,'reg');

if($presentation = $modx->getObject('wxPresentation', $presentationId)) {
    $regArray = $modx->fromJSON($presentation->get('reg'));
				if($listId = $regArray['lists'][$list]) {
        return $listId;
		 	}
}
return '';