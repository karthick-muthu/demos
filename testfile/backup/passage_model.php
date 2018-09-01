<?php


Class Passage_model extends MY_Model
{

	public function __construct() {
		 parent::__construct();
 		 $this->dbEnglish = $this->load->database('mindspark_english',TRUE);
		 $this->load->library('session');
		//$this->load->model('Language/speaking_model');
	}

	/**
	 * function description : Check session passages...whether they are set ,if they are not set set session passages
	 * param1  : userID  
	 * param2  : prevrefID = previous refID 
	 * @return : true 
	 * */
	public function setNextPassageData($userID,$prevrefID=NULL){
		$sessionPsg=$this->session->userdata('sessionPassages');
		if(!$sessionPsg):
			$this->setNextSessionPassages($userID,$prevrefID);
		endif;
		return true;
	}

	public function getCurrentPassagetype($refID) {
	          $passageSql = "SELECT passageType from passageMaster where passageID=$refID";
	          $result=$this->dbEnglish->query($passageSql);
	          $value = $result->row();
	          return $value;
      	}

      	public function getQcodePassageDetails($qcode) {
		    $this->dbEnglish->Select('passageID as qcodePassageID');
		    $this->dbEnglish->from('questions');
		    $this->dbEnglish->where('qcode',$qcode);
		    $query = $this->dbEnglish->get();
		    $qcodePassageDetailArr = $query->row();
		    if($qcodePassageDetailArr->qcodePassageID !=0){
			      $this->dbEnglish->Select('passageType');
			      $this->dbEnglish->from('passageMaster');
			      $this->dbEnglish->where('passageID',$qcodePassageDetailArr->qcodePassageID);
			      $query = $this->dbEnglish->get();
			      $passageTypeArr = $query->row();
			      $qcodePassageDetailArr->passageType=$passageTypeArr->passageType;
		    }      
		    return (array)$qcodePassageDetailArr;
  	}
	
	/**
	 * function description : Set session passages[10 psgs=5-Reading,5-listening] as per adaptive/exhuastion logic
	 * param1 : userID , 
	 * param2 : prevrefID = previous refID 
	 * param3 : exhaustionLogLevel shows the level in which user is in for exhaustion
	 * param4 : exhautionContentType is the exhaustion content type [Reading-comprehension]
	 * @return : array, Next session passages to show.  
	 * */
	
	public function getRecentAttmptdPsgID($userID,$passageType){
	//echo $passageType;exit;	
			$this->dbEnglish->Select('pa.passageID');
			$this->dbEnglish->from('passageAttempt pa');
			$this->dbEnglish->join('passageMaster pm', 'pm.passageID=pa.passageID', 'inner');
			$this->dbEnglish->where('pa.userID',$userID);
			$this->dbEnglish->where('pa.completed',2);
			if($passageType=='Textual' || $passageType=='Illustrated' ||  $passageType=='reading'):
				$this->dbEnglish->where_in('pm.passageType',array('Textual','Illustrated'));
			else:
				$this->dbEnglish->where('pm.passageType',$passageType);
			endif;	
			$this->dbEnglish->order_by('pa.lastModified','desc');
			$this->dbEnglish->limit(RemediationConstant);
			$query = $this->dbEnglish->get();
			$result=  $query->result_array();
			//echo $this->dbEnglish->last_query();exit;
			return $result;
	}

	

	function getAccurcyForPassageID($recentAttmptdPsgId,$userID) {
		$limitValue=RemediationAccuracyConst;
		//echo $recentAttmptdPsgId;exit;
		$getLowestAcc=$this->dbEnglish->query("select passageID,round(avg(correct)*100,2) as acc,attemptedDate from $this->questionAttemptClassTbl where userID=".$userID." and passageID in ($recentAttmptdPsgId) group by passageID having acc < ".$limitValue." order by acc ,attemptedDate limit 1");
		return  $getLowestAcc->row_array();

  	}

  	public function userContentAttemptLog($userID,$passageType) {
  		
  		$query=$this->dbEnglish->query("select totalAttempts,remediationPosition from userContentAttemptDetails where userID=".$userID." and contentType='".$passageType."'");
		$result=  $query->row_array();		
		// echo "total Attempts";
		// echo $this->dbEnglish->last_query();
		return $result;
  	}

  	public function checkForUserInContentAttemptLog($passageType){	
		$userID=$this->session->userdata('userID');
		$userContentAttempt = $this->userContentAttemptLog($userID,$passageType);	
	       //    echo 'userContentAttempt '; print_r($userContentAttempt);
		if(empty($userContentAttempt)):
			$getContentSpecificTotalAttempts=$this->getContentSpecificTotalAttempts($passageType);
			$totalAttempts=$getContentSpecificTotalAttempts['totalCount'];
			$insertData = array(
				'userID'          		=>	$userID,
				'totalAttempts'         => 	$totalAttempts,
				'contentType'          	=> 	$passageType
			);
			$this->dbEnglish->insert('userContentAttemptDetails', $insertData); 
			// echo "insertion";
			//echo $this->dbEnglish->last_query();
			$userContentAttempt = $this->userContentAttemptLog($userID,$passageType);
		endif;
		$totalAttempts=$userContentAttempt['totalAttempts'];
//		$this->session->set_userdata('contentFlowMasterType', $passageType);
		if($passageType=='reading'):
			$this->session->set_userdata('totalReadingAttempts', $totalAttempts);
		elseif($passageType=='speaking'):
			$this->session->set_userdata('totalSpeakingAttempts', $totalAttempts);
		elseif($passageType=='conversation'):
			$this->session->set_userdata('totalConversationAttempts', $totalAttempts);
		endif;
		//print_r($userContentAttempt);exit;
	//	exit;
		return $userContentAttempt;
	}

	/*public function checkForRemediation($resultArr,$userID,$passageType){
		$totalAttempts=$resultArr['totalAttempts'];
		$remediationPosition=$resultArr['remediationPosition'];
		if(($remediationPosition-1)==$totalAttempts):

			 $recentAttmptdPsgID=$this->getRecentAttmptdPsgID($totalAttempts,$userID,$passageType);
			 foreach ( $recentAttmptdPsgID as  $value) {
			 	$result.= $value['passageID'].',';
			 }
			$recentAttmptdPsgId=rtrim($result,',');
			$accuracyVal=$this->getAccurcyForPassageID($recentAttmptdPsgId,$userID);
			if(!empty($accuracyVal)):
				$this->dbEnglish->set('remediationPosition', $totalAttempts+1+RemediationConstant);
				
							//$accuracyVal['passageID']
			else:
				$this->dbEnglish->set('remediationPosition', $totalAttempts+RemediationConstant);
				//return 0				
			endif;
			$this->dbEnglish->set('totalAttempts', $totalAttempts+1);
			$this->dbEnglish->where('userID',$userID);
			$this->dbEnglish->where('contentType',$passageType);
			$this->dbEnglish->update('contentAttemptLog');
			//echo "<pre>";print_r($resultArr);exit;

			
		else:
			echo "no";exit;
		endif;

	}*/

	public function implodeArrayvalue($result){
	    $values = array_map('array_pop', $result);
	    return implode(',', $values);
	}

	public function checkNextRemediation($totalAttempts,$userID,$passageType) {

		$resultAttmptdPsgID=$this->getRecentAttmptdPsgID($userID,$passageType);
		// echo "hai";echo $this->dbEnglish->last_query();exit;
		$recentAttemptedID   = $this->implodeArrayvalue($resultAttmptdPsgID);
		$passageID 		= $this->userLowestAccurcyPsg($userID,$recentAttemptedID);
			//echo $this->dbEnglish->last_query();exit;	
		$totalAttemptCount    = (empty($passageID)) ? $totalAttempts+RemediationConstant+1 : $totalAttempts+2+RemediationConstant;
		//echo $totalAttemptCount;exit;
		/*$this->dbEnglish->set('remediationPosition',$totalAttemptCount);
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$passageType);
		$this->dbEnglish->update('contentAttemptLog');*/
		$this->session->set_userdata('remediationPosition',$totalAttemptCount);
		$data=array('remediationPosition'=> $totalAttemptCount);
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$passageType);
		$this->dbEnglish->update('userContentAttemptDetails', $data);
		return $passageID; 
	}


	public function userLowestAccurcyPsg($userID,$recentAttemptedID) {
		$remediationAccuracyConst=RemediationAccuracyConst;
		//echo "recentAttemptedID";exit;
		$query="select passageID,round(avg(correct)*100,2) as acc,attemptedDate from $this->questionAttemptClassTbl where userID=".$userID." and passageID in ($recentAttemptedID) group by passageID having acc < ".$remediationAccuracyConst." order by acc ,attemptedDate limit 1";
		$result = $this->dbEnglish->query($query)->row_array();
		//echo $this->dbEnglish->last_query();exit;

		return  $result;
	}

	public function getReadingMaxAttemptedPassages($userID) {
		
		$getReadingMaxAttemptedPassages=array();
		$query = "SELECT a.passageID FROM passageAttempt a 
		LEFT JOIN  (SELECT if((SELECT MAX(attemptCount) FROM passageAttempt WHERE userID = $userID)!=1,passageID,null) as passageID FROM passageAttempt
		WHERE attemptCount = (SELECT MAX(attemptCount) FROM passageAttempt
		WHERE userID = ".$userID.") AND userID = ".$userID.") AS t ON a.passageID = t.passageID
		INNER JOIN passageMaster p on a.passageID=p.passageID 
		WHERE a.userID = ".$userID." and a.completed=2 AND t.passageID IS NULL 
		and p.passageType in ('Textual','Illustrated') ORDER BY a.lastModified , a.attemptCount ASC limit 5";
    		$result=$this->dbEnglish->query($query);
              	$attemptedPassageIDArr =  $result->result_array();
              	foreach($attemptedPassageIDArr as $value)
		{
				if($contentType == readingContentTypeConst){
					if (in_array($value['passageID'], $psgArr)) {
					   array_push($getReadingMaxAttemptedPassages,$value['passageID']);
					}
				}else{
					array_push($getReadingMaxAttemptedPassages,$value['passageID']);
				}
		}

		return $getReadingMaxAttemptedPassages;

	}


	public function getConversationMaxAttemptedPassages($userID) {
		
		$getConversationMaxAttemptedPassages=array();
              	$query = "SELECT a.passageID FROM passageAttempt a 
		LEFT JOIN  (SELECT if((SELECT MAX(attemptCount) FROM passageAttempt WHERE userID = $userID)!=1,passageID,null) as passageID FROM passageAttempt
		WHERE attemptCount = (SELECT MAX(attemptCount) FROM passageAttempt
		WHERE userID = ".$userID.") AND userID = ".$userID.") AS t ON a.passageID = t.passageID
		INNER JOIN passageMaster p on a.passageID=p.passageID 
		WHERE a.userID = ".$userID." and a.completed=2 AND t.passageID IS NULL 
		and p.passageType in ('conversation') ORDER BY a.lastModified , a.attemptCount ASC limit 5";

    		$result=$this->dbEnglish->query($query);
              	$attemptedPassageIDArr =  $result->result_array();
              	foreach($attemptedPassageIDArr as $value)
		{
				if($contentType == listeningContentTypeConst){
					if (in_array($value['passageID'], $psgArr)) {
					   array_push($getConversationMaxAttemptedPassages,$value['passageID']);
					}
				}else{
					array_push($getConversationMaxAttemptedPassages,$value['passageID']);
				}
		}

		return $getConversationMaxAttemptedPassages;
	}
	

	// public function setNextSessionPassages($userID,$prevrefID=NULL,$exhaustionLogLevel=false,$exhaustionContentType=false)
	// {
	// 			$readingPsgsArr=array();
	// 			$listeningPsgsArr=array();
	// 			$psgDataArr=array();
	// 			$attemptedPassageID="";
	// 			$currentContentType=$this->session->userdata('currentContentType');
	// 			$userID=$this->session->userdata('userID');                        
	// 			$passageType=$this->session->userdata('presentContentType');
	// 			$userInContentAttemptLog=$this->checkForUserInContentAttemptLog($passageType);	
	// 			$remediationPstn=$userInContentAttemptLog['remediationPosition'];
	// 			$totalAttempts=$userInContentAttemptLog['totalAttempts'];
	// 			$checkVal =$totalAttempts %  RemediationConstant;
	// 			/*Checking the remediation count it's dived / RemediationConstant  */
	// 			///$totalAttempts!=0 &&

	// 			$passageID='';
	// 			//print_r($userInContentAttemptLog);exit;
	// 			if(($remediationPstn!="" &&  ($totalAttempts==($remediationPstn-1))) || ($remediationPstn=="" &&  ($checkVal==0)) && ($totalAttempts!=0)) :
	// 					//print_r($totalAttempts);exit;
	// 				//	echo "here";
	// 					$passageAttempt=$this->checkNextRemediation($userInContentAttemptLog['totalAttempts'],$userID,$passageType);
	// 					if($passageAttempt['passageID']) :
	// 								$passageID = $passageAttempt['passageID'];
	// 					endif;
	// 			endif;
	// 			/*Checking the Remediation passage return any value or Normal unattempted passage will load*/

	// 			if($passageID==''):
	// 				$currentContent=$this->session->userdata('currentContentType');
					
	// 				// Content flow order not required any present content type because it will load based on the content flow not in currentContentType

	// 				// Karthick removed the code  //

	// 				// For Offline sync will discuess with anand

	// 				// if($currentContent=='passage_ques') {
	// 				// 	$arrDetailPsg=$this->questionspage_model->getQcodePassageDetails($prevrefID);
	// 				// 	$passageType=$arrDetailPsg['passageType'];
	// 				// 	if($passageType=='Textual' || $passageType=='Illustrated')
	// 				// 	$passageType='reading';
	// 				// }
	// 				$passageLevel=$this->session->userdata('passageLevel');
	// 				$conversationLevel=$this->session->userdata('conversationLevel');
	// 				$attemptedPassageID = $this->questionspage_model->getUserAttemptedPassage($userID);
	// 				if($attemptedPassageID!=""):
	// 					$attemptedPassageIDArr = explode(',', $attemptedPassageID);
	// 				endif;
	// 				if($passageType=='reading'): 
	// 						$readingPsgsDataArr=$this->getReadingPassageArr($userID,$attemptedPassageIDArr);
	// 						foreach($readingPsgsDataArr as $key=>$value)  {
	// 										array_push($readingPsgsArr,$value['passageID']);
	// 						}
	// 						$readingPsgsArr=$this->setUncompletedPassages($readingPsgsArr,$attemptedPassageIDArr,readingContentTypeConst);
	// 						if(empty($readingPsgsArr)) :
	// 							$readingPsgsArr = $this-> getReadingMaxAttemptedPassages($userID,$passageType);
	// 						endif;
	// 				elseif($passageType=="conversation"):					
	// 						$listeningpsgsDataArr=$this->getConversatnPassageArr($userID,$attemptedPassageIDArr);
	// 						foreach($listeningpsgsDataArr as $key=>$value) {
	// 										array_push($listeningPsgsArr,$value['passageID']);
	// 						}

	// 						$listeningPsgsArr=$this->setUncompletedPassages($listeningPsgsArr,$attemptedPassageIDArr,listeningContentTypeConst,$convesationMsLevel);
	// 						//print_r($listeningPsgsArr);echo "before exhaustion condition";
	// 						if(empty($listeningPsgsArr)) :
	// 										$listeningPsgsArr=$this->getConversationMaxAttemptedPassages($userID,$passageType);
	// 						endif;
	// 						//print_r($listeningPsgsArr);echo "after exhaustion condition";
	// 				endif;
					
					               
	// 				$userRemediationLog = $this->checkUserRemediation($userID,$passageType);
	// 				if($userRemediationLog['remediationPosition']==''):        
	// 					$nextPassagelimit=1;
	// 				else:
	// 					$nextPassagelimit = $userRemediationLog['remediationPosition'] - ($userRemediationLog['totalAttempts'] + 1);
	// 				endif;

	// 				$contentFlowQuantity=$this->session->userdata('contentQuantity');
	// 				$attemptCount=$this->session->userdata('contentAttemptCount');
	// 				$attemptdQty=$contentFlowQuantity-$attemptCount;
	// 				if($nextPassagelimit>=$attemptdQty):
	// 					$nextPassagelimit=$attemptdQty;
	// 				endif;
	// 				for ($i=0; $i<$nextPassagelimit; $i++) { 
	// 					if($passageType=='reading'){
	// 						$psgDataArr[$i] = $readingPsgsArr[$i];
	// 					}
	// 					else{
	// 						$psgDataArr[$i] = $listeningPsgsArr[$i];  
	// 					}
	// 				}
	// 				$remediationStatus = 0;               

	// 			else :
	// 				if(!$this->session->userdata('sessionPassages')):                                                           
	// 					$psgDataArr[] = $passageID;
	// 				else:
	// 					$psgDataArr[1] = $passageID;
	// 				endif;		
	// 				$remediationStatus = 1;
	// 			endif;
	// 			//Remove cacher issues
	// 			//
	// 			$this->session->set_userdata('sessionPassages',$psgDataArr);
	// 			$this->session->set_userdata('remediationStatus',$remediationStatus);
	// 			$this->updateUserCurrentStatusTbl($userID);  
 //     }

	public function setNextSessionPassages($userID,$prevrefID=NULL,$exhaustionLogLevel=false,$exhaustionContentType=false){		
		$readingPsgsArr=array(); $listeningPsgsArr=array();
		$psgDataArr=array(); $passageID='';
		$remediationStatus = 0;
		$attemptedPassageID=""; 
		$userID = $this->session->userdata('userID');                        
		$passageType = $this->session->userdata('presentContentType');
		$currentContentType = $this->session->userdata('currentContentType');

		// Online and offline code is sync checking before content flow started
		/*-------------------------Online and Offline sync pending code----------------------------- */
		$passageID = $this->checkPendingPassage($userID,$passageType);
		$userInContentAttemptLog=$this->checkForUserInContentAttemptLog($passageType);
		//echo $passageID;exit;
		/*-------------------------Online and Offline sync pending code----------------------------- */
		if(!$passageID) : 			
			$remediationPstn=$userInContentAttemptLog['remediationPosition'];
			$totalAttempts=$userInContentAttemptLog['totalAttempts'];
			$checkVal =$totalAttempts %  RemediationConstant;
			/*checking the remediation count it's dived / RemediationConstant  */
			if(($remediationPstn!="" &&  ($totalAttempts==($remediationPstn-1))) || ($remediationPstn=="" &&  ($checkVal==0)) && ($totalAttempts!=0)) :
				$passageAttempt=$this->checkNextRemediation($userInContentAttemptLog['totalAttempts'],$userID,$passageType);
				if($passageAttempt['passageID']) :
					$passageID = $passageAttempt['passageID'];
					$remediationStatus=1;
				endif;
			endif;

			/*Normal passage flow started here*/
			if(!$passageID) :
				$remediationStatus=0;
				$currentContent=$this->session->userdata('currentContentType');
				$passageLevel=$this->session->userdata('passageLevel');
				$conversationLevel=$this->session->userdata('conversationLevel');
				$attemptedPassageID = $this->questionspage_model->getUserAttemptedPassage($userID);
				if($attemptedPassageID!=""):
					$attemptedPassageIDArr = explode(',', $attemptedPassageID);
				endif;
				if($passageType=='reading'): 
					$readingPsgsDataArr=$this->getReadingPassageArr($userID,$attemptedPassageIDArr);
					foreach($readingPsgsDataArr as $key=>$value)  {
						array_push($readingPsgsArr,$value['passageID']);
					}
					$readingPsgsArr=$this->setUncompletedPassages($readingPsgsArr,$attemptedPassageIDArr,readingContentTypeConst);
					if(empty($readingPsgsArr)) :
						$readingPsgsArr = $this-> getReadingMaxAttemptedPassages($userID,$passageType);
					endif;
				elseif($passageType=="conversation"):					
					$listeningpsgsDataArr=$this->getConversatnPassageArr($userID,$attemptedPassageIDArr);
					foreach($listeningpsgsDataArr as $key=>$value) {
						array_push($listeningPsgsArr,$value['passageID']);
					}
					$listeningPsgsArr=$this->setUncompletedPassages($listeningPsgsArr,$attemptedPassageIDArr,listeningContentTypeConst,$convesationMsLevel);
					if(empty($listeningPsgsArr)) :
						$listeningPsgsArr=$this->getConversationMaxAttemptedPassages($userID,$passageType);
					endif;
				endif;

				$userRemediationLog = $this->checkUserRemediation($userID,$passageType);
				if($userRemediationLog['remediationPosition']==''):        
					$nextPassagelimit=1;
				else:
					$nextPassagelimit = $userRemediationLog['remediationPosition'] - ($userRemediationLog['totalAttempts'] + 1);
				endif;

				$contentFlowQuantity=$this->session->userdata('contentQuantity');
				$attemptCount=$this->session->userdata('contentAttemptCount');
				$attemptdQty=$contentFlowQuantity-$attemptCount;
				if($nextPassagelimit>=$attemptdQty):
					$nextPassagelimit=$attemptdQty;
				endif;
				for ($i=0; $i<$nextPassagelimit; $i++) { 
					if($passageType=='reading')
					{
						$psgDataArr[$i] = $readingPsgsArr[$i];
					}
					else
					{
						$psgDataArr[$i] = $listeningPsgsArr[$i];  
					}
				}
			endif;
		endif;	

		if($passageID && !$this->session->userdata('sessionPassages')):                                                           
			$psgDataArr[]  = $passageID;
		elseif($passageID):
			$psgDataArr[1] = $passageID;
		endif;
		$this->session->set_userdata('sessionPassages',$psgDataArr);
		$this->session->set_userdata('remediationStatus',$remediationStatus);
		$this->updateUserCurrentStatusTbl($userID);  					
     }



     	public function checkPendingPassage($userID,$presentContentType) {

		$query = "SELECT  a.userID, a.passageID, a.completed, tmp.completed
		FROM  passageAttempt a  LEFT JOIN
		(SELECT a.passageID, a.userID, a.completed
		FROM  passageAttempt a, passageMaster b
		WHERE a.passageID = b.passageID";
		if($presentContentType=='reading') :
			$query.=" AND b.passageType IN('Textual','Illustrated')";
		else :
			$query.=" AND b.passageType = 'conversation' ";
		endif;

		$query.=" AND a.userID = $userID AND completed = 2) tmp ON a.passageID = tmp.passageID
		AND a.userID = tmp.userID JOIN passageMaster c ON a.passageID = c.passageID
		WHERE a.userID = $userID ";

		if($presentContentType=='reading') :
			$query.=" AND c.passageType IN('Textual','Illustrated')";
		else :
			$query.=" AND c.passageType = 'conversation' ";
		endif;
		$query.=" AND tmp.completed IS NULL";

		$result = $this->dbEnglish->query($query)->row_array();
		if(isset($result) && count($result)) :
			return $result['passageID'];
		endif;
	}

	public function checkUserRemediation($userID,$passageType) {
		$this->dbEnglish->Select('totalAttempts,remediationPosition');
		$this->dbEnglish->from('userContentAttemptDetails');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$passageType);
		$query = $this->dbEnglish->get();
		return $query->row_array();
	}

	public function updateUserCurrentStatusTbl($userID) {
		
		$presentContentType=$this->session->userdata('presentContentType');
		$presentContentType = strtolower($presentContentType);
		if($presentContentType=='reading' || $presentContentType=='conversation'):
			$contentType='passage';
			$sessionPassages=$this->session->userdata('sessionPassages');
			$refID=$sessionPassages[0];
		elseif($presentContentType=='speaking'):
			$contentType='speaking';
			$speakingQues=$this->session->userdata('sessionSpeakingQues');
			$refID=$speakingQues[0];
		endif;
		$this->session->set_userdata('currentContentType',$contentType);
		$this->session->set_userdata('refID',$refID);
		$this->session->set_userdata('completed',0);
		$data=array('currentContentType'=>$contentType,'refID'=>$refID,'completed'=>0);
		$this->dbEnglish->where('userID', $userID);
		$this->dbEnglish->update('userCurrentStatus',$data);
	}
	// ///@nd backup
	// public function setNextSessionPassages($userID,$prevrefID=NULL,$exhaustionLogLevel=false,$exhaustionContentType=false){
		
	// 	$readingPsgsArr=array();
	// 	$listeningPsgsArr=array();
	// 	$attemptedPassageID="";
	// 	//$currentContentType=$this->session->userdata('currentContentType');
	// 	$userID=$this->session->userdata('userID');
	// 	$passageType=$this->questionspage_model->getPassageType($this->session->userdata('refID'));
		
	// 	if($passageType='Textual' || $passageType='Illustrated'):
	// 		$passageType='reading';
	// 	endif;
	// 	/*Checking the contentattemptlog table*/
	// 	$UserInContentAttemptLog=$this->checkForUserInContentAttemptLog($passageType);
	// 	$remediationPstn=$UserInContentAttemptLog['remediationPosition'];
	// 	$totalAttempts=$UserInContentAttemptLog['totalAttempts'];
	// 	$checkVal =$totalAttempts %  RemediationConstant;
	// 	if(($remediationPstn!="" && ($totalAttempts==($remediationPstn-1))) || ($remediationPstn=="" &&  ($checkVal==0))) :
	// 		$passageAttempt=$this->checkNextRemediation($UserInContentAttemptLog['totalAttempts'],$userID,$passageType);
	// 		$passageID = ($passageAttempt['passageID']) ? $passageAttempt['passageID'] : '';
	// 	endif;
	// 	if(!$passageID) : 
	// 		$currentContent=$this->session->userdata('currentContentType');
	// 		/*if($currentContent=='passage'){
	// 			$passageType=$this->questionspage_model->getPassageType($this->session->userdata('refID'));
	// 		}*/
	// 		if($currentContent=='passage_ques') {
	// 			$arrDetailPsg=$this->questionspage_model->getQcodePassageDetails($prevrefID);
	// 			$passageType=$arrDetailPsg['passageType'];
	// 		}
	// 		$psgDataArr=array();
	// 		//echo $passageType;exit;
	// 		if($passageType=='reading'):
	// 			$readingPsgsDataArr=$this->getReadingPassageArr();
	// 			foreach($readingPsgsDataArr as $key=>$value):
	// 				array_push($readingPsgsArr,$value['passageID']);
	// 			endforeach;
	// 			$readingPsgsArr=$this->setUncompletedPassages($readingPsgsArr,$attemptedPassageIDArr,readingContentTypeConst);
	// 		else:
	// 			$listeningpsgsDataArr=$this->getConversatnPassageArr();
	// 			foreach($listeningpsgsDataArr as $key=>$value):
	// 				array_push($listeningPsgsArr,$value['passageID']);
	// 			endforeach;
	// 			$listeningPsgsArr=$this->setUncompletedPassages($listeningPsgsArr,$attemptedPassageIDArr,listeningContentTypeConst,$convesationMsLevel);

	// 		endif;
	// 		if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER') //ADDED BY NIVEDITA
	// 		{
	// 			$countOfReading   = count($readingPsgsArr);
	// 			$countOfListining = count($listeningPsgsArr);
	// 			if($countOfReading != 0 && $countOfListining != 0) {
	// 				if($countOfReading < $countOfListining)
	// 					$loopLength = $countOfReading;
	// 				elseif($countOfListining < $countOfReading)
	// 					$loopLength = $countOfListining;
	// 				for ($i=0; $i<$loopLength; $i++) {
	// 					if($passageType=='Textual'|| $passageType=='Illustrated'){
	// 						//$psgDataArr[] = $listeningPsgsArr[$i];	
	// 						$psgDataArr[] = $readingPsgsArr[$i];
	// 					}
	// 					else{
	// 						//$psgDataArr[] = $readingPsgsArr[$i];
	// 						$psgDataArr[] = $listeningPsgsArr[$i];	
	// 					}
	// 				}
	// 			}
	// 			else
	// 			{
	// 				$isContentExhaustedTeacher = array('isContentExhaustedTeacher'=>1);
	// 				$this->session->set_userdata($isContentExhaustedTeacher);
	// 			}

	// 		}
	// 		else
	// 		{
	// 			for ($i=0; $i<readingPsgCountConst; $i++) {
	// 				if($passageType=='reading'){

	// 					//$psgDataArr[] = $listeningPsgsArr[$i];	
	// 					$psgDataArr[] = $readingPsgsArr[$i];

	// 				}
	// 				else{

	// 					//$psgDataArr[] = $readingPsgsArr[$i];
	// 					$psgDataArr[] = $listeningPsgsArr[$i];	
	// 				}
	// 			}
	// 		}
	// 	else :
	// 		$psgDataArr=array();
	// 		$psgDataArr[] = $passageID;

	// 	endif;
		
	// 	$this->session->set_userdata('sessionPassages',$psgDataArr);
	// }
	// public function setNextSessionPassages($userID,$prevrefID=NULL,$exhaustionLogLevel=false,$exhaustionContentType=false){		
	// 	$readingPsgsArr=array();
	// 	$listeningPsgsArr=array();
	// 	$psgDataArr=array();
	// 	$passageDataArr=array();
	// 	$attemptedPassageID="";
	// 	$currentContent=$this->session->userdata('currentContentType');
	// 	$userID=$this->session->userdata('userID');
	// 	$refID = $this->session->userdata('refID');
	// 	$passageType=$this->questionspage_model->getPassageType($refID);	
	// 	/*Checking the contentattemptlog table*/
	// 	//echo "dfgfge";echo $this->session->userdata('refID');exit;
	// 	$UserInContentAttemptLog=$this->checkForUserInContentAttemptLog($passageType);
	// 	$remediationPstn=$UserInContentAttemptLog['remediationPosition'];
	// 	$totalAttempts=$UserInContentAttemptLog['totalAttempts'];
	// 	$checkVal =$totalAttempts %  RemediationConstant;
	// 	if(($remediationPstn!="" && ($totalAttempts==($remediationPstn-1))) || ($remediationPstn=="" &&  ($checkVal==0))) :
	// 		$passageAttempt=$this->checkNextRemediation($UserInContentAttemptLog['totalAttempts'],$userID,$passageType);
	// 		$passageID = ($passageAttempt['passageID']) ? $passageAttempt['passageID'] : '';
	// 	endif;
	// 	if(!$passageID) : 			
	// 		$currentContent=$this->session->userdata('currentContentType');
	// 		if($currentContent=='passage_ques') {
	// 			$arrDetailPsg=$this->questionspage_model->getQcodePassageDetails($prevrefID);
	// 			$passageType=$arrDetailPsg['passageType'];
	// 		}
	// 		//echo $passageType;exit;
	// 		if($passageType=='Textual'|| $passageType=='Illustrated'):
	// 			$passageDataArr=$this->getReadingPassageArr();
	// 		else:

	// 			$passageDataArr=$this->getConversatnPassageArr();
	// 		endif;
	// 		if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER') //ADDED BY NIVEDITA
	// 		{
	// 			$countOfReading   = count($readingPsgsArr);
	// 			$countOfListining = count($listeningPsgsArr);
	// 			if($countOfReading != 0 && $countOfListining != 0) {
	// 				if($countOfReading < $countOfListining)
	// 					$loopLength = $countOfReading;
	// 				elseif($countOfListining < $countOfReading)
	// 					$loopLength = $countOfListining;
	// 				for ($i=0; $i<$loopLength; $i++) {
	// 					if($passageType=='Textual'|| $passageType=='Illustrated'){
	// 						//$psgDataArr[] = $listeningPsgsArr[$i];	
	// 						$psgDataArr[] = $readingPsgsArr[$i];
	// 					}
	// 					else{
	// 						//$psgDataArr[] = $readingPsgsArr[$i];
	// 						$psgDataArr[] = $listeningPsgsArr[$i];	
	// 					}
	// 				}
	// 			}
	// 			else
	// 			{
	// 				$isContentExhaustedTeacher = array('isContentExhaustedTeacher'=>1);
	// 				$this->session->set_userdata($isContentExhaustedTeacher);
	// 			}

	// 		}
	// 		else
	// 		{
	// 			for ($i=0; $i<readingPsgCountConst; $i++) {
	// 				$psgDataArr[]=$passageDataArr[$i];
	// 			}
	// 		}
	// 	else :
			
	// 		$psgDataArr[] = $passageID;

	// 	endif;
		
	// 	$this->session->set_userdata('sessionPassages',$psgDataArr);
	// }

	public function getReadingPassageArr($userID,$attemptedPassageIDArr){
		/*$attemptedPassageID = $this->questionspage_model->getUserAttemptedPassage($userID);

		if($attemptedPassageID!=""):
			$attemptedPassageIDArr = explode(',', $attemptedPassageID);
		endif;*/
		$passageLevel=$this->session->userdata('passageLevel');
		$gradeLowerLimit=number_format($passageLevel, 2);
		$gradeHigherLimit=$gradeLowerLimit+gradeHigherLimitIncreaseConst;
		$readingPsgCondArr = array('q.passageStatus' => livePassageStatus, 'q.diffRating >=' => $gradeLowerLimit, 'q.diffRating <=' => $gradeHigherLimit);
		$this->dbEnglish->Select('p.passageID as passageID');
		$this->dbEnglish->from('passageMaster p');
		$this->dbEnglish->join('passageAdaptiveLogicParams q', 'p.passageID=q.passageID', 'inner');
		$this->dbEnglish->where($readingPsgCondArr);
		$this->dbEnglish->where_in('p.passageType',array('Textual','Illustrated'));
		$this->dbEnglish->where_not_in('p.passageID', $attemptedPassageIDArr);
		$this->dbEnglish->order_by('q.passageId','RANDOM');
		$readingPsgsSql = $this->dbEnglish->get();
		$readingPsgsDataArr = $readingPsgsSql->result_array();
		//echo $this->dbEnglish->last_query();exit;

		// foreach($readingPsgsDataArr as $key=>$value){
		// 		array_push($readingPsgsArr,$value['passageID']);
		// }
		// $readingPsgsArr=$this->setUncompletedPassages($readingPsgsArr,$attemptedPassageIDArr,readingContentTypeConst);
		/*for ($i=0; $i<readingPsgCountConst; $i++) {
			$psgDataArr[] = $readingPsgsArr[$i];
		}*/
		return $readingPsgsDataArr;
	}
	public function getConversatnPassageArr($userID,$attemptedPassageIDArr){
		$psgDataArr=array();
		$conversationLevel=$this->session->userdata('conversationLevel');
		$convesationMsLevel=$conversationLevel-gradeScallingConst;
		/*$attemptedPassageID = $this->questionspage_model->getUserAttemptedPassage($userID);
		if($attemptedPassageID!=""):
			$attemptedPassageIDArr = explode(',', $attemptedPassageID);
		endif;*/
		$listeningPsgCondArr = array('status' => livePassageStatus,'msLevel' => $convesationMsLevel, 'passageType' => 'Conversation');
		$this->dbEnglish->Select('passageID as passageID');
		$this->dbEnglish->from('passageMaster');
		$this->dbEnglish->where($listeningPsgCondArr);
		$this->dbEnglish->where_not_in('passageID', $attemptedPassageIDArr);
		$this->dbEnglish->order_by('passageID','RANDOM');
		$listeningpsgsSql = $this->dbEnglish->get();
		$listeningpsgsDataArr = $listeningpsgsSql->result_array();
		//print_r($listeningpsgsDataArr);exit;
		/*foreach($listeningpsgsDataArr as $key=>$value){
				array_push($listeningPsgsArr,$value['passageID']);
		}
		$listeningPsgsArr=$this->setUncompletedPassages($listeningPsgsArr,$attemptedPassageIDArr,listeningContentTypeConst,$convesationMsLevel);*/
		return 	$listeningpsgsDataArr;	
	}

	/**
	 * function description : Set uncompleted/half attempted passages as highest priority in reading/listening array
	 * param1 : psgArr = Unattempted Reading/listening passages array 
	 * param2 : attemptedPassageIDs = All questions passages array 
	 * param3 : contentType = Passage content type
	 * param4 : convesationMsLevel is the ms level of conversation
	 * param5 : isContentExhausted will be true if need to check for exhuastion passages
	 * @return : array, with unattempted passages as highest priority.  
	 * */

	public function getContentSpecificTotalAttempts($passageType){
		$userID=$this->session->userdata('userID');
		if($passageType=='speaking'):
			$this->dbEnglish->Select('count(qcode) as totalCount');
			$this->dbEnglish->from('speakingAttempts');
			$this->dbEnglish->where('userID',$userID);
			$query = $this->dbEnglish->get();

		else:
			//$passageType=$this->questionspage_model->getPassageType($this->session->userdata('refID'));
			//echo $passageType;
			$this->dbEnglish->Select('count(pa.passageID) as totalCount');
			$this->dbEnglish->Select('count(distinct pa.passageID) as distinctCount');
			$this->dbEnglish->from('passageAttempt pa');
			$this->dbEnglish->join('passageMaster pm', 'pm.passageID=pa.passageID', 'inner');
			$this->dbEnglish->where('pa.userID',$userID);
			$this->dbEnglish->where('pa.completed',2);
			//if($passageType='Illustrated' || $passageType='Textual'):
			if($passageType=='reading'):
				$this->dbEnglish->where_in('pm.passageType',array('Textual','Illustrated'));
			else:
				$this->dbEnglish->where('pm.passageType',$passageType);
			endif;
			$query = $this->dbEnglish->get();
		endif;
		$result=  $query->row_array();
		// echo $this->dbEnglish->last_query();
		// echo "total Attempts";
		return $result;
	}

	public function setUncompletedPassages($psgArr,$attemptedPassageIDs,$contentType,$convesationMsLevel='',$isContentExhausted=false){
		$allQuesNotCompletedPsgArr=$this->userUnCompletedPassages($psgArr,$attemptedPassageIDs,$contentType,$convesationMsLevel,$isContentExhausted);
		//print($allQuesNotCompletedPsgArr);
		//will remove the same passageID if found in psgArr
		$removeDuplicateSamePsg = array_diff($psgArr, $allQuesNotCompletedPsgArr);
			
		// resetting the keys after removal of same passageID from psgArr
		$removeDuplicateSamePsg=array_values($removeDuplicateSamePsg);
		$i=0;
		if(count($allQuesNotCompletedPsgArr)>0)
		{
			
			foreach($allQuesNotCompletedPsgArr as $passageID){
				$removeDuplicateSamePsg[$i]=$passageID;
				$i++;	
			}
			
		}
		
		// Remove refID passage set in usercurrentstatus[if completed=0] from the array 
		$refID=$this->session->userdata('refID');
		if (($key = array_search($refID, $removeDuplicateSamePsg)) !== false) {
		    unset($removeDuplicateSamePsg[$key]);
		}
		$removeDuplicateSamePsg=array_values($removeDuplicateSamePsg);		
		return $removeDuplicateSamePsg;
	}

	/**
	 * function description : returns uncompleted/half attempted passages
	 * param1 : psgArr = Unattempted Reading/listening passages array 
	 * param2 : attemptedPassageIDs = All questions passages array 
	 * param3 : contentType = Passage content type
	 * param4 : convesationMsLevel is the ms level of conversation
	 * param5 : isContentExhausted will be true if need to check for exhuastion passages
	 * @return : array, of unattempted passages.  
	 * */
	
	public function userUnCompletedPassages($psgArr,$attemptedPassageIDs,$contentType,$convesationMsLevel='',$isContentExhausted=false){
		
		$allQuesNotCompletedPsgArr=array();
		$psgCondArr = array('q.userID' => $this->user_id);		
		$this->dbEnglish->Select('distinct(p.passageID) as passageID');
		$this->dbEnglish->from('passageMaster p');
		$this->dbEnglish->join('passageAttempt q', 'p.passageID=q.passageID', 'inner');
		$this->dbEnglish->where($psgCondArr);
		if($contentType == readingContentTypeConst){
			$this->dbEnglish->where_in('p.passageType',array('Textual','Illustrated'));
		}
		else if($contentType == listeningContentTypeConst){
			$this->dbEnglish->where('p.msLevel',$convesationMsLevel);
			$this->dbEnglish->where_in('p.passageType','Conversation');
		}
		
		
		$this->dbEnglish->where_not_in('p.passageID', $attemptedPassageIDs);
		//exhaustion
		/*
		if($isContentExhausted)
			$this->dbEnglish->where('exScoringID IS NOT NULL');
		else	
			$this->dbEnglish->where('scoringID IS NOT NULL');*/

		$this->dbEnglish->order_by('q.lastmodified','asc');
		
		$query = $this->dbEnglish->get();
		$attemptedPassageIDArr = $query->result_array();		
		foreach($attemptedPassageIDArr as $value)
		{
			// To check whether half attempted passage is of same level or not,by checking it in the unattempted array
			//if($this->questionspage_model->lastCompletedPassageQuestionsPending(null,$userID,$value['passageID']))
			//{
				if($contentType == readingContentTypeConst){
					if (in_array($value['passageID'], $psgArr)) {
					   array_push($allQuesNotCompletedPsgArr,$value['passageID']);
					}
				}else{
					array_push($allQuesNotCompletedPsgArr,$value['passageID']);
				}
				
			//}	
		}

		return $allQuesNotCompletedPsgArr;
	}	

	/**
	 * function description : unset isContentExhaust flag to 0 if all three content exhaustion flags are 0 
	 * @return : none.  
	 * */
	
	public function unsetIsContentExhaustionFlag(){

		if(!$this->session->userdata('isListeningContExhaust') && !$this->session->userdata('isReadingContExhaust') && !$this->session->userdata('isFreeQuesContentExhaust')){
			$data=array('isContentExhaust'=>0);
			$this->dbEnglish->where('userID', $this->user_id);
			$this->dbEnglish->update('userCurrentStatus', $data);		
			$this->session->set_userdata($data); 
		}

	}
	
	/**
	 * function description : returns User Current reading/listening contenttype attempted count from userLevelAndAccuracyLog
	 * param1 : userID 
	 * param2 : contentType = Content type for which attempted count to check
	 * @return : array, count of attempts for the perticular content type.  
	 * */

	public function returnCurrentLevelPsgAttemptCnt($userID,$contentType){
		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userLevelAndAccuracyLog');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->order_by('scoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserLevelAndAccLogSql = $this->dbEnglish->get();
		$userLevelAndAccData=0;
		//print $this->dbEnglish->last_query();		
		
		if($getUserLevelAndAccLogSql->num_rows() > 0){
			$tmpRow = $getUserLevelAndAccLogSql->row();
			$userLevelAndAccData=$tmpRow->quesPsgAttemptCount;				
		}
		return $userLevelAndAccData;
		
	}


	/**
	 * function description : updates userLevelAndAccLog table after the passage attempt 
	 * param1 : userID 
	 * param2 : contentType = Content type for which attempted count to check
	 * param3 : passageID = Attempted passage after which userLevelAndAccLog need to be updated
	 * @return : none.  
	 * */




	public function updateUserLevelAndAccPsgLog($userID,$contentType,$passageID){
		//echo "xgsdgdf";exit;
		//$insert=0;
		if($contentType == "Textual" || $contentType == "Illustrated"){
			$contentType=readingContentTypeConst;
			$level=$this->session->userdata('passageLevel');
		}
		else if($contentType == "Conversation"){
			$contentType=listeningContentTypeConst;
			$level=$this->session->userdata('conversationLevel');
		}elseif($contentType == "Speaking" || $contentType == "conversation"){
			$contentType=speakingContentTypeConst;
			$childClass=$this->session->userdata('childClass');
			$level=$childClass-3;
		}
	
		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userLevelAndAccuracyLog');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->order_by('scoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserLevelAndAccLogSql = $this->dbEnglish->get();
		//print $this->dbEnglish->last_query();	exit;	
		$result=  $getUserLevelAndAccLogSql->row_array();
		//$userLevel=$this->getUserCurrentStatusLevel($userID,$contentType);
		if($level==$result['level']):
			if($getUserLevelAndAccLogSql->num_rows() > 0){
				$userLevelAndAccData = $getUserLevelAndAccLogSql->row();
				$this->updateQuesPsgAttemptCount($userLevelAndAccData,$passageID);	
			}
			else{
				//$insert=1;
				$this->insertUserLevelData( $userID,$contentType,$level);
				
			}
		else:
			$this->insertUserLevelData( $userID,$contentType,$level);
		endif;


	}
	public function insertUserLevelData( $userID,$contentType,$level){
		$data = array(
			'userID' => $userID,
			'contentType' => $contentType,
			'quesPsgAttemptCount' => 0,
			'accuracy' => 0,
			'level' => $level
		);

		$this->dbEnglish->insert('userLevelAndAccuracyLog', $data);

	}
	
	/**
	 * function description : updates quesPsgAttemptCount in the userLevelAndAccLog table 
	 * param1 : userLevelAndAccData = user content type row data which needs to be updated  
	 * param2 : passageID = passageID for which the table should be updated 
	 //greater than or equal to condition added for offline cases where count will be increased
	 * @return : none.  
	 * */

	public function updateQuesPsgAttemptCount($userLevelAndAccData,$passageID){
		

		if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER')//added by nivedita
		{
			$level = $this->session->userdata('passageLevel');

			$this->dbEnglish->Select('quesPsgAttemptCount');
			$this->dbEnglish->from('userLevelAndAccuracyLog');
			$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
			$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
			$this->dbEnglish->where('level', $level);
			$getPsgAttmpCountSql = $this->dbEnglish->get();
			$getPsgAttmpCountRow = $getPsgAttmpCountSql->row_array();

			$userLevelAndAccData->quesPsgAttemptCount = $getPsgAttmpCountRow['quesPsgAttemptCount']+1;

			$data=array('quesPsgAttemptCount'=> $userLevelAndAccData->quesPsgAttemptCount);

			$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
			$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
			$this->dbEnglish->where('level', $level);
		}
		else
		{
			$userLevelAndAccData->quesPsgAttemptCount=$userLevelAndAccData->quesPsgAttemptCount+1;
			$data=array('quesPsgAttemptCount'=> $userLevelAndAccData->quesPsgAttemptCount);
			$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
			$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
			$this->dbEnglish->where('scoringID', $userLevelAndAccData->scoringID);	
		}

		$this->dbEnglish->update('userLevelAndAccuracyLog', $data);
		
		//exhaustion-level changes
		/*if($this->category == 'STUDENT') //added by nivedita for DMS
		{
			if($this->dbEnglish->affected_rows() == 1){
				// Check accuracy and change level if the attempted count has reached to the change level count for reading/listening
				if(($userLevelAndAccData->contentType==readingContentTypeConst && $userLevelAndAccData->quesPsgAttemptCount >= readingPsgChangeLevelCount))
					$this->checkAccuracyAndChangeLevel($userLevelAndAccData,$passageID);
				else if($userLevelAndAccData->contentType==listeningContentTypeConst && $userLevelAndAccData->quesPsgAttemptCount >= listeningPsgChangeLevelCount) 		
					$this->checkAccuracyAndChangeLevel($userLevelAndAccData,$passageID);
			}
		}*/
	} 
	
	/**
	 * function description : calculate accuracy and change level for the given content type[reading/listening]
	 * param1 : userLevelAndAccData = content type data for which the accuracy need to calculated   
	 * param2 : passageID = passageID for which the table should be updated 
	 * @return : none.  
	 * */
	//level-change
	/*public function checkAccuracyAndChangeLevel($userLevelAndAccData,$passageID)
	{
		$totalPsgAccuracy=0;		
		$this->dbEnglish->Select('p.passageID as passageID,q.accuracy as Accuracy,q.level as userLevel');
		$this->dbEnglish->from('passageAttempt p');
		$this->dbEnglish->join('userLevelAndAccuracyLog q', 'p.scoringID=q.scoringID', 'inner');
		$this->dbEnglish->where('q.scoringID',$userLevelAndAccData->scoringID);
		$readingPsgsSql = $this->dbEnglish->get();
		$readingPsgsDataArr = $readingPsgsSql->result_array();	
		
		foreach ($readingPsgsDataArr as $row)
		{
			// Check the accuracy for the every passage that falls into the scoringID for which accuracy need to be calculated 

			$getPsgAccuracySql=$this->dbEnglish->query("select q.passageID,sum(q.quesAccuracy)/count(q.qcode) as psgAccuracy from (select count(qcode) as totalAttmpted,qcode,sum(if(correct>0.5,1,0)) as correct,sum(if(correct>0.5,1,0))/count(qcode)*100 as quesAccuracy,passageID from ".$this->questionAttemptClassTbl." where scoringID=".$userLevelAndAccData->scoringID." group by qcode)q where q.passageID=".$row['passageID']);
			
			if($getPsgAccuracySql->num_rows() > 0)
			{	
				$psgAccuracyArr=$getPsgAccuracySql->row();
				if($psgAccuracyArr->psgAccuracy != NULL)
					$totalPsgAccuracy = $totalPsgAccuracy+$psgAccuracyArr->psgAccuracy;
			}	
		}	
		
		if($userLevelAndAccData->contentType == readingContentTypeConst)
			$avgPsgAccuracy=$totalPsgAccuracy/readingPsgChangeLevelCount;
		else
			$avgPsgAccuracy=$totalPsgAccuracy/listeningPsgChangeLevelCount;

		$data=array('accuracy'=> round($avgPsgAccuracy,2));
		$this->dbEnglish->where('scoringID', $userLevelAndAccData->scoringID);
		$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
		$this->dbEnglish->update('userLevelAndAccuracyLog', $data);
		$userLevel=$readingPsgsDataArr[0]['userLevel']; 
		
		if($userLevelAndAccData->contentType == readingContentTypeConst)
		{	
			// if acc is < 40 and level is 4 then user should remain in same level,otherwise it should be degraded 
			if($avgPsgAccuracy <= 40 && $userLevel!=4){
				$userLevel=$userLevel-readingPsgLevelConst;	
			}
			// if acc is > 80 and level is 9 then user should remain in same level,otherwise it should be degraded 
			else if($avgPsgAccuracy >= 80 && $userLevel!=9)
				$userLevel=$userLevel+readingPsgLevelConst;	
		}else
		{
			// if acc is < 40 and level is 4 then user should remain in same level,otherwise it should be degraded 
			if($avgPsgAccuracy <= 40 && $userLevel!=4){
				$userLevel=$userLevel-listeningPsgLevelConst;	
			}
			// if acc is > 80 and level is 9 then user should remain in same level,otherwise it should be degraded 
			else if($avgPsgAccuracy >= 80 && $userLevel!=9)
				$userLevel=$userLevel+listeningPsgLevelConst;	
		}	
		
		
		$data = array(
				'userID' => $userLevelAndAccData->userID,
				'contentType' => $userLevelAndAccData->contentType,
				'accuracy' => 0,
				'level' => $userLevel
			);

		$this->dbEnglish->insert('userLevelAndAccuracyLog', $data);
		if($userLevelAndAccData->contentType == readingContentTypeConst)
			$data=array('passageLevel'=>$userLevel);
		else
			$data=array('conversationLevel'=>$userLevel);
		
		$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
		$this->dbEnglish->update('userCurrentStatus', $data);
		$this->session->set_userdata($data); 
		$this->session->unset_userdata('sessionPassages');
		$this->setNextSessionPassages($userLevelAndAccData->userID,$passageID);	
	}*/
	
	/* -------------------- Exhaustion Logic related function starts -------------------- */
	
	/**
	 * function description : sets the exhaustion logic for the given content type[reading/listening]
	 * param1 : contentType = content type data for which the accuracy need to calculated   
	 * param2 : level = level for which the exhaustion logic need to be set. 
	 * @return : array, of exhaustion passages if thery are set other wise false if level is change of adaptive logic   
	 * */
	
	public function setExhaustionLogic($contentType,$Level){
		if($contentType == readingContentTypeConst && !$this->session->userdata('isReadingContExhaust'))
			$this->sendMail($this->readingPsgContExhaustMailMessage);
		else if ($contentType == listeningContentTypeConst && !$this->session->userdata('isListeningContExhaust'))
			$this->sendMail($this->listeningPsgContExhaustMailMessage);

		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userexhaustionlogiclog');
		$this->dbEnglish->where('userID',$this->user_id);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->order_by('exScoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserExhaustionLogSql = $this->dbEnglish->get();
		//print $this->dbEnglish->last_query();
		// If above query returns rows means user is already in exhaustion and adaptive level need not to be passed.

		if($getUserExhaustionLogSql->num_rows() > 0){	
			$userExhaustionData = $getUserExhaustionLogSql->row();			
			if($contentType == readingContentTypeConst){
				$adaptiveLevel=$this->session->userdata('passageLevel');
			}
			else{
				$adaptiveLevel=$this->session->userdata('conversationLevel');
			}
			
			//Start shivam
			
			//Below code will take care 2 scenereos- 1) No passages are left to show. User already have an entry in the exhaustionlogiclog table for which no passages are present. 2) Latest entry in the exhaustion logic log will have attempted psgs > psgstoshow and accuracy is already calculated.

			if (($userExhaustionData->psgsToShow == 0) or ($userExhaustionData->psgsToShow <= $userExhaustionData->psgAttemptCount and $userExhaustionData->accuracy > 0) ){
				//Call this function to get the exhaustionlogiclog entry in which no content is present for passages.
				
				list($newlevel,$newpsgstoshow )=$this->getmslevel($this->user_id, $contentType); 

				$this->updateOrInsertAfterNoPsgsleftinExhaustion($this->user_id, $contentType, $newlevel, $newpsgstoshow);

				//Set the data in the variables.
				$userExhaustionData->level = $newlevel;
				$userExhaustionData->psgtoshow = $newpsgstoshow;

			}
			//End shivam

			// Check if the passages to get of exhaustion are of same level as of adaptive logic level
			if($userExhaustionData->level == $adaptiveLevel)
				$exhaustionPsgsArr=$this->setExhaustionPsgLogic($contentType,$userExhaustionData->level,true);
			else				
				$exhaustionPsgsArr=$this->setExhaustionPsgLogic($contentType,$userExhaustionData->level);
		}
		else{
			$exhaustionPsgsArr=$this->setExhaustionPsgLogic($contentType,$Level,true);
		}
		
		if($exhaustionPsgsArr){
			// Update RefID with the exhaustion passage id when user goes in exhaustion.  
			$currentContentType=currContentTypePsgConst;
			$passageID=$exhaustionPsgsArr[0];
			$data = array('currentContentType' => $currentContentType,'refID' => $passageID,'isContentExhaust'=>1,'completed'=>0);
			$this->session->set_userdata($data);
			$this->dbEnglish->where('userID', $this->user_id);
			$this->dbEnglish->update('userCurrentStatus',$data);
			
			// Remove newly set refID of passage set in usercurrentstatus[if completed=0] from the exhaustion array 

			$refID=$this->session->userdata('refID');
			if (($key = array_search($refID, $exhaustionPsgsArr)) !== false) {
		   		unset($exhaustionPsgsArr[$key]);
			}
			$exhaustionPsgsArr=array_values($exhaustionPsgsArr);	
			//print_r($exhaustionPsgsArr);			
		}
			
			//print $this->dbEnglish->last_query();
		
		
		return $exhaustionPsgsArr;
	}
	
	/**
	 * function description : sets the exhaustion passage logic for the given content type[reading/listening]
	 * param1 : contentType = content type data for which the accuracy need to calculated   
	 * param2 : level = level for which the exhaustion logic need to be set. 
	 * param3 : exhaustLevelIsOfAdaptLogic = true : exhaustion level is of adaptive logic level , else : false  
	 * @return : array, of exhaustion passages if thery are set other wise false if level is change of adaptive logic.  
	 * */

	public function setExhaustionPsgLogic($contentType,$level,$exhaustLevelIsOfAdaptLogic=false){
		$calAvgAccForExhaustionLogicArr=$this->calAvgAccForExhaustionLogic($contentType,$level,$exhaustLevelIsOfAdaptLogic);
		$totalPsgAccuracy=$calAvgAccForExhaustionLogicArr[0];
		$exhaustionPsgsArr=$calAvgAccForExhaustionLogicArr[1];
		if($calAvgAccForExhaustionLogicArr[2] != ""){		
			$attemptedExhaustionPsgs=$calAvgAccForExhaustionLogicArr[2];		
		}
		
		//  if calculated totalPsgAccuracy of the adpative level passages are > 90% then upgrade level of adaptive logic 
		if($totalPsgAccuracy > exhaustionGradeIncrementPerConst){
			if($contentType == readingContentTypeConst){
				$level=$this->session->userdata('passageLevel');	
				$level = $level+readingPsgLevelConst;
				$data=array('passageLevel'=>$level);
				$this->sendmail($this->readingPsgMoveNextLevelMessage);
			}else{
				$level=$this->session->userdata('conversationLevel');
				$level = $level+listeningPsgLevelConst;	
				$data=array('conversationLevel'=>$level);
				$this->sendmail($this->listeningPsgMoveNextLevelMessage);
			}
			
			$this->session->set_userdata($data);
		 	$this->dbEnglish->where('userID', $this->user_id);
			$this->dbEnglish->update('userCurrentStatus', $data);
			return false;
		}else{
		// else return the exhaustion passsages to set the exhaustion logic 
			
			$data=array();
			uasort($exhaustionPsgsArr,function ($a,$b){if ($a == $b){return 0;}return ($a < $b) ? -1 : 1;});
			$exhaustionPsgsArr=array_slice($exhaustionPsgsArr,0,10, true);
			$exhaustionPsgsArr=array_keys($exhaustionPsgsArr);
			
			//print_r($exhaustionPsgsArr);
			//print "HHHHH";
			//print_r($attemptedExhaustionPsgs);

			if($contentType == readingContentTypeConst){
				$exhaustionPsgsArr=$this->setUncompletedPassages($exhaustionPsgsArr,$attemptedExhaustionPsgs,readingContentTypeConst,'',true);
				$data['isReadingContExhaust']=1;
			}
			else{
				$exhaustionPsgsArr=$this->setUncompletedPassages($exhaustionPsgsArr,$attemptedExhaustionPsgs,listeningContentTypeConst,$level,true);
				$data['isListeningContExhaust']=1;	
			}
			
			/*if($contentType == readingContentTypeConst)
				$data['isReadingContExhaust']=1; 
			else
				$data['isListeningContExhaust']=1;	
		 	*/
		 	$this->session->set_userdata($data);
		 	$this->dbEnglish->where('userID', $this->user_id);
			$this->dbEnglish->update('userCurrentStatus', $data);
			return $exhaustionPsgsArr;
		}
		
	}
	
	/**
	 * function description : calculate accuracy of adative passages if required and return exhuastion passages with accuracy for the given content type[reading/listening]
	 * param1 : contentType = content type data for which the accuracy need to calculated   
	 * param2 : level = level for which the exhaustion logic need to be set. 
	 * param3 : exhaustLevelIsOfAdaptLogic = true : exhaustion level is of adaptive logic level , else : false  
	 * @return : array, of exhaustion passages and attemptedPassages in exhaustion.  
	 * */
	
	public function calAvgAccForExhaustionLogic($contentType,$level,$exhaustLevelIsOfAdaptLogic){
		$accuracyTotal=0;	
		$psgsAccuracyArr=array();
		
		$this->dbEnglish->Select('scoringID,accuracy,level');
		$this->dbEnglish->from('userLevelAndAccuracyLog');
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->where('userID',$this->user_id);
		$this->dbEnglish->where('level',$level);
		$getUserLevelLogsSql = $this->dbEnglish->get();
		//print $this->dbEnglish->last_query();
		
		// if exhuastion logic level is same as adaptive level then repeat adaptive logic attempted passage logic   
		/*print $getUserLevelLogsSql->num_rows();
		print "EX";
		print $exhaustLevelIsOfAdaptLogic;	
		print "AAA";*/
		
		if($getUserLevelLogsSql->num_rows() > 0 && $exhaustLevelIsOfAdaptLogic)
		{
			//print "1";
			$userLevelLogsArr = $getUserLevelLogsSql->result_array();

			$attemptedExhaustionPsgs="";
			$attemptedExhaustionPsgs = $this->getAttemptedExhaustPsgs($contentType);
			
			// if($attemptedExhaustionPsgs!="")
			// 	$attemptedExhaustionPsgsArr = explode(',', $attemptedExhaustionPsgs);
			
			$count=0;
			foreach($userLevelLogsArr as $logData){
					$totalLogPsgAccuracy=0;
					$allQuesUnCompletedPsgArr=array();
					$this->dbEnglish->Select('p.passageID as passageID,q.accuracy as Accuracy,q.level as userLevel');
					$this->dbEnglish->from('passageAttempt p');
					$this->dbEnglish->join('userLevelAndAccuracyLog q', 'p.scoringID=q.scoringID', 'inner');
					$this->dbEnglish->where('q.scoringID',$logData['scoringID']);
					$this->dbEnglish->where('p.completed','2');    // want completed passage 
					//$this->dbEnglish->where_in('p.completed',array('1','2'));
					$this->dbEnglish->order_by("p.lastModified", "asc"); 
					$getLogPsgsSql = $this->dbEnglish->get();
					
					$logPsgsDataArr = $getLogPsgsSql->result_array();
					/*foreach($logPsgsDataArr as $key=>$value)
					{
						if($this->questionspage_model->lastCompletedPassageQuestionsPending(null,$this->user_id,$value['passageID'],false))
							array_push($allQuesUnCompletedPsgArr,$value['passageID']);	
					}*/    // Of no use Commented By Aditya
			
					foreach ($logPsgsDataArr as $logPsgsDataRow)
					{
						// if passage is in uncompleted array , then do not calculate that passage accuracy and contiune
						/*if(in_array($logPsgsDataRow['passageID'], $allQuesUnCompletedPsgArr))
							continue;*/  // Of no use Commented By Aditya
							
						$getPsgAccuracySql=$this->dbEnglish->query("select q.passageID,sum(q.quesAccuracy)/count(q.qcode) as psgAccuracy from (select count(qcode) as totalAttmpted,qcode,sum(if(correct>0.5,1,0)) as correct,sum(if(correct>0.5,1,0))/count(qcode)*100 as quesAccuracy,passageID from ".$this->questionAttemptClassTbl." where scoringID=".$logData['scoringID']." group by qcode)q where q.passageID=".$logPsgsDataRow['passageID']);
						//print $this->dbEnglish->last_query();
						if($getPsgAccuracySql->num_rows() > 0)
						{
							$psgAccuracyArr=$getPsgAccuracySql->row();
							if($psgAccuracyArr->psgAccuracy != NULL)
								$totalLogPsgAccuracy = $totalLogPsgAccuracy+$psgAccuracyArr->psgAccuracy;
							//print $totalLogPsgAccuracy;
							//print "<br/>";
							//$psgsAccuracyArr[$logPsgsDataRow['passageID']]=$psgAccuracyArr->psgAccuracy;
							$psgsAccuracyArr[$logPsgsDataRow['passageID']]=array("acc"=>$psgAccuracyArr->psgAccuracy,"flow"=>$count);
							$count++;
						}			
					}

					
					//print $totalLogPsgAccuracy;

					$avgLogAccuracy=$totalLogPsgAccuracy/$getLogPsgsSql->num_rows();
					
					$avgLogAccuracy = round($avgLogAccuracy,2);
					
					$accuracyTotal += $avgLogAccuracy;
			}

			// remove attempted exhaustion passages from the adative logic passages repeat array
			if($attemptedExhaustionPsgs!=""){
				foreach($attemptedExhaustionPsgs as $key=>$val){
						unset($psgsAccuracyArr[$val]);
				}
			}

			/*if(count($psgsAccuracyArr) < 10){
				$psgsAccuracyArr=$this->getUnattemptedExhaustionLogic($contentType,$level);	
				$accuracyTotal = 0;
			}*/
			//return array($accuracyTotal/$getUserLevelLogsSql->num_rows(),$psgsAccuracyArr);
			return array($accuracyTotal/$getUserLevelLogsSql->num_rows(),$psgsAccuracyArr,$attemptedExhaustionPsgs);
		}	
		// if exhaustion logic is not of adaptive level then get passages of unattempted passages of exhuastion level
		else{
			$exhaustionPsgsDetArr=$this->getUnattemptedExhaustionLogic($contentType,$level);
			$exhaustionPsgsArr=$exhaustionPsgsDetArr[0];
			$attemptedExhaustionPsgs=$exhaustionPsgsDetArr[1];
			return array(0,$exhaustionPsgsArr,$attemptedExhaustionPsgs);

		}
	}
	
	/**
	 * function description : get unattempted passages for exhaustion level that is not of adaptive level as per given content type[reading/listening]
	 * param1 : contentType = content type data for which the accuracy need to calculated   
	 * param2 : level = level for which the exhaustion logic need to be set. 
	 * param3 : getTotalLevelPsgs = true : to fetch all the exhaustion level passages , else : false  
	 * @return : array, of unattempted exhaustion passages and attemptedPassages in exhaustion.  
	 * */

	public function getUnattemptedExhaustionLogic($contentType,$level,$getTotalLevelPsgs=false,$countAllPsg=''){
		
		$psgDataArr=array();
		$readingPsgsArr=array();
		$listeningPsgsArr=array();

		//$attemptedPassageID="";
		//$attemptedPassageID = $this->questionspage_model->getUserAttemptedPassage($this->user_id);
		$attemptedPassageIDArr="";
		if($countAllPsg=="countAllPsgAtmpt")
		{
			$attemptedPassageIDArr = $this->questionspage_model->getUserAttemptedPassage($this->user_id);
		}
		else
		{
			$attemptedPassageIDArr = $this->questionspage_model->getUserAttemptedPassage($this->user_id,true);
		}			

		if($attemptedPassageIDArr!="")
			$attemptedPassageIDArr = explode(',', $attemptedPassageIDArr);

		//print "AttemptedPassagesArr";
		//print_r($attemptedPassageIDArr);
		if($contentType == readingContentTypeConst)
		{
			
			$gradeLowerLimit=number_format($level, 2);
			$gradeHigherLimit=$gradeLowerLimit+gradeHigherLimitIncreaseConst;

			$readingPsgCondArr = array('q.passageStatus' => livePassageStatus, 'q.diffRating >=' => $gradeLowerLimit, 'q.diffRating <=' => $gradeHigherLimit);
			
			$this->dbEnglish->Select('p.passageID as passageID');
			$this->dbEnglish->from('passageMaster p');
			$this->dbEnglish->join('passageAdaptiveLogicParams q', 'p.passageID=q.passageID', 'inner');
			$this->dbEnglish->where($readingPsgCondArr);
			$this->dbEnglish->where_in('p.passageType',array('Textual','Illustrated'));
			$this->dbEnglish->where_not_in('p.passageID', $attemptedPassageIDArr);
			$this->dbEnglish->order_by('q.passageId','RANDOM');
			if(!$getTotalLevelPsgs)
				$this->dbEnglish->limit(10);
			$readingPsgsSql = $this->dbEnglish->get();
			//print $this->dbEnglish->last_query();
			$readingPsgsDataArr = $readingPsgsSql->result_array();

			foreach($readingPsgsDataArr as $key=>$value)
			{
				array_push($readingPsgsArr,$value['passageID']);
			}
				
			//
			if(!$getTotalLevelPsgs){
				foreach($readingPsgsArr as $value)
				{
					$psgDataArr[$value]=0;
				}
				
			}else{
				$psgDataArr['totalCount']=$readingPsgsSql->num_rows();
			}
		}else
		{
			$convesationMsLevel=$level-gradeScallingConst; 
			$listeningPsgCondArr = array('status' => livePassageStatus,'msLevel' => $convesationMsLevel, 'passageType' => 'Conversation');
		
			$this->dbEnglish->Select('passageID as passageID');
			$this->dbEnglish->from('passageMaster');
			$this->dbEnglish->where($listeningPsgCondArr);
			$this->dbEnglish->where_not_in('passageID', $attemptedPassageIDArr);
			$this->dbEnglish->order_by('passageID','RANDOM');
			if(!$getTotalLevelPsgs)
				$this->dbEnglish->limit(10);
			$listeningpsgsSql = $this->dbEnglish->get();
			//print $this->dbEnglish->last_query();
			$listeningpsgsDataArr = $listeningpsgsSql->result_array();	
			
			//$readingPsgsSql->num_rows() != 20
			//$listeningpsgsSql->num_rows() != 30
			foreach($listeningpsgsDataArr as $key=>$value)
			{
				array_push($listeningPsgsArr,$value['passageID']);
			}
			
			if(!$getTotalLevelPsgs){
				foreach($listeningPsgsArr as $key=>$value)
				{
					$psgDataArr[$value]=0;
				}
			}else{
				$psgDataArr['totalCount']=$listeningpsgsSql->num_rows();
			}
		}
		return array($psgDataArr,$attemptedPassageIDArr);
	}


	/* --------------------------- Update/Insert Exhaustion table functions ---------------------------- */

	/**
	 * function description : updates user exhaustion passage log table as per given content type[reading/listening]
	 * param1 : userID
	 * param2 : contentType = content type data for which the accuracy need to calculated   
	 * param3 : passageID = attempted exhuastion passageID for which userexhaustionlogiclog table needs to be updated. 
	 * @return : none.  
	 * */
	 
	public function updateUserExhaustPsgLog($userID,$contentType,$passageID){
		if($contentType == "Textual" || $contentType == "Illustrated"){
			$contentType=readingContentTypeConst;
			$level=$this->session->userdata('passageLevel');
		}
		else if($contentType == "Conversation"){
			$contentType=listeningContentTypeConst;
			$level=$this->session->userdata('conversationLevel');
		}

		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userexhaustionlogiclog');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->order_by('exScoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserExhaustionLogSql = $this->dbEnglish->get();
		
		if($getUserExhaustionLogSql->num_rows() > 0){
			$userExhaustionData = $getUserExhaustionLogSql->row();
			//print_r($userExhaustionData);
			$this->updateExhaustPsgAttemptCount($userExhaustionData,$passageID,$contentType);
			//print "AAAA";
		}
		else{
			if($contentType == readingContentTypeConst){
				$level=$this->session->userdata('passageLevel');
				$exhaustionPsgLimitPer = exhaustionReadingPsgSetLimitPer;
			}else{
				$level=$this->session->userdata('conversationLevel');
				$exhaustionPsgLimitPer = exhaustionListeningPsgSetLimitper;
			}

			// logic of setting half passages of the total passages attempted in adaptive logic for same level of exhaustion and adaptive.
			$attemptedLevelPsgsSql=$this->dbEnglish->query('select p.passageID as passageID,q.level as userLevel,q.scoringID as scoringID,p.lastmodified from passageAttempt p,userLevelAndAccuracyLog q where p.scoringID=q.scoringID and q.userID='.$this->user_id.' and q.contentType = "'.$contentType.'" and q.level='.$level.' and p.completed = "2" order by p.lastModified desc');
			$attemptedLevelPsgsCount = $attemptedLevelPsgsSql->num_rows();
			
			if($attemptedLevelPsgsCount > 0){
				$exhaustionPsgsCount=($exhaustionPsgLimitPer/100)*$attemptedLevelPsgsCount;
				$exhaustionPsgsCount = floor($exhaustionPsgsCount);	
			}

			//Shivam- Start

			//Check if exhaustionpsgscount is 0 then change the level
			if($exhaustionPsgsCount == 0 ){
				list($level, $exhaustionPsgsCount) = $this->getmslevel($this->user_id,$contentType);
			}
			//Shivam- End

			$data = array(
				'userID' => $this->user_id,
				'contentType' => $contentType,
				'accuracy' => 0,
				'psgsToShow' => $exhaustionPsgsCount,
				'level' => $level
			);

			$this->dbEnglish->insert('userexhaustionlogiclog', $data); 	
		}
	}

	/**
	 * function description : updates user exhaustion passage log table as per given content type[reading/listening]
	 * param1 : userExhaustionData = user exhaustion log table row data for which count should be updated 
	 * param2 : contentType = content type data for which the accuracy need to calculated   
	 * param3 : passageID = attempted exhuastion passageID for which userexhaustionlogiclog table needs to be updated. 
	 	//greater than or equal to condition added for offline cases where count will be increased
	 * @return : none.  
	 * */
	
	public function updateExhaustPsgAttemptCount($userExhaustionData,$passageID,$contentType){
		$userExhaustionData->psgAttemptCount=$userExhaustionData->psgAttemptCount+1;
		$data=array('psgAttemptCount'=> $userExhaustionData->psgAttemptCount);
		$this->dbEnglish->where('userID', $userExhaustionData->userID);
		$this->dbEnglish->where('contentType', $userExhaustionData->contentType);
		$this->dbEnglish->where('exScoringID', $userExhaustionData->exScoringID);

		$this->dbEnglish->update('userexhaustionlogiclog', $data);
		//print $this->dbEnglish->last_query();
		if($this->dbEnglish->affected_rows() == 1){
			if($userExhaustionData->psgAttemptCount >= $userExhaustionData->psgsToShow){
				$this->calExhaustPsgAccAndChangeLevelandSetnextSession($userExhaustionData,$passageID);

				/*$data=array('isContentExhaust'=>0);
				if($contentType == readingContentTypeConst){
					$level = $level+readingPsgLevelConst;
					$data['isReadingContExhaust']=0;
					$data['passageLevel']=$level;
				}
				else{
					$level = $level+listeningPsgLevelConst;	
					$data['isListeningContExhaust']=0;
					$data['conversationLevel']=$level;	
				}
			 	$this->session->set_userdata($data);
			 	$this->dbEnglish->where('userID', $userExhaustionData->userID);
				$this->dbEnglish->update('userCurrentStatus', $data);*/
			}
		}
	}
	
	/**
	 * function description : calculates exhaustion passage accuracy and changes exhaustion level as per given content type[reading/listening]
	 * param1 : userExhaustionData = exhauastion row data which need to be updated 
	 * param2 : passageID = attempted exhauastion passageID for which userexhaustionlogiclog table needs to be updated. 
	 * @return : none.  
	 * */

	public function calExhaustPsgAccAndChangeLevelandSetnextSession($userExhaustionData,$passageID=null){

		//Below function will change the level after calculating the accuracy.
		$userExhaustionData = $this->calExhaustPsgAccAndChangeLevel($userExhaustionData); 

		//set the level
		$userLevel = $userExhaustionData->level;	
		
		$this->session->unset_userdata('sessionPassages');
		$this->setNextSessionPassages($userExhaustionData->userID,$passageID,$userLevel,$userExhaustionData->contentType);		
		$sessionPsg=$this->session->userdata('sessionPassages');
		$refID=$sessionPsg[0];
		//print_r($this->session->userdata('sessionPassages'));
		$data = array('currentContentType' => currContentTypePsgConst,'refID' => $refID,'isContentExhaust'=>1);
		$this->session->set_userdata($data);
		$this->dbEnglish->where('userID', $this->user_id);
		$this->dbEnglish->update('userCurrentStatus',$data);
	}

	/**
	 * function description : returns attempted exhaustion passages as per given content type[reading/listening]
	 * param1 : contentType = content type data for which the accuracy need to calculated   
	 * @return : return attempted exhaustion passages if array count is greater than zero otherwise blank string.  
	 * */
	 
	public function getAttemptedExhaustPsgs($contentType){
		$allQuesCompletedPsgArr=array();
		$this->dbEnglish->Select('distinct(p.passageID) as passageID');
		$this->dbEnglish->from('passageAttempt p');
		$this->dbEnglish->join('userexhaustionlogiclog q', 'p.exScoringID=q.exScoringID', 'inner');
		$this->dbEnglish->where('q.userID',$this->user_id);
		$this->dbEnglish->where('q.contentType',$contentType);
		$this->dbEnglish->where('p.completed','2'); 
		$this->dbEnglish->order_by('p.lastmodified','asc');
		$query = $this->dbEnglish->get();
		$attemptedPassageIDArr = $query->result_array();

		foreach($attemptedPassageIDArr as $key=>$value)
		{
			//if(!$this->questionspage_model->lastCompletedPassageQuestionsPending(null,$this->user_id,$value['passageID'])){
				array_push($allQuesCompletedPsgArr,$value['passageID']);	
			//}
		}	

		//if($this->session->userdata('currentContentType')==currContentTypePsgConst && $this->session->userdata('completed')==0)
				//array_push($allQuesCompletedPsgArr,$this->session->userdata('refID'));	
				
		if(count($allQuesCompletedPsgArr)>0)
			return $allQuesCompletedPsgArr;
		else 
			return "";
		
	} 
/*
	Added by Shivam - 03/17/2018
		 * function description : This function will be called for two purposes - 
	 						1) Getting the newmslevel and no of unattempted free questions which needs to be set in the userexhaustionlogiclog table.
	 						2) If param2 value is passed as true then it will also call another function to update/insert the userexhaustionlogiclog table.
		 * param1   userid
		 * param2	passagetype
		 * @return  array containing the newlevel and psgs to attempt.
		 * 
*/
	public function getmslevel($userID, $contentType){
		//echo "content type".$contentType ; die("getmslevel");
		//Get the unattempted questions and mslevel through the query. Subquerry is used so that it will eliminate the truncation of comma separated passage id's. 
		if($contentType == readingContentTypeConst ){

			//For reading querry
			$this->dbEnglish->Select('count(distinct(a.passageid)) as unattempted_psgs, floor(a.diffrating) as newmslevel ');
			$this->dbEnglish->from('passageAdaptiveLogicParams a');
			$this->dbEnglish->join("passageMaster b", 'a.passageid = b.passageID', 'inner');

			$this->dbEnglish->join("passageAttempt c", 'a.passageid = c.passageID and c.userID ='.$userID, 'left outer');
			$this->dbEnglish->where('b.status',7);
			$this->dbEnglish->where('b.passagetype <>\'conversation\'');
			$this->dbEnglish->where('c.passageid is null');
			$this->dbEnglish->where('a.diffrating >=4');
			$this->dbEnglish->group_by('newmslevel');
			$this->dbEnglish->order_by("unattempted_psgs desc");
			$this->dbEnglish->limit(1);

		} else{

			//For listening query.
			$this->dbEnglish->Select('count(distinct(a.passageid)) as unattempted_psgs, a.mslevel as newmslevel ');
			$this->dbEnglish->from('passageMaster a');
			$this->dbEnglish->join("passageAttempt b", 'a.passageid = b.passageID and b.userID ='.$userID, 'left outer');
			$this->dbEnglish->where('a.status',7);
			$this->dbEnglish->where('a.passagetype','conversation');
			$this->dbEnglish->where('b.passageid is null');
			$this->dbEnglish->group_by('newmslevel');
			$this->dbEnglish->order_by("unattempted_psgs desc");
			$this->dbEnglish->limit(1);
		}

		$getmaxunattempt = $this->dbEnglish->get();

		$maxquesPending = 0;	
		$maxquesPending=$getmaxunattempt->num_rows();

		//Get the new ms level which we need to set in the userexhaustionlogic log.
		if($maxquesPending>0){

			$row = $getmaxunattempt->row();
			$newlevel = $row->newmslevel;
			$newpsgstoshow = $row->unattempted_psgs;	

			//Change the newlevel as per our mindspark logic.
			if($contentType == listeningContentTypeConst)
				$newlevel = $newlevel + 3;
			
		}
		else{
			//In worst scenereo if no row is present then give alert to user.
			echo '<script language="javascript">';
			echo 'alert("No Passages are available.")';
			echo '</script>';
			return;
		}

		//By default return the array which contains the newlevel and no of unattempted questions to show. 
		
		return array($newlevel, $newpsgstoshow);
		
	}

/*
	Added by Shivam - 03/17/2018
		 * function description : This function will insert/update the userexhaustionlogiclog table for
		 						 the mslevel for which maximum no of questions are available to attempt.
		 * param1   userid
		 * param2	contentType
		 * param3 	newlevel- newlevel which needs to be set.
		 * param4	psgtoshow- unattempted passage count which need to be set.
		 * @return  none.
		 * 
*/
	public function updateOrInsertAfterNoPsgsleftinExhaustion($userID, $contentType, $newlevel,$psgtoshow){

		//Check if row exists or not in the userexhaustionlogiclog table.
		$this->dbEnglish->select('exScoringID');
		$this->dbEnglish->from('userexhaustionlogiclog');
		$this->dbEnglish->where('psgsToShow',0);
		$this->dbEnglish->where('accuracy',0);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->where('userid',$userID);
		$this->dbEnglish->order_by('exScoringID','desc');
		$this->dbEnglish->limit(1);
		$checkifexists = $this->dbEnglish->get();

		$rowcount = 0;	
		$rowcount=$checkifexists->num_rows();

		//Get the row in variable.
		$row = $checkifexists->row();

		If($rowcount == 1){
			//If row is present then update the same row.
			$data=array('level'=> $newlevel,'psgsToShow'=>$psgtoshow );
			$this->dbEnglish->where('exScoringID',$row->exScoringID);
			$this->dbEnglish->update('userexhaustionlogiclog', $data);

		}else{

			//Insert row in the table if it is not present. 
			$data = array(
					'userID' => $userID,
					'contentType' => $contentType,
					'accuracy' => 0,
					'level' => $newlevel,
					'psgsToShow' => $psgtoshow
				);
			$this->dbEnglish->insert('userexhaustionlogiclog', $data);

		}

	}


/*
	Added by Shivam - 03/17/2018
		 * function description : This function will update the userexhaustionlogiclog table and will set the psgtoshow and psgattempt count same.
		 * param1   userExhaustionData
		 * param2	
		 * @return  none.
		 * 
*/
	public function updateUserExhaustionlogiclog($userExhaustionData){

		//Update the UserExhaustionlogiclog table. Make attempted passage and psgstoshow same.
		$data=array('psgsToShow'=>$userExhaustionData->psgAttemptCount);
		$this->dbEnglish->where('exScoringID',$userExhaustionData->exScoringID);
		$this->dbEnglish->update('userexhaustionlogiclog', $data);

	}

	/**
	 * function description : calculates exhaustion passage accuracy 
	 * param1 : userExhaustionData = exhauastion row data which need to be updated 
	 * @return : object containing exhaustion row and contentlevl 
	 * */

	public function calExhaustPsgAccAndChangeLevel($userExhaustionData){
		$totalPsgAccuracy=0;

		$this->dbEnglish->Select('p.passageID as passageID,q.accuracy as Accuracy,q.level as userLevel');
		$this->dbEnglish->from('passageAttempt p');
		$this->dbEnglish->join('userexhaustionlogiclog q', 'p.exScoringID=q.exScoringID', 'inner');
		$this->dbEnglish->where('q.exScoringID',$userExhaustionData->exScoringID);
		$exhaustionLogPsgsSql = $this->dbEnglish->get();
		$exhaustionLogPsgsArr = $exhaustionLogPsgsSql->result_array();	

		foreach ($exhaustionLogPsgsArr as $row)
		{
			$getPsgAccuracySql=$this->dbEnglish->query("select q.passageID,sum(q.quesAccuracy)/count(q.qcode) as psgAccuracy from (select count(qcode) as totalAttmpted,qcode,sum(if(correct>0.5,1,0)) as correct,sum(if(correct>0.5,1,0))/count(qcode)*100 as quesAccuracy,passageID from ".$this->questionAttemptClassTbl." where exScoringID=".$userExhaustionData->exScoringID." group by qcode)q where q.passageID=".$row['passageID']);
			//print $this->dbEnglish->last_query();
			if($getPsgAccuracySql->num_rows() > 0)
			{	
				$psgAccuracyArr=$getPsgAccuracySql->row();
				if($psgAccuracyArr->psgAccuracy != NULL)
					$totalPsgAccuracy = $totalPsgAccuracy+$psgAccuracyArr->psgAccuracy;
			}	
		}	

		$avgPsgAccuracy=$totalPsgAccuracy/$userExhaustionData->psgsToShow;
		//print $avgPsgAccuracy;
		$data=array('accuracy'=> round($avgPsgAccuracy,2));
		$this->dbEnglish->where('exScoringID', $userExhaustionData->exScoringID);
		$this->dbEnglish->where('contentType', $userExhaustionData->contentType);
		$this->dbEnglish->update('userexhaustionlogiclog', $data);//shivam- data updated here
		$userLevel=$exhaustionLogPsgsArr[0]['userLevel']; 

		if($userExhaustionData->contentType == readingContentTypeConst){
			$levelDegradeConst=readingPsgLevelConst;
		}
		else{
			$levelDegradeConst=listeningPsgLevelConst;
		}

		// if acc is > 40 and level is not 9 then user should increase the level,otherwise decrease
		// if acc is < 40 and level is not 4 then user should decrease the level,otherwise increase	

		if(($avgPsgAccuracy <= 40 && $userLevel!=4) || ($avgPsgAccuracy >= 40 && $userLevel==9))
			$userLevel=$userLevel-$levelDegradeConst;	
		else if(($avgPsgAccuracy >= 40 && $userLevel!=9) || ($avgPsgAccuracy <= 40 && $userLevel==4))
			$userLevel=$userLevel+$levelDegradeConst;	
		
		$totalPassages=$this->getUnattemptedExhaustionLogic($userExhaustionData->contentType,$userLevel,true,"countAllPsgAtmpt");

			//Shivam Start
			//Check if totalPassages is 0 then change the level, will create new entry
			if($totalPassages[0]['totalCount'] == 0 ){
				list($userLevel, $totalPassages[0]['totalCount']) = $this->getmslevel($this->user_id,$userExhaustionData->contentType);
			}
			//Shivam End

		//Set the level of 	userExhaustionData so as to return it.
		$userExhaustionData->level = $userLevel;
		$data = array(
				'userID' => $userExhaustionData->userID,
				'contentType' => $userExhaustionData->contentType,
				'accuracy' => 0,
				'level' => $userLevel,
				'psgsToShow' =>$totalPassages[0]['totalCount']

			);

		$this->dbEnglish->insert('userexhaustionlogiclog', $data);//Shivam- Entry goes from here.
		
		return (object) $data;
	
	}	
}

