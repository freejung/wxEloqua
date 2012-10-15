<?php
/**
 * wxEloqua
 *
 * Copyright 2012 by Eli Snyder <freejung@gmail.com>
 * 
 * MODX class for interacting with the Eloqua SOAP API
 * Stores authentication data
 * Has methods to get and set contact data from Eloqua Cloud Connector program steps
 */
/**
 * @package wxEloqua
 * @subpackage model
 */
 
#  include Eloqua soap client classes for interacting with the Eloqua API
include ('/mnt/stor9-wc1-dfw1/627233/www.kpaonline.com/web/core/components/wxeloqua/scripts/EloquaSOAPClient.php'); 
 
class wxEloqua {
	
    public $modx;
    public $config = array();
    public $members = array();
    public $failedMembers = array();
    public $stepId = 0;
    
    # WSDL documents - External Action Service and Eloqua Service
	private $wsdl = "https://secure.eloqua.com/api/1.2/ExternalActionService.svc?wsdl";
	private $wsdl2 = '/mnt/stor9-wc1-dfw1/627233/www.kpaonline.com/web/core/components/wxeloqua/scripts/wsdl/EloquaServiceV1.2.wsdl';
	private $endPointURL = 'https://secure.eloqua.com/API/1.2/Service.svc?wsdl';
	# Client Credentials
	private $username = "";
	private $password = "";
	private $client;
	private $eloquaClient;

    function __construct(modX &$modx,array $config = array()) {
        $this->modx =& $modx;
        $basePath = $this->modx->getOption('wxEloqua.core_path',$config,$this->modx->getOption('core_path').'components/wxeloqua/');
        $assetsUrl = $this->modx->getOption('wxEloqua.assets_url',$config,$this->modx->getOption('assets_url').'components/wxeloqua/');
        $this->config = array_merge(array(
            'basePath' => $basePath,
            'corePath' => $basePath,
            'modelPath' => $basePath.'model/',
            'processorsPath' => $basePath.'processors/',
            'templatesPath' => $basePath.'templates/',
            'chunksPath' => $basePath.'elements/chunks/',
            'jsUrl' => $assetsUrl.'js/',
            'cssUrl' => $assetsUrl.'css/',
            'assetsUrl' => $assetsUrl,
            'connectorUrl' => $assetsUrl.'connector.php',
            'pageSize' => 20,
            'instance' => 0
        ),$config);
        # Instantiate a new Eloqua Soap Clients to retrieve members and update contact fields
        $this->client = new ElqActionSoapClient($this->wsdl, $this->username, $this->password);
		$this->eloquaClient = new EloquaSoapClient($this->wsdl2, $this->username, $this->password,$this->endPointURL);
        $this->modx->lexicon->load('wxEloqua:default');
    }
    /*
    * getMembersAwaitingAction gets a list of contacts in a program step
    * Sets their status to "In Progress"
    * Returns an array of program step members whose status has been successfully changed
    *
    * @param int $stepId the ID number of the Eloqua Cloud Connector program step to be polled
    */
    public function getMembersAwaitingAction($stepId) {   
        $this->stepId = $stepId;
        $this->failedMembers = array();
        # Invoke SOAP Request : ListMembersInStepByStatus()
        try
        {
            
        /********Call function ListMembersInStepByStatus to get members waiting in campaign step********/
        
        $response = $this->client->ListMembersInStepByStatus(array('stepId'=>$stepId, 'status'=>'AwaitingAction', 'pageNumber'=>0, 'pageSize'=> $this->config['pageSize']));
        
        /*********************************************************************************/
        
        }
        catch (Exception $e)
        {
        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
        }
        # Set array $members containing all contacts retrieved from this program step
        $this->members = $response->ListMembersInStepByStatusResult->Member;
        if(!empty($this->members)){
            # Set member status to In Progress
            try
            {
            $response = $this->client->SetMemberStatus(array('members' => $this->members, 'status' => 'InProgress'));
            }
            catch (Exception $e)
            {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
            }
            $this->members = $response->SetMemberStatusResult->Member;
        }
        return count($this->members);
    }
    
