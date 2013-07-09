<?php
/**
 * This file contains the Eloqua implementation of the modMail email service.
 * based on modPHPMailer by splittingred
 * @package modx
 * @subpackage mail
 */

require_once MODX_CORE_PATH . 'model/modx/mail/modmail.class.php';

/**
 * Eloqua implementation of the modMail service.
 *
 * @package modx
 * @subpackage mail
 */
class modEloquaMailer extends modMail {
	
	/*
	* @var EmailDetails $email
	* @var array $recipients
	* @var EloquaMailClient $mailer
	* @var DeploymentSettings $deploySettings
	*/
	
	public $emailDetails;
	public $recipients = array();
	public $mailer;
	private $wsdl;
	private $username = "";
	private $password = "";
	private $wxEloqua;
	
	
    /**
     * Constructs a new instance of the modEloquaMailer class.
     *
     * @param modX $modx A reference to the modX instance
     * @param array $attributes An array of attributes for the instance
     * @return modEloquaMailer
     */
     
     
    function __construct(modX &$modx, array $attributes= array()) {
        parent :: __construct($modx, $attributes);
        $modx->log(modX::LOG_LEVEL_ERROR, 'modEloquaMailer construct called');
        require_once $modx->getOption('core_path') . 'model/modx/mail/phpmailer/eloquamailservice.class.php';
        $this->wsdl = $modx->getOption('modEloquaMail.wsdl', NULL, 'https://secure.eloqua.com/API/1.2/EmailService.svc?wsdl');
        $this->emailDetails = new EmailDetails();
        $this->wxEloqua = $modx->getService('wxeloqua','wxEloqua',$modx->getOption('wxEloqua.core_path',null,$modx->getOption('core_path').'components/wxeloqua/').'model/wxeloqua/');
		if (!($this->wxEloqua instanceof wxEloqua)) die('could not instantiate wxEloqua');
        $this->_getMailer();
    }

    /**
     * Sets an Eloqua Mail attribute corresponding to the modX::MAIL_* constants or
     * a custom key.
     *
     * @param string $key The attribute key to set
     * @param mixed $value The value to set
     */
     
    public function set($key, $value) {
    	$this->modx->log(modX::LOG_LEVEL_ERROR, 'modEloquaMailer set called. Key: '.$key.' Value: '.$value);
        parent :: set($key, $value);
        switch ($key) {
            case modMail::MAIL_BODY :
                $this->emailDetails->HtmlContent= $this->attributes[$key];
                break;
            case modMail::MAIL_BODY_TEXT :
                $this->emailDetails->TextContent= $this->attributes[$key];
                break;
            case modMail::MAIL_CHARSET :
                break;
            case modMail::MAIL_CONTENT_TYPE :
                if ($value == 'text/plain') {
	                $this->emailDetails->SendHtml= false;
	                $this->emailDetails->AutoGenerateTextContent= false;
	            }elseif ($value == 'text/html') {
	                $this->emailDetails->SendHtml= false;
	            }
                break;
            case modMail::MAIL_ENCODING :
                break;
            case modMail::MAIL_ENGINE :
                break;
            case modMail::MAIL_ENGINE_PATH :
                break;
            case modMail::MAIL_FROM :
                $this->emailDetails->FromAddress= $this->attributes[$key];
                $this->emailDetails->ReplyToAddress= $this->attributes[$key];
                break;
            case modMail::MAIL_FROM_NAME :
                $this->emailDetails->FromName= $this->attributes[$key];
                break;
            case modMail::MAIL_HOSTNAME :
                break;
            case modMail::MAIL_LANGUAGE :
                break;
            case modMail::MAIL_PRIORITY :
                break;
            case modMail::MAIL_READ_TO :
                break;
            case modMail::MAIL_SENDER :
                $this->emailDetails->FromAddress= $this->attributes[$key];
                $this->emailDetails->ReplyToAddress= $this->attributes[$key];
                break;
            case modMail::MAIL_SMTP_AUTH :
                break;
            case modMail::MAIL_SMTP_HELO :
                break;
            case modMail::MAIL_SMTP_HOSTS :
                break;
            case modMail::MAIL_SMTP_KEEPALIVE :
                break;
            case modMail::MAIL_SMTP_PASS :
                break;
            case modMail::MAIL_SMTP_PORT :
                break;
            case modMail::MAIL_SMTP_PREFIX :
                break;
            case modMail::MAIL_SMTP_SINGLE_TO :
                break;
            case modMail::MAIL_SMTP_TIMEOUT :
                break;
            case modMail::MAIL_SMTP_USER :
                break;
            case modMail::MAIL_SUBJECT :
                $this->Subject= $this->attributes[$key];
                break;
            default :
                $this->modx->log(modX::LOG_LEVEL_WARN, $this->modx->lexicon('mail_err_attr_nv',array('attr' => $key)));
                break;
        }
    }
    

