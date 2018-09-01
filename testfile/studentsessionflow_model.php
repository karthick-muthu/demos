<?php


Class Studentsessionflow_model extends MY_Model
{
	
	public function __construct() {
		 parent::__construct();
		 $this->dbEnglish = $this->load->database('mindspark_english',TRUE);
		 $this->load->library('session');
		 //$this->load->model('Language/questionspage_model');
		 $this->load->model('Language/passage_model');
		 $this->load->model('Language/freeques_model');
		 $this->load->model('Language/speaking_model');
		 
	}

	/**
	 * function description : To check which type(passage/question) of session to show to the child 
	 * param1   userID
	 * @return  string, sessionType 
	 * 
	 * */

	//This function need to modify the content flow next order
	// public function getSessionType($userID){
	// 	$userContentAtmptDetails=$this->session->userdata('content_attempt_details');
	// 	if($userContentAtmptDetails[0]['userContentFlowType'] == '')
	// 			$order=0;
	// 	else:			
	// 		$order=$userContentAtmptDetails[0]['orderNo'] ;		
	// 	$contentFlowArray = $this->defaultContentFlowOrder($order);
	// 	return $contentFlowArray[0]['contentType'];		
	// }
	public function getContentDetailsFromOrder($order){
		$templateID    = $this->session->userdata('templateID');
		$this->dbEnglish->Select('contentType,contentQuantity');
		$this->dbEnglish->from('contentFlowMaster');
		$this->dbEnglish->where('contentStatus','Yes');
		$this->dbEnglish->where('contentOrder',$order);
		$this->dbEnglish->where('templateID',$templateID);
		$query = $this->dbEnglish->get();
		return   $query->row_array();
	}

	public function getContentDetailsOfCurrentOrder($order){
		$nextOrderContent= $this-> getContentDetailsFromOrder($order);
		//print_r($nextOrderContent);exit;
		if(isset($nextOrderContent) && count($nextOrderContent)):
			$contentType=$nextOrderContent['contentType'];
			$contentQuantity=$nextOrderContent['contentQuantity'];
		else:
			$order=1;
			$templateID    = $this->session->userdata('templateID');
			$this->dbEnglish->Select('contentType,contentQuantity');
			$this->dbEnglish->from('contentFlowMaster');
			$this->dbEnglish->where('contentStatus','Yes');
			$this->dbEnglish->where('contentOrder',$order);
			$this->dbEnglish->where('templateID',$templateID);
			$query = $this->dbEnglish->get();
			$result=  $query->row_array();
			$contentType=$result['contentType'];
			$contentQuantity=$result['contentQuantity'];
		endif;
		$resultArr=array(
			'contentType'    	=>  $contentType,
			'order'		  		=>  $order,
			'contentQuantity'	=>  $contentQuantity
			);
		return $resultArr;
	}

	/*public function contentFlowContentType($userID,$type){

		if($type=='reading' || $type=='conversation') :
			$this->passage_model->setNextPassageData($userID);
			$contentType='Passage';
		else :.
			$this->session->unset_userdata('sessionPassages');
			$this->session->unset_userdata('currentPsgQuestions');
			$this->freeques_model->setNextFreeQuesData($userID);	
			$freeQuestion=$this->session->userdata("sessionfreeQues");			
			$data=array('currentContentType'=>currContentTypeFreeQuesConst,'completed'=>0,'refID'=>$freeQuestion[0]);
			$this->session->set_userdata($data);
			$this->dbEnglish->where('userID', $userID);
			$this->dbEnglish->update('userCurrentStatus',$data);
			$contentType='FreeQuestion';
		endif;
		return $contentType;
	}*/

	/*public function getSessionTypeToContentFlowShow($userID,$order) {
		$getCurrentContentType = $this->getContentFlowOrder($order);
		print_r($getCurrentContentType);
		$currentContentType = $this->contentFlowContentType($userID,$getCurrentContentType['contentType']);
		return $currentContentType;
	}


	


	*/

	// public function getUserContentAttemptDetails($userID){echo "hai";exit;
	// 	$this->dbEnglish->Select('userContentFlowType,contentAttemptCount,orderNo');
	// 	$this->dbEnglish->from('userContentAttemptLog');
	// 	$this->dbEnglish->where('userID',$userID);
	// 	$query = $this->dbEnglish->get();
	// 	return  $query->result_array();
	// }

	// public function defaultContentFlowOrder($order) {
	// 	$this->dbEnglish->Select('contentType,contentQuantity');
	// 	$this->dbEnglish->from('contentFlowMaster');
	// 	$this->dbEnglish->where('contentStatus','Yes');
	// 	$this->dbEnglish->where('contentOrder',$order+1);
	// 	$query = $this->dbEnglish->get();
	// 	return  $query->row_array();
	// }
	public function chckUserInUsrContentAttemptLog($userID){
		//echo "fdf";
		$this->dbEnglish->Select('userContentFlowType,contentAttemptCount,orderNo');
		$this->dbEnglish->from('userContentFlowStatus');
		$this->dbEnglish->where('userID',$userID);
		$query = $this->dbEnglish->get();
		//echo $this->dbEnglish->last_query();exit;
		return  $query->row_array();
	}

	public function getFirstContent(){
		$templateID    = $this->session->userdata('templateID');
		$this->dbEnglish->Select('contentType,contentQuantity');
		$this->dbEnglish->from('contentFlowMaster');
		$this->dbEnglish->where('templateID',$templateID);
		$this->dbEnglish->order_by('contentOrder','asc');
		$this->dbEnglish->limit(1);
		$query = $this->dbEnglish->get();
		return  $query->row_array();
	}

	public function getContentQuantity($contentType,$orderNo){
		//echo $contentType;
		$templateID    = $this->session->userdata('templateID');
		$this->dbEnglish->Select('contentType,contentQuantity');
		$this->dbEnglish->from('contentFlowMaster');
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->where('contentOrder',$orderNo);
		$this->dbEnglish->where('templateID',$templateID);
		$query = $this->dbEnglish->get();
		//echo $this->dbEnglish->last_query();exit;
		return  $query->row_array();

	}

	public function addDataToUserContentAttemptLog($userID,$contentType,$orderNo){
		$contentAttemptCount=0;
		$insertData = array(
			'userID'          		=>	$userID,
			'userContentFlowType'   => 	$contentType,
			'contentAttemptCount'   => 	$contentAttemptCount,
			'orderNo'         		=> 	$orderNo
		);
		$this->dbEnglish->insert('userContentFlowStatus', $insertData); 
		$insert_id = $this->dbEnglish->insert_id();

		$this->updateContentFlowSessionDetails($orderNo,$contentAttemptCount);
		//echo $this->dbEnglish->last_query();exit;
	}

	public function updateContentQuantity($userID,$quantity,$counter,$orderNo,$contentType){
		if($counter==0):
			$contentAttemptCount=$quantity;
			$this->session->set_userdata('isContentquantityEqual',0);
			$updatedOrderNo= $orderNo;
			$this->session->set_userdata('presentContentType', $contentType);
			$this->updateContentFlowSessionDetails($updatedOrderNo,$contentAttemptCount);
		else:
			$this->skipCurrentContentFlow($orderNo,$userID);
		endif;
	}


	public function skipCurrentContentFlow($orderNo,$userID) {

		$nextOrderContent=$this->getContentDetailsOfCurrentOrder($orderNo);
		$this->session->set_userdata('presentContentType',$nextOrderContent['contentType']);
		$updatedOrderNo=$nextOrderContent['order'];
		$contentAttemptCount=0;
		$this->dbEnglish->set('userContentFlowType', $nextOrderContent['contentType']);
		$this->dbEnglish->set('contentAttemptCount',0);
		$this->dbEnglish->set('orderNo', $updatedOrderNo);
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->update('userContentFlowStatus');
		//echo $this->dbEnglish->last_query();
		$contentType=$nextOrderContent['contentType'];	
		//$this->session->set_userdata('presentContentType',$nextOrderContent['contentType']);
		$this->session->set_userdata('contentQuantity',$nextOrderContent['contentQuantity']);
		$this->session->set_userdata('sessionfreeQues',0);
		$this->updateUserCurrentStatus($nextOrderContent['contentType'],$userID);	
		$this->updateContentFlowSessionDetails($updatedOrderNo,$contentAttemptCount);

	}


	public function updateUserCurrentStatus($contentType,$userID){
		if($contentType=='reading' || $contentType=='conversation'):
			$contentType='passage';
		elseif($contentType=='freeques'):
			$contentType='freeQues';
		else :
			$contentType='speaking';
		endif;
		$this->session->set_userdata('currentContentType', $contentType);
	}

	public function updateContentFlowSessionDetails($updatedOrderNo,$contentAttemptCount){
		$this->session->set_userdata('orderNo', $updatedOrderNo);
		$this->session->set_userdata('contentAttemptCount', $contentAttemptCount);
	}

	public function getNextContentType($userID){

		$checkContentFlowOrder = $this->checkContentFlowOrder($userID);
		$userIDInAtmptLog=$this->chckUserInUsrContentAttemptLog($userID);
		//print_r($userIDInAtmptLog); //checks if the user has any entry in contentAttemptLog		
		if(count($userIDInAtmptLog)>0):
			$getContentQuantity=$this->getContentQuantity($userIDInAtmptLog['userContentFlowType'],$userIDInAtmptLog['orderNo']);			
			$contentQuantity=$getContentQuantity['contentQuantity'];
			$contentAttemptCount= $userIDInAtmptLog['contentAttemptCount'];	
			//echo "contentAttemptCount - $contentAttemptCount";exit;
			//echo $userIDInAtmptLog['contentAttemptCount'];
			//echo $getContentQuantity['contentQuantity'];
			if($userIDInAtmptLog['contentAttemptCount']>=$getContentQuantity['contentQuantity']):
				//echo "qrong";exit;
				$presentOrdernumber = $userIDInAtmptLog['orderNo'];
				$orderNo = $presentOrdernumber + 1;
				$this->updateContentQuantity($userID,$quantity=0,$counter=1,$orderNo,$userIDInAtmptLog['userContentFlowType']);
			else:
				//echo "correct";exit;
				$orderNo=$userIDInAtmptLog['orderNo'];
				$this->session->set_userdata('contentQuantity',$getContentQuantity['contentQuantity']);
			 	$this->updateContentQuantity($userID,$userIDInAtmptLog['contentAttemptCount'],$counter=0,$orderNo,$userIDInAtmptLog['userContentFlowType']);
			endif;
		else:
			$getFirstContent=$this->getFirstContent();
			//print_r($getFirstContent);exit;
			$contentQuantity=$getFirstContent['contentQuantity']; //reading
			//$contentAttemptCount= 1;
			$orderNo=1;
			$this->session->set_userdata('contentQuantity',$contentQuantity);
			$this->addDataToUserContentAttemptLog($userID,$getFirstContent['contentType'],$orderNo);
			$this->updateUserCurrentStatus($getFirstContent['contentType'],$userID);
			$this->session->set_userdata('presentContentType',$getFirstContent['contentType']);
			$userIDInAtmptLog=$this->chckUserInUsrContentAttemptLog($userID);
		endif;




		//$this->session->set_userdata('presentContentType', $userIDInAtmptLog['userContentFlowType']);
		


		$presentContentType = $this->session->userdata('presentContentType');
		//echo '<br>presentContentType'; print_r($presentContentType); 

		//print_r($getCurrentContentType);
		//$this->session->set_userdata('contentType', $getCurrentContentType['contentType']);
		$sessionFlowStarted=$this->session->userdata('sessionFlowStarted');
		//echo '<br>sessionFlowStarted'; print_r($sessionFlowStarted); 
		if(!$sessionFlowStarted)
			$this->updateSessionTimeLimits();
		//print_r($getCurrentContentType);exit;
		// $getTimeSpentToday=$this->questionspage_model->getTimeSpentToday($userID);
		// $sessionPsgTimeLimit=$this->session->userdata('sessionPsgTimeLimit');
		//echo  $getCurrentContentType['contentType'];exit;
		if($presentContentType=='reading' || $presentContentType=='conversation'){
			//if($presentContentType!='reading'):
			//	$this->session->unset_userdata('sessionPassages'); 
			//endif;
			$this->passage_model->setNextPassageData($userID);
			$this->session->set_userdata('sessionTypeToShow','Passage');
			return "Passage";
		}
		elseif($presentContentType=='speaking'){
			//echo "speaking";exit;
			$this->session->unset_userdata('sessionPassages');
			$this->session->unset_userdata('sessionSpeakingQues');			
			$this->session->unset_userdata('currentPsgQuestions');
			$this->session->unset_userdata("sessionfreeQues");
			$this->session->set_userdata('sessionTypeToShow','Speaking');
			//echo "haiii";exit;
			$this->speaking_model->setSpeakingQuesData($userID);
			return "Speaking";	
		}
		else{						
			$this->session->unset_userdata('sessionPassages');
			$this->session->unset_userdata('sessionSpeakingQues');
			$this->session->unset_userdata('currentPsgQuestions');
			$this->session->set_userdata('sessionTypeToShow','FreeQuestion');
			$childClass = $this->session->userdata('childClass');
			$schoolCode = $this->session->userdata('schoolCode');
			$groupSkillID = $this->session->userdata('groupSkillID');
			$schoolBunchingOrder = $this->freeques_model->nextSchoolBunchingOrder($schoolCode,$childClass,$groupSkillID);
			if($schoolBunchingOrder){
				$this->session->set_userdata('schoolBunchingOrder',$schoolBunchingOrder);
				$data=array('currentContentType'=>currContentTypeFreeQuesConst);
				$this->session->set_userdata($data);
				$this->freeques_model->setNextFreeQuesData($userID);
				$freeQuestion=$this->session->userdata("sessionfreeQues");				
				$refID = $this->session->userdata('refID');
				$completed = $this->session->userdata('completed');
				$isValidRefID = $this->session->userdata('isValidRefID');
				if($refID == "" || $refID == 0 || $completed == 1){
					$data['refID']=$freeQuestion[0];
					$this->session->set_userdata('isRefIDPresent',0);
				} else {
					if($isValidRefID == 1){ //part of existing flow so insert normally by taking currentBunchID from table
						$this->session->set_userdata('isRefIDPresent',0);
					} else { //not part of existing flow so insert 0 and do no increment totalAttempts
						$this->session->set_userdata('isRefIDPresent',1);
					}					
					$data['refID']=$refID;
				}
				$data['completed'] = 0;
				$this->session->set_userdata($data);
				$this->dbEnglish->where('userID', $userID);
				$this->dbEnglish->update('userCurrentStatus',$data);			
				return "FreeQuestion";
			} else {
				return array('schoolBunchingOrder' => 0);
			}
		}
	}


	/*public function getSessionTypeToShow($userID){
		//new
		//$getCurrentContentType = $this->getContentFlowOrder($order=0);
		$sessionFlowStarted=$this->session->userdata('sessionFlowStarted');
		if(!$sessionFlowStarted)
			$this->updateSessionTimeLimits();
		
		$getTimeSpentToday=$this->questionspage_model->getTimeSpentToday($userID);
		$sessionPsgTimeLimit=$this->session->userdata('sessionPsgTimeLimit');
		//if($getCurrentContentType['contentType']=='reading' || $getCurrentContentType['contentType']=='conversation') {

		if(($getTimeSpentToday+0.05) <= ($sessionPsgTimeLimit-1)){

			//print "Passage";
			$this->passage_model->setNextPassageData($userID);
			return "Passage";
		}
		else{

			$this->session->unset_userdata('sessionPassages');
			$this->session->unset_userdata('currentPsgQuestions');
			$this->freeques_model->setNextFreeQuesData($userID);	
			$freeQuestion=$this->session->userdata("sessionfreeQues");			
			$data=array('currentContentType'=>currContentTypeFreeQuesConst,'completed'=>0,'refID'=>$freeQuestion[0]);
			$this->session->set_userdata($data);
			$this->dbEnglish->where('userID', $userID);
			$this->dbEnglish->update('userCurrentStatus',$data);
			return "FreeQuestion";
		}
	}
	*/
	 /*
	 * function description :  update passage & free question time limit in session
	 * @return  none 
	 */
	
	public function updateSessionTimeLimits(){
		$timeAllowedPerDay=$this->session->userdata('timeAllowedPerDay');
		$this->session->set_userdata('sessionPsgTimeLimit',floor($timeAllowedPerDay/2));
		$this->session->set_userdata('sessionFreeQuesTimeLimit',ceil($timeAllowedPerDay/2));
		$this->session->set_userdata('sessionFlowStarted',1);
	}
	
	 /*
	 *  function description :  calculate passage time spent and free question time spent
	 *  param1   userID
	 *	@return  array,passage/freequestion time spent 
	 */ 
	
	public function getTotalTimeSpentPsgFreeQues($userID){
		$getTimeSpentToday=$this->questionspage_model->getTimeSpentToday($userID);
		$sessionPsgTimeLimit=$this->session->userdata('sessionPsgTimeLimit');
		$psgTimeSpentToday=0;
		$freeQuesTimeSpentToday=0;
		if(($getTimeSpentToday+0.05) >= ($sessionPsgTimeLimit-1)){
			$psgTimeSpentToday=$getTimeSpentToday+0.05;	
			$freeQuesTimeSpentToday=(($getTimeSpentToday+0.05)-($sessionPsgTimeLimit-1));
		}	
		else
			$psgTimeSpentToday=$getTimeSpentToday;
				
		return array($psgTimeSpentToday,$freeQuesTimeSpentToday);
	}

	public function checkContentFlowOrder($userID,$orderID="") {

		$getCurrentContentOrder =  ($orderID) ? $orderID :  $this->session->userdata('orderNo');
		$userContentFlowType    = $this->session->userdata('presentContentType');
		$curretContentFlow         =  $this->getContentQuantity($userContentFlowType,$getCurrentContentOrder);
		if(empty($curretContentFlow)) :
			$nextconteFlow = $this->nextconteFlow($userContentFlowType);
			$data = array('orderNo' =>$nextconteFlow['contentOrder']);
			$this->dbEnglish->where('userID', $userID);
			$this->dbEnglish->update('userContentFlowStatus',$data);
			$this->session->set_userdata($data);
			$this->session->set_userdata('contentQuantity',$nextconteFlow['contentQuantity']);

		endif;
	}


	public function nextconteFlow($userContentFlowType) {
		$templateID    = $this->session->userdata('templateID');
		$this->dbEnglish->Select('contentType,contentQuantity,contentOrder');
		$this->dbEnglish->from('contentFlowMaster');
		$this->dbEnglish->where('contentType',$userContentFlowType);
		$this->dbEnglish->where('templateID',$templateID);
		$this->dbEnglish->limit(1);
		$query = $this->dbEnglish->get();
		return  $query->row_array();
	}

	public function getTemplateIDFrmSchoolCode($schoolCode,$childClass){
		$this->dbEnglish->Select('templateID');
		$this->dbEnglish->from('schoolContentFlowOrder');
		$this->dbEnglish->where('schoolCode',$schoolCode);
		$this->dbEnglish->where('childClass',$childClass);
		$query = $this->dbEnglish->get();
		$result=  $query->row_array();
		if($result):
			$templateID=$result['templateID'];
		else:
			$templateID=1;
		endif;
		return $templateID;
	}
} 
?>