    /*
     * setMemberStatus sets the status of program step members to $status
     * returns an array of members whose status has been changed
     *
     * @param ArrayOfMember $members Eloqua contacts for whom to set status
    */
    public function setMemberStatus ($members = array(), $status) {
        # Instantiate a new instance of the Eloqua Action Soap client to poll for program step members
        $this->client = new ElqActionSoapClient($this->wsdl, $this->username, $this->password);
        # Set member status to Complete
        try
        {
            
        /********Call function SetMemberStatus to set new step member status********/
        
        $response = $this->client->SetMemberStatus(array('members' => $members, 'status' => $status));
        
        /*********************************************************************************/
        
        }
        catch (Exception $e)
        {
        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
        }
        $members = $response->SetMemberStatusResult->Member;
        return $members;
    }
    
    public function completeStep() {
        $this->setMemberStatus($this->members, 'Complete');
        $this->setMemberStatus($this->failedMembers, 'AwaitingAction');
        return $this->failedMembers;
    }
    
    /*
     * setContactFields sets the specified fields of contacts in Eloqua
     *
     * @param EntityType $EntityType
     * @param array $fields (email=> array(EloquaFieldName => value))
     * @param array $dateTimeFields
     * to set default values, use email="default"
    */
    
    public function setEntityFields($entityType, $fields = array(), $dateTimeFields = array(), $codeField = '') {
        $memberFieldsArray = array();
        if(!empty($this->members)){
            # If one of the fields is a date/time field, get the state for all members
            # so that the time can be set to the member's local time.
            
            # Retrieve email and state for each contact
            $returnedContacts = $this->getContactData(array('C_State_Prov', 'C_EmailAddress'));
            $memberStates = array();
            if (!empty($returnedContacts)) {                           
                 foreach ($returnedContacts as $key => $dynamicEntity) {
                    $memberStates[$dynamicEntity->Id] = $dynamicEntity->FieldValueCollection->getDynamicEntityField('C_State_Prov');
                    $email = $dynamicEntity->FieldValueCollection->getDynamicEntityField('C_EmailAddress');
                    if ($email) $email = strtolower($email);
                    $this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'member email: '.$email);
                    if(!empty($fields[$email])) {
                        $memberFieldsArray[$dynamicEntity->Id] = $fields[$email];
                    }else{
                        $memberFieldsArray[$dynamicEntity->Id] = $fields['default'];
                    }     
                 }        
            }else{
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'No contact states retrieved for step id='.$this->stepId);    
            }
            
