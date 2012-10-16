<?php

/*
* Helper classes for Eloqua Mail webservice
*/

class Email {
	
	public $config = array();
	public $Id;
	public $Name = null;
	public $Subject = null;
	
	function __construct(array $config = array()) {
		$this->config = $config;
		if(!empty($config['Id'])) $this->Id = $config['Id'];
		if(!empty($config['Name'])) $this->Id = $config['Name'];
		if(!empty($config['Subject'])) $this->Id = $config['Subject'];
	}
}

class EmailDetails extends Email {
	
	public $config = array();
	public $AutoGenerateTextContent = 1;
	public $BouncebackAddress = null;
	public $EncodingId = 0;
	public $FooterId = 0;
	public $FromAddress = null;
	public $FromName = null;
	public $GroupId = 1;
	public $HeaderId = 0;
	public $HtmlContent = null;
	public $NeverExpires = 1;
	public $ReplyToAddress = null;
	public $ReplyToName = null;
	public $SendHtml = 1;
	public $SendToUnsubscribes = 0;
	public $TextContent = null;
	public $Tracked = 1;
	
	function __construct(array $config = array()) {
		$this->config = $config;
		parent :: __construct($config);
		if(!empty($config['AutoGenerateTextContent'])) $this->AutoGenerateTextContent = $config['AutoGenerateTextContent'];
		if(!empty($config['BouncebackAddress'])) $this->BouncebackAddress = $config['BouncebackAddress'];
		if(!empty($config['EncodingId'])) $this->EncodingId = $config['EncodingId'];
		if(!empty($config['FooterId'])) $this->FooterId = $config['FooterId'];;
		if(!empty($config['FromAddress'])) $this->FromAddress = $config['FromAddress'];
		if(!empty($config['FromName'])) $this->FromName = $config['FromName'];
		if(!empty($config['GroupId'])) $this->GroupId = $config['GroupId'];
		if(!empty($config['HeaderId'])) $this->HeaderId = $config['HeaderId'];
		if(!empty($config['HtmlContent'])) $this->HtmlContent = $config['HtmlContent'];
		if(!empty($config['NeverExpires'])) $this->NeverExpires = $config['NeverExpires'];
		if(!empty($config['ReplyToAddress'])) $this->ReplyToAddress = $config['ReplyToAddress'];
		if(!empty($config['ReplyToName'])) $this->ReplyToName = $config['ReplyToName'];
		if(!empty($config['SendHtml'])) $this->SendHtml = $config['SendHtml'];
		if(!empty($config['SendToUnsubscribes'])) $this->SendToUnsubscribes = $config['SendToUnsubscribes'];
		if(!empty($config['TextContent'])) $this->TextContent = $config['TextContent'];
		if(!empty($config['Tracked'])) $this->Tracked = $config['Tracked'];
	}

}

class DeploymentSettings {
	
	public $config = array();
	public $AllowResend = 1;
	public $AllowSendToBouncebacked = 0;
	public $AllowSendToEmailGroupUnsubscribed = 0;
	public $AllowSendToMasterExcludeList = 0;
	public $AllowSendToUnsubscribed = 0;
	public $ContactId = 1;
	public $DeploymentDate;
	public $DistributionListId;
	public $EmailId;
	public $ExcludeGroupIds;
	public $IncludeGroupIds;
	public $Name = null;
	public $NotificationEmailAddress = null;
	public $SendOnBehalfOfUser = null;
	public $SignatureRuleId = null;
	
	function __construct(array $config = array()) {
		$this->config = $config;
		if(!empty($config['AllowResend'])) $this->AllowResend = $config['AllowResend'];
		if(!empty($config['AllowSendToBouncebacked'])) $this->AllowSendToBouncebacked = $config['AllowSendToBouncebacked'];
		if(!empty($config['AllowSendToEmailGroupUnsubscribed'])) $this->AllowSendToEmailGroupUnsubscribed = $config['AllowSendToEmailGroupUnsubscribed'];
		if(!empty($config['AllowSendToMasterExcludeList'])) $this->AllowSendToMasterExcludeList = $config['AllowSendToMasterExcludeList'];
		if(!empty($config['AllowSendToUnsubscribed'])) $this->AllowSendToUnsubscribed = $config['AllowSendToUnsubscribed'];
		if(!empty($config['ContactId'])) $this->ContactId = $config['ContactId'];
		if(!empty($config['DeploymentDate'])) $this->DeploymentDate = $config['DeploymentDate'];
		if(!empty($config['DistributionListId'])) $this->DistributionListId = $config['DistributionListId'];
		if(!empty($config['EmailId'])) $this->EmailId = $config['EmailId'];
		if(!empty($config['ExcludeGroupIds'])) $this->ExcludeGroupIds = $config['ExcludeGroupIds'];
		if(!empty($config['IncludeGroupIds'])) $this->IncludeGroupIds = $config['IncludeGroupIds'];
		if(!empty($config['Name'])) $this->Name = $config['Name'];
		if(!empty($config['NotificationEmailAddress'])) $this->NotificationEmailAddress = $config['NotificationEmailAddress'];
		if(!empty($config['SendOnBehalfOfUser'])) $this->SendOnBehalfOfUser = $config['SendOnBehalfOfUser'];
		if(!empty($config['SignatureRuleId'])) $this->SignatureRuleId = $config['SignatureRuleId'];
	}
}

?>