<?php
define('MODX_API_MODE', true);
require_once '/mnt/stor9-wc1-dfw1/627233/dev.dealerwebinars.com/web/content/index.php';

if (!($modx instanceof modX)) exit();

$webinarFieldMapping = '{
	"C_Current_Webinar_Title1" : {"source" : "presentation", "key" : "wbn.longtitle"}, 
	"C_Current_Webinar_Access_Code1" : {"source" : "presentation", "key" : "accesscode"}, 
	"C_Current_Webinar_Calendar_URL1" : {"source" : "presentation", "key" : "calendarurl"}, 
	"C_Current_Webinar_Date_Time1" : {"source" : "presentation", "key" : "eventdate"}, 
	"C_Current_Webinar_Dialin_Number1" : {"source" : "presentation", "key" : "dialin"}, 
	"C_Current_Webinar_Landing_Page_URL1" : {"source" : "presentation", "key" : "landingpageurl"}, 
	"C_Current_Webinar_Slides_URL1" : {"source" : "presentation", "key" : "slides"}
}';

$modx->runSnippet('eloquaCloudConnector', array(
	'instance' => 0,
	'debug' => 1, 
	'dateTimes' => '["C_Current_Webinar_Date_Time1"]',
	'webinarProvider' => 'GoToWebinar',
	'webinarContext' => 'web',
	'calendarId' => 12,
	'cloudConnectorActions' =>'{
		"regStep" : {
			"pushfieldsets" : [],
			"pullfieldsets" : [{
				"entitytype" : {
					"id" : 0, 
					"name" : "Contact" , 
					"type" : "Base"
				}, 
				"pullfields" : {
					"C_Current_Webinar_Join_URL1" : "reg"
				}
			}],
			"pulllistconditions" : {},
			"pushlistconditions" : {},
			"completeconditions" : []
		},
		"reminderStep" : {
			"pushfieldsets" : [{
				"entitytype" : {
					"id" : 0, 
					"name" : "Contact",
					"type" : "Base"
				},
				"pushfields" : '.$webinarFieldMapping.'
			}],
			"pullfieldsets" : [],
			"pulllistconditions" : {},
			"pushlistconditions" : {},
			"completeconditions" : []
		},
		"attStep" : {
			"pushfieldsets" : [{
				"entitytype" : {
					"id" : 0, 
					"name" : "Contact",
					"type" : "Base"
				},
				"pushfields" : '.$webinarFieldMapping.'
			}],
			"pullfieldsets" : [{
				"entitytype" : {
					"id" : 19, 
					"name" : "Attendance" , 
					"type" : "DataCardSet"
				}, 
				"pullfields" : {
					"Attendance_Time_Text1" : "attended"
				},
				"emailfield" : "Email_Address1",
				"codefield" : "Code1"
			}],
			"pulllistconditions" : [{
				"modxfield" : "attended",
				"fieldvalue" : 0,
				"truelist" : "missed",
				"falselist" : "att"
			}],
			"pushlistconditions" : {},
			"completeconditions" : [
				{
					"field" : "recording",
					"value" : 0,
					"success" : 0
				},
				{
					"field" : "slides",
					"value" : "",
					"success" : 0
				}
			]
		},
		"gtwAttStep" : {
			"checkattendance" : 1,
			"pushfieldsets" : [{
				"entitytype" : {
					"id" : 0, 
					"name" : "Contact",
					"type" : "Base"
				},
				"pushfields" : '.$webinarFieldMapping.'
			},{
				"entitytype" : {
					"id" : 19, 
					"name" : "Attendance" , 
					"type" : "DataCardSet"
				},
				"codeField": "Code1",
				"pushfields" : {
					"Attendance_Time_Text1" : {"source" : "provider", "key" : "attendanceTimeInMinutes"},
					"Email_Address1" : {"source" : "provider", "key" : "email"},
					"Code1" : {"source" : "provider", "key" : "id"},
					"Webinar_Date1" : {"source" : "presentation", "key" : "eventdate"},
					"Webinar_Title1" : {"source" : "presentation", "key" : "wbn.longtitle"},
					"Presenters1" : {"source" : "presentation", "key" : "presenterNames"},
					"Questions1" : {"source" : "provider", "key" : "questions"},
					"Surveys1" : {"source" : "provider", "key" : "polls"},
					"CMS_ID1" : {"source" : "provider", "key" : "id"},
					"GTW_Meeting_ID1" : {"source" : "provider", "key" : "ses.webinarKey"}
				}
			}],
			"pullfieldsets" : [],
			"pulllistconditions" : {},
			"pushlistconditions" : [{
				"eloquafield" : "Attendance_Time_Text1",
				"fieldvalue" : 0,
				"truelist" : "missed",
				"falselist" : "att"
			}],
			"completeconditions" : [
				{
					"field" : "recording",
					"value" : 0,
					"success" : 0
				},
				{
					"field" : "slides",
					"value" : "",
					"success" : 0
				}
			]
		}
	}',
));

?>