    /**
     * Adds an address to the email
     *
     * @param string $type The type of address (to, reply-to, bcc, cc)
     * cc and bcc not supported
     * only one reply-to supported
     * @param string $email The email address to address to
     * @param string $name The name of the email address
     * @return boolean True if was addressed
     */
     
    public function address($type, $email, $name= '') {
    	$this->modx->log(modX::LOG_LEVEL_ERROR, 'modEloquaMailer address called, email: '.$email);
        $set= false;
        if ($email) {
            $set= parent :: address($type, $email, $name);
            if ($set) {
                $type= strtolower($type);
                switch ($type) {
                    case 'to' :
                        $this->recipients[] = $email;
                        break;
                    case 'cc' :
                        break;
                    case 'bcc' :
                        break;
                    case 'reply-to' :
                        $this->emailDetails->ReplyToAddress = $email;
                        $this->emailDetails->ReplyToName = $name;
                        break;
                }
            }
        } elseif ($email === null) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $this->modx->lexicon('mail_err_unset_spec'));
        } else {
            $this->modx->log(modX::LOG_LEVEL_ERROR, $this->modx->lexicon('mail_err_address_ns'));
        }
        return $set;
    }
    

    /**
     * Eloqua does not offer the option to add custom headers, just return false
     *
     * @param string $header The header to set
     * @return boolean True if the header was successfully set
     */
     
    public function header($header) {
        return false;
    }
    

    /**
     * Send the email, applying any attributes to the mailer before sending.
     *
     * @param array $attributes An array of attributes to pass when sending
     * @return boolean True if the email was successfully sent
     */
     
    public function send(array $attributes= array()) {
    	$this->modx->log(modX::LOG_LEVEL_ERROR, 'modEloquaMailer send called');
        $sent = parent :: send($attributes);
        $emailId = $this->mailer->CreateHtmlEmail($this->emailDetails);
        $deploySettings = new DeploymentSettings(array('EmailId' => $emailId, 'DeploymentDate' => date('c')));
        $recipientIds = $this->wxEloqua->getContactsByFieldValues($this->recipients);
        $success = true;
        foreach ($recipientIds as $recipientId) {
        	$this->deploySettings->ContactId = $recipientId;
	        if(!$sent = $this->mailer->Deploy($this->deploySettings)) $success = false;
	    }
        return $success;
    }
    

    /**
     * Resets all PHPMailer attributes, including recipients and attachments.
     *
     * @param array $attributes An array of attributes to pass when resetting
     */
     
    public function reset(array $attributes= array()) {
        parent :: reset($attributes);
        $this->emailDetails = new EmailDetails();
        $this->recipients = array();
    }
    

    /**
     * Loads the PHPMailer object used to send the emails in this implementation.
     *
     * @return boolean True if the mailer class was successfully loaded
     */
     
    protected function _getMailer() {
    	$this->modx->log(modX::LOG_LEVEL_ERROR, 'modEloquaMailer getMailer called');
        $success= false;
        if (!$this->mailer || !($this->mailer instanceof ElqActionSoapClient)) {
            if ($this->mailer= new ElqActionSoapClient($this->wsdl, $this->username, $this->password)) {
                if (!empty($this->attributes)) {
                    foreach ($this->attributes as $attrKey => $attrVal) {
                        $this->set($attrKey, $attrVal);
                    }
                }
                $success= true;
            }
        }
        return $success;
    }
    

    /**
     * Attachments not supported
     *
     * @param mixed $file The file to attach
     * @param string $name The name of the file to attach as
     * @param string $encoding The encoding of the attachment
     * @param string $type The header type of the attachment
     */
     
    public function attach($file,$name = '',$encoding = 'base64',$type = 'application/octet-stream') {
        parent :: attach($file);
    }
    

    /**
     * Clears all existing attachments.
     */
     
    public function clearAttachments() {
        parent :: clearAttachments();
    }
    

    /**
     * Sets email to HTML or text-only.
     *
     * @access public
     * @param boolean $toggle True to set to HTML.
     */
     
    public function setHTML($toggle) {
        $this->emailDetails->SendHtml= $toggle;
        $this->emailDetails->AutoGenerateTextContent = $toggle;
    }
    
//}