            $this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'processing members:');
            $failedContactIds = array();
            foreach ($this->members as $member) {
                $id = $member->EntityId;
                $entityId = $id;
                $memberFields = $memberFieldsArray[$id];
                //$this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'member fields: '.print_r($memberFields, true));
                $defaultFields = $fields['default'];
                $dynamicEntityFields = new DynamicEntityFields();
                foreach($defaultFields as $key => $value) {
                    $dynamicEntityFields->setDynamicEntityField($key, $value);
                }
                foreach($memberFields as $key => $value) {
                    $dynamicEntityFields->setDynamicEntityField($key, $value);
                }
                $this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'member id: '.$id);
                //customize date/time to member's local time if available
                if(!empty($dateTimeFields)) {
                    foreach($dateTimeFields as $dateTimeField) {
                        $dynamicEntityFields->setDynamicEntityField($dateTimeField, $this->elqLocalTime(strtotime($memberFields[$dateTimeField]), $memberStates[$id]));
                    }
                }
                if($entityType->Type == 'DataCardSet') {
                	$code = $dynamicEntityFields->getDynamicEntityField($codeField);
                	if(empty($code)) {
                		$failedContactIds[] = $id;
                		continue;
                	}
                    $dynamicEntityFields->setDynamicEntityField('MappedEntityID', $id);
                    $dynamicEntityFields->setDynamicEntityField('MappedEntityTypeID', 0);
                    $entityId = NULL;
                }
                $entity = new DynamicEntity($entityType,$dynamicEntityFields,$entityId);
                if($entityType->Name == 'Contact') {
	            	$this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'calling update for contacts:');
	                $param = new Update(array($entity));
	                # Invoke SOAP Request : Update ()
	                try
	                {
	                    
	                #********Call function Update to set contact fields********
	                
	                $response = $this->eloquaClient->Update($param);
	                
	                #*********************************************************************************
	                
	                }
	                catch (Exception $e)
	                {
	                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
                    $failedContactIds[] = $id;
	                }
	            }elseif($entityType->Type == 'DataCardSet') {
	            	$this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'calling update for data cards: ');
	                $param = new Create(array($entity));
	                # Invoke SOAP Request : Create ()
	                try
	                {
	                    
	                #********Call function Create to create a new data cards********
	                
	                $response = $this->eloquaClient->Create($param);
	                
	                #*********************************************************************************
	                
	                }
	                catch (Exception $e)
	                {
	                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
                    $failedContactIds[] = $id;
	                }
	            }
            }
            $this->memberFailure($failedContactIds);
        }
        return $memberFieldsArray;
    }    

    /*
     * getContactData gets the values of specified contact fields for step members
     *
     * @param array $fields array of Eloqua field names to fetch values of
    */
    public function getContactData($fields = array()) {
        $this->modx->log(modX::LOG_LEVEL_DEBUG, 'Elq instance '.$this->config['instance'].' :'.'getting contact data');
        if(!empty($this->members)){
            # Retrieve the contact fields for each member and store them in an array indexed by email address
            #Create Request Object for Retreive Entity
            $entityType = new EntityType(0, 'Contact', 'Base');
            $allMemberIds = array();
            foreach($this->members as $member){
                $allMemberIds[] = $member->EntityId;
            }
            $failedContactIds = array();
            //split memberIds into groups of 50
            $memberIdsArray = array_chunk($allMemberIds, 50);
            $outputArray = array();
            foreach ($memberIdsArray as $memberIds) {
                $param = new Retrieve($entityType,$memberIds,$fields);
                # Invoke SOAP Request : Retrieve ()
                try
                {
                    
                /********Call function Retrieve to get contact data*******/
                
                $response = $this->eloquaClient->Retrieve($param);
                
                /*********************************************************************************/
                
                }
                catch (Exception $e)
                {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Error getting contact data: '.$e->getMessage());
                $failedContactIds = array_merge($failedContactIds, $memberIds);
                }
                $retreiveResult = $response->RetrieveResult;
                if (is_array($retreiveResult->DynamicEntity)) {              
                     // 2+ results
                     $outputArray = array_merge($outputArray, $retreiveResult->DynamicEntity);
                }elseif ($retreiveResult->DynamicEntity instanceof DynamicEntity ) {   
                    // 1 result
                    $outputArray = array_merge($outputArray, array($retreiveResult->DynamicEntity));            
                }else{
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'No contact data retrieved');
                    $failedContactIds = array_merge($failedContactIds, $memberIds);
                }
            }
            $this->memberFailure($failedContactIds);
            return $outputArray;
        }
    }
    
    /*
     * getDataCards gets the values of specified data card fields for data cards matching step member emails
     *
    */
    public function getDataCards($fields = array(), $cardEntityType, $cardEmailField, $cardCodeField, $cardIdValue) {
        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'getting data cards');
        $dataCardArray = array();
        if(!empty($this->members)){
            #get contact email addresses.
            $contactArray = $this->getContactData(array('C_EmailAddress'));
            $fields[] = $cardEmailField;
            $failedContactIds = array();
            #retrieve all data cards associated with these contacts
            foreach ($contactArray as $dynamicEntity) {
                $contactId = $dynamicEntity->Id;
                 $fieldValueCollection = $dynamicEntity->FieldValueCollection;
                $email = $fieldValueCollection->EntityFields->Value;
                $dataCardArray[$contactId]['email'] = $email;
                $param = new Query($cardEntityType, $cardCodeField.'='.$cardIdValue.'*('.$email.')',$fields,1,10);
                
                try
                {
                $response = $this->eloquaClient->Query($param);
                }
                catch (Exception $e)
                {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Error getting data cards: '.$e->getMessage().' trying again');
                    // query has a time limit of 250ms, so sleep for 5 seconds and try again
                    sleep(5);
                    try
                    {
                        
                    /********Call function Query to get data cards matching query parameters********/
                    
                    $response = $this->eloquaClient->Query($param);
                    
                    /*********************************************************************************/
                    
                    }
                    catch (Exception $e)
                    {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Error again getting data cards: '.$e->getMessage().', giving up');
                    $failedContactIds[] = $contactId;
                    }    
                }
                //pause a second after query to go easy on the server
                sleep(1);
                $queryResult = $response->QueryResult;
                if (is_array($queryResult->Entities->DynamicEntity)) {              
                     // 2+ results
                        $found = 0;
                        foreach ($queryResult->Entities->DynamicEntity as $dynamicEntity) {
                            $fieldValueCollection = $dynamicEntity->FieldValueCollection;
                            if($fieldValueCollection->getDynamicEntityField($cardEmailField) == $email) {
                                $dataCardArray[$contactId]['fields'] = $fieldValueCollection;
                                $found = 1;
                            }
                        }
                        if (!$found) {
                            $dataCardArray[$contactId]['fields'] = '';
                            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Of multiple returned cards, none match '.$email);
                            $failedContactIds[] = $contactId;
                        }
                    
                }elseif ($queryResult->Entities instanceof stdClass ) {
                    if(isset($queryResult->Entities->DynamicEntity)) {
                        // 1 result
                        $dynamicEntity = $queryResult->Entities->DynamicEntity;
                        $fieldValueCollection = $dynamicEntity->FieldValueCollection;
                        if($fieldValueCollection->getDynamicEntityField($cardEmailField) == $email) {
                            $dataCardArray[$contactId]['fields'] = $fieldValueCollection;
                        }else {
                            $dataCardArray[$contactId]['fields'] = '';
                            $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Email does not match returned data card: '.$email);
                            $failedContactIds[] = $contactId;
                        }
                    }else{
                        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'No matching data cards found for this contact: '.$email);
                        $failedContactIds[] = $contactId;   
                    }
                }
            }
            $this->memberFailure($failedContactIds);
        }
        return $dataCardArray;
    }
    
    public function memberFailure($failedContactIds = array()) {
        $successfulMembers = array();
        foreach ($this->members as $member) {
            $memberId = $member->EntityId;
            if(in_array($memberId, $failedContactIds)) {
                $this->failedMembers[] = $member;
            }else{
                $successfulMembers[] = $member;
            }
        }
        $this->members = $successfulMembers;
    }
    
    public function createAsset($assetName, $assetTypeName, $assetType) {
        
        $assetType = new AssetType(0, $assetTypeName, $assetType);
        $dynamicAssetFields = new DynamicAssetFields();
        $dynamicAssetFields->setDynamicAssetField('name',$assetName);
        
        $asset = new DynamicAsset($assetType,$dynamicAssetFields,'');
        $param = new CreateAsset(array($asset));
        # Invoke SOAP Request : CreateAsset ()
        try
        {
        $response = $this->eloquaClient->CreateAsset($param);
        }
        catch (Exception $e)
        {
        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
        }
        return $response->CreateAssetResult->CreateAssetResult->ID;
    }

    public function renameAsset($id, $assetName, $assetTypeName, $assetType) {
        #Create Request Object for Creating Entity
        $assetType = new AssetType(0, $assetTypeName, $assetType);
        $dynamicAssetFields = new DynamicAssetFields();
        $dynamicAssetFields->setDynamicAssetField('name',$assetName);
        $asset = new DynamicAsset($assetType,$dynamicAssetFields,$id);
        $param = new UpdateAsset(array($asset));
        # Invoke SOAP Request : UpdateAsset ()
        try
        {
        $response = $this->eloquaClient->UpdateAsset($param);
        }
        catch (Exception $e)
        {
        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
        }
        return $response;
    }
    
    public function addToLists($addToLists) {
        $addResult = array();
        if(!empty($this->members)){
            # Retrieve the contact fields for each member and store them in an array indexed by email address
            #Create Request Object for Retreive Entity
            $entityType = new EntityType(0, 'Contact', 'Base');
            $assetType = new AssetType(0, 'ContactList', 'ContactGroup');
            $failedContactIds = array();
            foreach($this->members as $member) {
                $id = $member->EntityId;
                $param = new Retrieve($entityType,array($id),array());
                try
                { 
                $response = $this->eloquaClient->Retrieve($param);
                }
                catch (Exception $e)
                {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
                }
                $retreiveResult = $response->RetrieveResult;
                reset($retreiveResult);
                $dynamicEntity = current($retreiveResult);
                foreach($addToLists[$id] as $listId) {
                    # Invoke SOAP Request : Retreive Asset
                    $param = new RetrieveAsset($assetType,array($listId),array());
                    try
                    {
                        
                    /********Call function RetrieveAsset to get the contact list********/ 
                        
                    $response = $this->eloquaClient->RetrieveAsset($param);
                    
                    /*********************************************************************************/
                    
                    }
                    catch (Exception $e)
                    {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
                    $failedContactIds[] = $id;
                    }
                    $retreiveResult = $response->RetrieveAssetResult;
                    reset($retreiveResult);
                    $dynamicAsset = current($retreiveResult);
                    # Invoke SOAP Request : AddGroupMembership
                    $param = new AddGroupMember($dynamicEntity,$dynamicAsset);
                    try
                    {
                        
                    /********Call function RetrieveAsset to get the contact list********/
                        
                    $response = $this->eloquaClient->AddGroupMember($param);
                    
                    /*********************************************************************************/
                    
                    }
                    catch (Exception $e)
                    {
                    $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.$e->getMessage());
                    $failedContactIds[] = $id;
                    }
                    if(!$addResult[$id][$listId] = $response->AddGroupMemberResult->Success) {
                        $failedContactIds[] = $id;
                        $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :List add failed for contact: '.$id);
                    }
                }
            }
            $this->memberFailure($failedContactIds);
        }
        return $addResult;
    }
    
    public function getContactsByFieldValues ($fieldValues = array(), $fieldName = 'C_EmailAddress') {
        $elqIds = array();
        $entityType = new EntityType(0, 'Contact', 'Base');
        $fields = array('ContactIdExt', 'C_EmailAddress', $fieldName);
        foreach ($fieldValues as $fieldValue) {
            $this->modx->log(modX::LOG_LEVEL_ERROR,'Field Value in wxEloqua: '.$fieldValue);
            $param = new Query($entityType, 'C_SFDCContactId='.$fieldValue,$fields,1,20);
            try
            {
            $response = $this->eloquaClient->Query($param);
            }
            catch (Exception $e)
            {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Error getting contacts by Field Value: '.$e->getMessage().' trying again');
                // query has a time limit of 250ms, so sleep for 5 seconds and try again
                sleep(5);
                try
                {
                $response = $this->eloquaClient->Query($param);
                }
                catch (Exception $e)
                {
                $this->modx->log(modX::LOG_LEVEL_ERROR, 'Elq instance '.$this->config['instance'].' :'.'Error again getting contacts by Field Value: '.$e->getMessage().', giving up. Contact with field value '.$fieldValue.' was not added to the shared list.');
                }    
            }
            //pause a second after query to go easy on the server
            sleep(1);
            $queryResult = $response->QueryResult;
            if (is_array($queryResult->Entities->DynamicEntity)) {              
                 // 2+ results
                foreach ($queryResult->Entities->DynamicEntity as $dynamicEntity) {
                    $fieldValueCollection = $dynamicEntity->FieldValueCollection;
                    $elqFieldValue = $fieldValueCollection->getDynamicEntityField($fieldName);
                    if($elqFieldValue == $fieldValue) {
                        $this->modx->log(modX::LOG_LEVEL_ERROR,$fieldValueCollection->getDynamicEntityField('C_EmailAddress'));
                        $elqIds[] = $dynamicEntity->Id;
                    }
                }    
            }elseif ($queryResult->Entities instanceof stdClass ) {
                if(isset($queryResult->Entities->DynamicEntity)) {
                    // 1 result
                    $dynamicEntity = $queryResult->Entities->DynamicEntity;
                    $fieldValueCollection = $dynamicEntity->FieldValueCollection;
                    $elqFieldValue = $fieldValueCollection->getDynamicEntityField($fieldName);
                    if($elqFieldValue == $fieldValue) {
                        $this->modx->log(modX::LOG_LEVEL_ERROR,$fieldValueCollection->getDynamicEntityField('C_EmailAddress'));
                        $elqIds[] = $dynamicEntity->Id;
                    }
                }
            }
        }
        return $elqIds;
    }

    public function elqLocalTime($time, $state, $format = '%A %B %e, %Y, %l:%M%P') {
        $states = array(
            'AL' => '-6',
            'AK' => '-9',
            'AZ' => '-7',
            'AR' => '-6',
            'CA' => '-8',
            'CO' => '-7',
            'CT' => '-5',
            'DE' => '-5',
            'FL' => '-5',
            'GA' => '-5',
            'HI' => '-10',
            'ID' => '-7',
            'IL' => '-6',
            'IN' => '-5',
            'IA' => '-6',
            'KS' => '-6',
            'KY' => '-5',
            'LA' => '-6',
            'ME' => '-5',
            'MD' => '-5',
            'MA' => '-5',
            'MI' => '-5',
            'MN' => '-6',
            'MS' => '-6',
            'MO' => '-6',
            'MT' => '-7',
            'NE' => '-6',
            'NV' => '-8',
            'NH' => '-5',
            'NJ' => '-5',
            'NM' => '-7',
            'NY' => '-5',
            'NC' => '-5',
            'ND' => '-6',
            'OH' => '-5',
            'OK' => '-6',
            'OR' => '-8',
            'PA' => '-5',
            'RI' => '-5',
            'SC' => '-5',
            'SD' => '-6',
            'TN' => '-6',
            'TX' => '-6',
            'UT' => '-7',
            'VT' => '-5',
            'VA' => '-5',
            'WA' => '-8',
            'DC' => '-5',
            'WV' => '-5',
            'WI' => '-6',
            'WY' => '-7'
        );
        if(!$timezone = $states[$state]) $timezone = '-7';
        $zones = array(
            '-5' => array('code' => 'America/New_York', name => 'Eastern Time'),
            '-6' => array('code' => 'America/Chicago', name => 'Central Time'),
            '-7' => array('code' => 'America/Denver', name => 'Mountain Time'),
            '-8' => array('code' => 'America/Los_Angeles', name => 'Pacific Time'),
            '-9' => array('code' => 'America/Anchorage', name => 'Alaska Time'),
            '-10' => array('code' => 'America/Adak', name => 'Hawaii-Aleutian Time'),
        );
        if(empty($zones[$timezone]['code']) || !$time) return '';
        $systemTimezone = date_default_timezone_get();
        date_default_timezone_set($zones[$timezone]['code']);
        $localtime = strftime($format, $time).' '.$zones[$timezone]['name'];
        date_default_timezone_set($systemTimezone);
        return $localtime;
    }
}