<?php
	

Class Freeques_model extends MY_Model
{
	
	public function __construct() {
		 parent::__construct();
		 $this->dbEnglish = $this->load->database('mindspark_english',TRUE);
		 $this->load->library('session');
	}

	/**
	 * function description : check for stored session free question and set free questions in the session if not already set   
	 * param1   userID
	 * @return  none
	 * */
	
	public function setNextFreeQuesData($userID){
		if(!$this->session->userdata('sessionfreeQues'))
			$this->setNextSessionFreeQuestions($userID);
	}

	/**
	 * function description : Check for bunchID and get free questions according to limit
	 * param1   schoolBunchingOrder
	 * param2   userID
	 * param3   attemptedQuestionsinBunch
	 * param4   contentQuantity 
	 * */
	public function obtainBunchingQcodes($currentBunch,$contentQuantity,$qcodesArr,$isContentExhausted,$attemptedQuestionsinBunch){
	
		//echo "the currentBunch is $currentBunch"." \n";
		$freeQuesLevel = $this->session->userdata('freeQuesLevel');
		$userID = $this->session->userdata('userID');
		$groupSkillID = $this->session->userdata('groupSkillID');
		$endSkillDate = $this->session->userdata('endSkillDate');
		$childClass = $this->session->userdata('childClass');
		$refID = $this->session->userdata('refID');
		$qstnAtmptClss = "educatio_msenglish.questionAttempt_class$childClass";
		$todays_date = date("Y-m-d");
		$isGroupSkillActive = $this->session->userdata('isGroupSkillActive');
		//echo "isGroupSkillActive ".$isGroupSkillActive." \n";
		$bunchingLogicQuestionsSQL = "SELECT qcode FROM bunchMaster WHERE bunchID=$currentBunch and childClass=$freeQuesLevel";
		// check groupskill active for the user
		if($isGroupSkillActive == 1){
			$bunchingLogicQuestionsSQL .= " AND groupSkillID = $groupSkillID";
		}
		//check if there is attemptHistory
		if($attemptedQuestionsinBunch){ 
			//$this->dbEnglish->where_not_in('qcode',$attemptedQuestionsinBunch);
			$bunchingLogicQuestionsSQL .= " AND qcode NOT IN($attemptedQuestionsinBunch)";
		}

		$limitValue = $contentQuantity - count($qcodesArr); //add the no of questions to obtain from
		//$this->dbEnglish->order_by('RAND()');
		$bunchingLogicQuestionsSQL .= " ORDER BY RAND() LIMIT $limitValue";
		//$this->dbEnglish->limit($limitValue);
		$bunchingQcodes = $this->dbEnglish->query($bunchingLogicQuestionsSQL);
		$bunchingQcodesPresent = $bunchingQcodes->num_rows();
		
		//echo $bunchingLogicQuestionsSQL." \n";


		$qcodesArr = array_merge($qcodesArr,$bunchingQcodes->result_array());
		//check if qcodes are present  and check if qcodes == contentQuantity
		if($bunchingQcodesPresent > 0 && $bunchingQcodesPresent == $limitValue){
			$qcodesArr = $this->SplitArrayValue($qcodesArr);
			$bunchingFlowQcode = explode(",",$qcodesArr);
			//print_r($bunchingFlowQcode);exit;
			$this->session->set_userdata('sessionfreeQues',$bunchingFlowQcode);
			$this->session->set_userdata('sessionTypeToShow','freeQues');
		} else {
			$nextBunchDetails = $this->nextBunchID($currentBunch); //nextBunchID
			$currentBunch  = $nextBunchDetails['bunchID'];
			$isLastBunchID = $nextBunchDetails['isLastBunchID'];
			//not equal need to go to nextBunch and get qcodes
			if($isLastBunchID == 1 || $this->session->userdata('isFreeQuesContentExhausted')){
				
				$qcodesArr = $this->SplitArrayValue($qcodesArr);
				$bunchingFlowQcode = explode(",",$qcodesArr);
					//group skill not active
				if($qcodesArr){
					$remainingfreeQues = $contentQuantity - count($bunchingFlowQcode);
					$this->session->set_userdata('remainingfreeQues',$remainingfreeQues);
					$this->session->set_userdata('sessionfreeQues',$bunchingFlowQcode);
				} else {
					echo "Data not present!";exit;
				}
																							
			} else {
				$this->obtainBunchingQcodes($currentBunch,$contentQuantity,$qcodesArr,$isContentExhausted,$attemptedQuestionsinBunch);
			}
		}
	}

	/**
	 * function description : Obtain nextBunchID as an array.
	 * param1   currentBunch
	 * @return bunchDetails
	 * */
	public function nextBunchID($currentBunch){
		
		$bunchDetails = array();
		$schoolBunchingOrder = $this->session->userdata('schoolBunchingOrder');
		$bunchDetails['isLastBunchID'] = 0;
		if($schoolBunchingOrder){
			$lastBunchKey = end(array_keys($schoolBunchingOrder));
			$currentBunchKey = array_search($currentBunch,$schoolBunchingOrder);
			if($currentBunchKey == $lastBunchKey){ //If end of array reached restart and obtain first element
				$bunchDetails['bunchID'] = $schoolBunchingOrder[0];
				$bunchDetails['isLastBunchID'] = 1;
			} else {
				//obtain next element until end of line has reached
				$bunchDetails['bunchID'] = $schoolBunchingOrder[$currentBunchKey+1];
				if($schoolBunchingOrder[$currentBunchKey] == $schoolBunchingOrder[$lastBunchKey]){
					$bunchDetails['isLastBunchID'] = 1;
				}
			}
			return $bunchDetails;
		}
		return NULL;
	}

	/*
	* CODE IN EARLY DEVELOPMENT STAGE
	*/ 
	public function setNextSessionFreeQuestions($userID,$changeLevel=false,$exhaustionLogLevel=false){
		//Step 1: Obtain last or current bunching id from educatio_msenglish.questionAttempt_class using userID

		$userInfo['childClass'] = $childClass = $this->session->userdata('childClass');
		$userInfo['schoolCode'] = $schoolCode = $this->session->userdata('schoolCode');
		$userInfo['groupSkillID'] = $groupSkillID = $this->session->userdata('groupSkillID');
		$groupSkillID = $this->session->userdata('groupSkillID');
		$endSkillDate = $this->session->userdata('endSkillDate');
		$todays_date = date("Y-m-d");

		$qcodesArr= array();
		
		$contentAttemptCount = $this->session->userdata('contentAttemptCount');
		$schoolBunchingOrder = $this->session->userdata('schoolBunchingOrder');
		
		$isContentExhausted = 0;

		$refID = $this->session->userdata('refID');
		if($refID !="" && $refID != 0){
			$contentQuantity = $this->session->userdata('contentQuantity') - $contentAttemptCount - 1;	
		} else {
			$contentQuantity = $this->session->userdata('contentQuantity') - $contentAttemptCount;
		}
		$qstnAtmptClss = "educatio_msenglish.questionAttempt_class$childClass";
		//Check if any previously attempted bunchID is there -- contentAttemptLog.
		
		$isCurrentBunchIDPresent = $this->isCurrentBunchIDPresent($userID,$schoolBunchingOrder);
		if(is_array($isCurrentBunchIDPresent) && $isCurrentBunchIDPresent['isCurrentBunchIDPresent'] == 0){
			//no history of attempt take first element of schoolBunchingOrder and start
			$currentBunch = $schoolBunchingOrder[0];
			$this->session->set_userdata('isValidRefID',0);	
		} else {
			$currentBunch = $isCurrentBunchIDPresent;
			$this->session->set_userdata('isValidRefID',1);	
		}

		if($groupSkillID != 0 && strtotime($todays_date) <= strtotime($endSkillDate)){
			$this->session->set_userdata('isGroupSkillActive', 1);
			$this->dbEnglish->set('bunchBeforeGroupSkillActivation', $currentBunch);
			$this->dbEnglish->where('userID', $userID);
			$this->dbEnglish->update('userCurrentStatus');
		} else {
			//echo 'inside inactive gp';exit;
			$this->session->set_userdata('isGroupSkillActive', 0);
		}

		//echo 'done setting';exit;
		$this->session->set_userdata('currentBunch',$currentBunch);

		//Obtain attempted Questions for the currentBunch - if empty will return none
		$attemptedQuestionsinBunch = $this->selectAttmptdQstnCurrentBnchQuery($currentBunch,$userID,$qstnAtmptClss);
		$attemptedQuestionsinBunch = $this->SplitArrayValue($attemptedQuestionsinBunch);
		$this->obtainBunchingQcodes($currentBunch,$contentQuantity,$qcodesArr,$isContentExhausted,$attemptedQuestionsinBunch);
	}

	public function isCurrentBunchIDPresent($userID,$schoolBunchingOrder){
		$isCurrentBunchIDPresentSQL = "SELECT currentBunchID,bunchCompleted FROM userContentAttemptDetails WHERE userID=$userID AND contentType='freeQues'";
		$isCurrentBunchID=$this->dbEnglish->query($isCurrentBunchIDPresentSQL);
		$isCurrentBunchIDPresent=$isCurrentBunchID->num_rows();
		if($isCurrentBunchIDPresent > 0){
			//this means there is data and we have to resume with existing data
			$currentBunchID = $isCurrentBunchID->row()->currentBunchID;
			$bunchCompleted = $isCurrentBunchID->row()->bunchCompleted;
			if($bunchCompleted == 1){
				$currentBunchID = $this->nextBunchID($currentBunchID);
				return $currentBunchID;
			} else {
				return $currentBunchID;
			}
		} else {
			//this means there is no data and we have to insert data
			$data = array(
				'userID' => $userID,
				'totalAttempts' => 0,
				'contentType' => 'freeQues',
				'currentBunchID' => $schoolBunchingOrder[0],
				'bunchCompleted' => 0
			);
			$this->dbEnglish->insert('userContentAttemptDetails', $data);
			$this->session->set_userdata('currentBunch',$schoolBunchingOrder[0]);
			return array('isCurrentBunchIDPresent' => 0, 'contentAttemptLog_insertSrNo' => $this->dbEnglish->insert_id());
		}
	}
	/**
	 * function description : Check for selected group and get free questions accordingly
	 * param1   userID
	 * param2   changeLevel is enabled or disabled as per the change level request 
	 * param3   exhaustionLogLevel is level in exhaustion if passaed 
	 * @return  none 
	 * */
	
	public function setDefaultFreeQuestions($userID,$changeLevel,$exhaustionLogLevel){

		$freeQuesArr=array();
		$freeQuesLevel=$this->session->userdata('freeQuesLevel');
		
		$isContentExhausted=false;
		$currFreeQuesFlow="default";
		$selectedGroupID=7;

		if($exhaustionLogLevel)
			$freeQuesLevel=$exhaustionLogLevel;

		$freeQuesMsLevel=$freeQuesLevel-gradeScallingConst;

		$freeQuesArr=$this->setFreeQuesArr($currFreeQuesFlow,$selectedGroupID,$freeQuesMsLevel,$userID,$changeLevel);

		if($freeQuesLevel == $this->session->userdata('freeQuesLevel') && !$isContentExhausted){	
			$freeQuesMsLevel=$freeQuesLevel-3;
			$getCurrLevelFreeQuesCntSql=$this->dbEnglish->query('select count(*) as totalFreeQues from questions where passageID=0 and msLevel='.$freeQuesMsLevel.' and status=6 and skillID in (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23)');
			$currLevelFreeQuesCntData = $getCurrLevelFreeQuesCntSql->row();
			$currLevelFreeQuesCnt=$currLevelFreeQuesCntData->totalFreeQues;
			$attemptedFreeQuesArr=$this->questionspage_model->getPreviousAttemptedNCQQues($this->user_id,$freeQuesMsLevel);
			$attemptedFreeQuesCnt=count($attemptedFreeQuesArr);
			if($currLevelFreeQuesCnt-$attemptedFreeQuesCnt > 100){
				$data=array('isFreeQuesContentExhaust'=>0);
				$this->dbEnglish->where('userID', $this->user_id);
				$this->dbEnglish->update('userCurrentStatus', $data);
				$this->session->set_userdata($data);
				$this->unsetIsContentExhaustionFlag();	
			} else {
				if($this->session->userdata('isFreeQuesContentExhaust')){
					$isContentExhausted=$this->calRemainingFreeQues($freeQuesLevel);
					if($isContentExhausted && !$exhaustionLogLevel) 
					{
						if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER') //added by nivedita
						{
							$freeQuesArr = array();
							$isContentExhaustedTeacher = array('isContentExhaustedTeacher'=>1);
							$this->session->set_userdata($isContentExhaustedTeacher);
							return;
						}
						else
						{
							$this->setExhaustionLogic($freeQuesLevel);
							return;
						}
					}
				}
			}
		}
		if(count($freeQuesArr)>0)
		{
			$this->session->set_userdata('sessionfreeQues',$freeQuesArr);
		}else{

			//Shivam - Call the function getmslevel so that it will set the new level in which free questions are available.
			$this->updateOrInsertAfterNoQuesleftinExhaustion($userID);

			//echo "a no free question left for this user";
		}	
		
	}

	/**
	 * function description : Check for selected group and get free questions accordingly
	 * param1   selectedGroupID
	 * param2   userID  
	 * param3   changeLevel is enabled or disabled as per the change level request  
	 * param4   exhaustionLogLevel is level in exhaustion if passaed   
	 * @return  none 
	 * */
	
	public function setGroupedFreeQuestions($selectedGroupID,$userID,$changeLevel,$exhaustionLogLevel){
		
		$freeQuesArr=array();
		$freeQuesLevel=$this->session->userdata('freeQuesLevel');
		
		$attemptedFreeQuesArr = array();		
		$currFreeQuesFlow="group";

		if($exhaustionLogLevel)
			$freeQuesLevel=$exhaustionLogLevel;

		$freeQuesMsLevel=$freeQuesLevel-gradeScallingConst;	

		$attemptedFreeQuesArr = $this->questionspage_model->getPreviousAttemptedNCQQues($userID,$freeQuesMsLevel);
		
		if(count($attemptedFreeQuesArr) > 0)
			$addParams=" and qcode NOT IN (".implode(',', $attemptedFreeQuesArr).")";

		$skills=$this->getGroupSkills($selectedGroupID);	
		$checkQuesPending=$this->dbEnglish->query("select qcode,msLevel from questions where skillID IN (".$skills.") and status=".liveQuestionsStaus." and passageID=0 and msLevel=".$freeQuesMsLevel.$addParams);
		//$checkQuesPending=$this->dbEnglish->query("select qcode,msLevel from questions where skillID IN (1,4) and status=6 and passageID=0 and msLevel=2 and qcode NOT IN (6490,5774,8260,7908,7870,7860,27,331,762,763,943,968,969,62,669,883,1570,1572,1575,1587,1610,1612,1616,211,709,713,1617,1627,1691,4064,4065,4066,4067,835,1010,1017,4068,4069,4070,4071,4072,4073,5098,363,379,414,5100,5102,7495,7496,7497,7498,7499,262,288,360,7500,8842,8843,8844,8845,8846,8847,40,674,676,8848,8849,8850,8851,9992,9993,10050)");
		$quesPending=$checkQuesPending->num_rows();
		if($quesPending>0)
		{
			$freeQuesArr=$this->setFreeQuesArr($currFreeQuesFlow,$selectedGroupID,$freeQuesMsLevel,$userID,$changeLevel);
			if($freeQuesLevel == $this->session->userdata('freeQuesLevel') && !$isContentExhausted)
			{
				$freeQuesMsLevel=$freeQuesLevel-3;
				$getCurrLevelFreeQuesCntSql=$this->dbEnglish->query('select count(*) as totalFreeQues from questions where passageID=0 and msLevel='.$freeQuesMsLevel.' and status=6 and skillID in (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23)');
				$currLevelFreeQuesCntData = $getCurrLevelFreeQuesCntSql->row();
				$currLevelFreeQuesCnt=$currLevelFreeQuesCntData->totalFreeQues;
				$attemptedFreeQuesArr=$this->questionspage_model->getPreviousAttemptedNCQQues($this->user_id,$freeQuesMsLevel);
				$attemptedFreeQuesCnt=count($attemptedFreeQuesArr);
				if($currLevelFreeQuesCnt-$attemptedFreeQuesCnt > 100)
				{
					$data=array('isFreeQuesContentExhaust'=>0);
					$this->dbEnglish->where('userID', $this->user_id);
					$this->dbEnglish->update('userCurrentStatus', $data);
					$this->session->set_userdata($data);
					$this->unsetIsContentExhaustionFlag();
				}else{
					if($this->session->userdata('isFreeQuesContentExhaust'))
					{
						$isContentExhausted=$this->calRemainingFreeQues($freeQuesLevel);
						if($isContentExhausted && !$exhaustionLogLevel)
						{
							if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER') //added by nivedita
							{
								$freeQuesArr = array();
								$isContentExhaustedTeacher = array('isContentExhaustedTeacher'=>1);
								$this->session->set_userdata($isContentExhaustedTeacher);
								return;
							}
							else
							{
								$this->setExhaustionLogic($freeQuesLevel);
								return;
							}
						}
					}
				}
			}
			//echo "e";
			//print_r($freeQuesArr);
			
			if(count($freeQuesArr)>0)
			{
				$this->session->set_userdata('sessionfreeQues',$freeQuesArr);
			}else{
				$data=array('groupSkillID'=> 0,'startSkillDate'=> '0000-00-00','endSkillDate'=> '0000-00-00');
				$this->dbEnglish->where('userID', $userID);		
				$this->dbEnglish->update('userCurrentStatus', $data);
				$this->session->set_userdata($data);
				$this->setNextFreeQuesData($userID);
			}
		}else{
			$data=array('groupSkillID'=> 0,'startSkillDate'=> '0000-00-00','endSkillDate'=> '0000-00-00');
			$this->dbEnglish->where('userID', $userID);		
			$this->dbEnglish->update('userCurrentStatus', $data);
			$this->session->set_userdata($data);
			$this->setNextFreeQuesData($userID);

		}

			
	}
	
	/**
	 * function description : set and return free question array as per the default logic , or selected group question logic
	 * param1   currfreeQuesFlow "Default" or "group"
	 * param2   selectedGroupID for which it need  to be set  
	 * param3   freeQuesMsLevel is ms Level for which free question need to be set  
	 * param4   change Level is true or false as per the level change request  
	 * @return  none 
	 * */
	
	public function setFreeQuesArr($currFreeQuesFlow,$selectedGroupID,$freeQuesMsLevel,$userID,$changeLevel)
	{
		if($currFreeQuesFlow=="default"){
			$totalQuesForSelectedSkill=3;		
		}else{
			$totalQuesForSelectedSkill=7;
		}
		
		$totalQuesForNonSelectedSkill=3;
		$addParams="";
		$freeQuesArr=array();
		$attemptedFreeQuesArr = array();
		$arrGroupQues= array();	

		$attemptedFreeQuesArr = $this->questionspage_model->getPreviousAttemptedNCQQues($userID,$freeQuesMsLevel);
		
		if(count($attemptedFreeQuesArr) > 0)
			$addParams=" and qcode NOT IN (".implode(',', $attemptedFreeQuesArr).")";

		$attemptedGroupQnsData=$this->getPreviousAttemptedGroupID($userID,$selectedGroupID);

		if($attemptedGroupQnsData==NULL)
		{
			$attemptedGroupQnsData=array();		
		}
		else
		{
			//when the level is changed do not check for previous attempted questions for user , the flow should be considered as new		
			if(!$changeLevel)
			{					
				$attemptedGroupQnsData = array_reverse($attemptedGroupQnsData, true);								
			}
			else
			{
				$attemptedGroupQnsData=array();		
			}
		}

		if (count($attemptedGroupQnsData)>0)
		{	
			$key_skill = array_keys($attemptedGroupQnsData);		
			$val_totalQAttempt= array_values($attemptedGroupQnsData);
			if(count($key_skill)==1)
			{
				array_unshift($key_skill,"0"); 
			}
			if(count($val_totalQAttempt)==1)
			{
				array_unshift($val_totalQAttempt,"3"); 
			}	

			$allGroupSkill = array(1,2,3,4,5,6,7);
			if($key_skill[1]!=$selectedGroupID)
			{
				$val=$key_skill[1]+1;
			}
			else
			{
				$val=$key_skill[0]+1;
			}
			if($val==$selectedGroupID)
			{
				$val=$val+1;
			}

			$skillArr = array();
			for ($x = 1; $x <= 7; $x++) 
			{
				if($x>=$val)
				{
					//echo "The number is: $val <br>";
					if (($key = array_search($val, $allGroupSkill)) !== false) {
   				 		array_push($skillArr,$allGroupSkill[$key]); 
   				 		$val=$allGroupSkill[$key];   				 
   				 		unset($allGroupSkill[$key]);
					}
					$val=$val+1;
				}			
    		}   	

			foreach ($allGroupSkill as $value)
			{
				array_push($skillArr,$value); 
			}

			if (($key = array_search($selectedGroupID, $skillArr)) !== false)
			{
   			 	unset($skillArr[$key]);
			}

			//$skillArr has the final non selected skill flow array for the user	
			$skillArr=array_values($skillArr);
			//print_r($skillArr);
		
			if($key_skill[1]==$selectedGroupID && $val_totalQAttempt[1]<=$totalQuesForSelectedSkill)
			{
				if($val_totalQAttempt[1]<$totalQuesForSelectedSkill)
				{			
					$tmpArr=array("skill" => $selectedGroupID ,"totalQuesofSkill"=> $totalQuesForSelectedSkill-$val_totalQAttempt[1]);
					array_push($arrGroupQues,$tmpArr);
				}	
			}
			else
			{
				if($val_totalQAttempt[1]<$totalQuesForNonSelectedSkill)
				{
					$tmpArr=array("skill" => $key_skill[1] ,"totalQuesofSkill"=> $totalQuesForNonSelectedSkill-$val_totalQAttempt[1]);
					array_push($arrGroupQues,$tmpArr);
				}
				//below case taken for offline scenario in case selected group questions are attempted greater than 7 
				if($key_skill[1]!=$selectedGroupID)
				{
					$tmpArr=array("skill" => $selectedGroupID ,"totalQuesofSkill"=> $totalQuesForSelectedSkill);
					array_push($arrGroupQues,$tmpArr);
				}					
			}


		}
		else
		{
			$skillArr = array(1,2,3,4,5,6,7);
			if (($key = array_search($selectedGroupID, $skillArr)) !== false)
			{
   			 	unset($skillArr[$key]);
			}
			$skillArr=array_values($skillArr);
			$tmpArr=array("skill" => $selectedGroupID ,"totalQuesofSkill"=> $totalQuesForSelectedSkill);
			array_push($arrGroupQues,$tmpArr);
		}
	
		foreach ($skillArr as $value) 
		{
			$tmpArr=array("skill" =>$value ,"totalQuesofSkill"=> $totalQuesForNonSelectedSkill);
			array_push($arrGroupQues,$tmpArr);
			$tmpArr=array("skill" => $selectedGroupID ,"totalQuesofSkill"=> $totalQuesForSelectedSkill);
			array_push($arrGroupQues,$tmpArr);
    		
		}

		//$arrGroupQues has Final skill flow with selected  and non selected skill for the user
		//print_r($arrGroupQues);
		$count=0;
		foreach ($arrGroupQues as $value) 
		{
			$skill=  $value["skill"];
			$tQues= $value["totalQuesofSkill"];
			$skills=$this->getGroupSkills($skill);	
			$limit=" limit ".$tQues;
			//echo $check." a ".$totalQuesNotAttempted;
		 	if ($check==1) 
		 	{ 
		 		$check=0;
		 		continue; 
		 	}
			$getTotalQuesAtSkill=$this->dbEnglish->query("select qcode,msLevel from questions where skillID IN (".$skills.") and status=".liveQuestionsStaus." and passageID=0 and msLevel=".$freeQuesMsLevel.$addParams."".$limit);
			$totalQuesNotAttempted=$getTotalQuesAtSkill->num_rows();
			
			if($totalQuesNotAttempted>0)
			{
				$srNo=1;
				foreach($getTotalQuesAtSkill->result_array() as $row)
				{
					array_push($attemptedFreeQuesArr,$row['qcode']);
					$freeQuesDataArr[$count]['srNo']=$srNo;
					$freeQuesDataArr[$count]['qcode']=$row['qcode'];
					$freeQuesDataArr[$count]['msLevel']=$row['msLevel'];
					//$freeQuesDataArr[$count]['groupCat']="selectedgroup";
					$freeQuesDataArr[$count]['groupNo']=$skill;
				
					$count++;
					$srNo++;
				}
				$addParams=" and qcode NOT IN (".implode(',', $attemptedFreeQuesArr).")";
			
			}
		
			if($selectedGroupID==$skill)
			{	
				if($totalQuesNotAttempted<$tQues)
				{
					#" when less or no No questions pending for this skill - Scenario can also be like when questions is left 3 and question is not pending in next flow";
					if($currFreeQuesFlow=="group"){
						break;
					}
				}			
			}
			else
			{
				if($totalQuesNotAttempted==0)
				{
					$check=1;
				}
				else
				{
					$check=0;
				}
			}		
		
		}

		//$freeQuesDataArr has the complete details of flow ,skill and exact nummber of questions need to be given to user

		foreach($freeQuesDataArr as $key=>$value)
		{
			array_push($freeQuesArr,$value['qcode']);
		}

		return $freeQuesArr;


	}
	
	/**
	 * function description : To unset the isContentExhaust flag is all three flags are unset.
	 * @return  none 
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
	 * function description : To unset the isContentExhaust flag is all three flags are unset.
	 * @return  string , comma separated group skill IDs 
	 *
	 * */
	
	
	public function getGroupSkills($no){
		$this->dbEnglish->Select('skilID');
		$this->dbEnglish->from('groupSkillMaster');
		$this->dbEnglish->where('groupSkillID',$no);
		$getGroupSkillsSql = $this->dbEnglish->get();
		$groupSkillData = $getGroupSkillsSql->row();
		return $groupSkillData->skilID;
	}
	
	/**
	 * function description : checks userLevelAndAccLog free question latest entry if it is not there then creates entry for it
	 * param1   userID
	 * param2   contentType
	 * @return  none;
	 * 
	 * */
	
	public function updateUserLevelAndAccFreeQuesLog($userID,$contentType){
		
		$level=$this->session->userdata('freeQuesLevel');
		
		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userLevelAndAccuracyLog');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->order_by('scoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserLevelAndAccLogSql = $this->dbEnglish->get();
		if($getUserLevelAndAccLogSql->num_rows() > 0){
			$userLevelAndAccData = $getUserLevelAndAccLogSql->row();
			$this->updateQuesPsgAttemptCount($userLevelAndAccData);	
		}
		else{
			$data = array(
				'userID' => $userID,
				'contentType' => $contentType,
				'quesPsgAttemptCount' => 0,
				'accuracy' => 0,
				'level' => $level
			);

			$this->dbEnglish->insert('userLevelAndAccuracyLog', $data); 	
		}			
	}
	
	//greater than or equal to condition added for offline cases where count will be increased

	/**
	 * function description : update userLevelAndAccLog free question latest entry count calls accuracy calculation function if maximum count is reached
	 * param1   userLevelAndAccData , row data of current free question entry for the current user
	 * @return  none;
	 * 
	 * */
	
	public function updateQuesPsgAttemptCount($userLevelAndAccData){
		
		if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER')//added by nivedita
		{
			$level = $this->session->userdata('freeQuesLevel'); //change passageLevel to freeques level

			$this->dbEnglish->Select('quesPsgAttemptCount');
			$this->dbEnglish->from('userLevelAndAccuracyLog');
			$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
			$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
			$this->dbEnglish->where('level', $level);
			$getFreeAttmpCountSql = $this->dbEnglish->get();
			$getFreeAttmpCountRow = $getFreeAttmpCountSql->row_array();

			$userLevelAndAccData->quesPsgAttemptCount = $getFreeAttmpCountRow['quesPsgAttemptCount']+1;
			$data=array('quesPsgAttemptCount'=> $userLevelAndAccData->quesPsgAttemptCount);

			$this->dbEnglish->where('level', $level);
			$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
			$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
		}
		else
		{
			$userLevelAndAccData->quesPsgAttemptCount=$userLevelAndAccData->quesPsgAttemptCount+1;
			$data=array('quesPsgAttemptCount'=> $userLevelAndAccData->quesPsgAttemptCount);

			$this->dbEnglish->where('scoringID', $userLevelAndAccData->scoringID);
			$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
			$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
		}
		$this->dbEnglish->update('userLevelAndAccuracyLog', $data);
		if($this->category == 'STUDENT') //added by nivedita for dms
		{
			if($this->dbEnglish->affected_rows() == 1){
				if(($userLevelAndAccData->contentType==freeQuesContentTypeConst && $userLevelAndAccData->quesPsgAttemptCount >= freeQuesChangeLevelCount))
					$this->checkAccuracyAndChangeLevel($userLevelAndAccData);
			}
		}
	}

	/**
	 * function description : Checks accuracy for the passed scoringID data and calculates new level based on accuracy
	 * param1   userLevelAndAccData , row data of current free question entry for the current user
	 * @return  none;
	 * 
	 * */
		
	public function checkAccuracyAndChangeLevel($userLevelAndAccData)
	{
		$totalCorrects=0;		
		$this->dbEnglish->Select('p.qcode as qcode,p.correct as correct,q.accuracy as Accuracy,q.level as userLevel');
		$this->dbEnglish->from($this->questionAttemptClassTbl.' p');
		$this->dbEnglish->join('userLevelAndAccuracyLog q', 'p.scoringID=q.scoringID', 'inner');
		$this->dbEnglish->where('q.scoringID',$userLevelAndAccData->scoringID);
		$freeQuesDataSql = $this->dbEnglish->get();
		$freeQuesDataArr = $freeQuesDataSql->result_array();	
		
		foreach ($freeQuesDataArr as $row)
		{	
			$totalCorrects=$totalCorrects+$row['correct'];
		}	
		
		$accuracy=$totalCorrects/freeQuesChangeLevelCount*100;
		
		$data=array('accuracy'=> round($accuracy,2));
		$this->dbEnglish->where('scoringID', $userLevelAndAccData->scoringID);
		$this->dbEnglish->where('contentType', $userLevelAndAccData->contentType);
		$this->dbEnglish->update('userLevelAndAccuracyLog', $data);
		$userLevel=$freeQuesDataArr[0]['userLevel'];
		
		if($accuracy <= 40 && $userLevel!=4)
			$userLevel=$userLevel-freeQuesLevelConst;	
		else if($accuracy >= 80 && $userLevel!=9)
			$userLevel=$userLevel+freeQuesLevelConst;	
		
		
		$data = array(
				'userID' => $userLevelAndAccData->userID,
				'contentType' => $userLevelAndAccData->contentType,
				'accuracy' => 0,
				'level' => $userLevel
			);

		//print $userLevel;
		$this->dbEnglish->insert('userLevelAndAccuracyLog', $data);
		$data=array('freeQuesLevel'=>$userLevel);
		$this->dbEnglish->where('userID', $userLevelAndAccData->userID);
		$this->dbEnglish->update('userCurrentStatus', $data);
		$this->session->set_userdata($data);
		$this->session->unset_userdata('sessionfreeQues');
		$this->setNextSessionFreeQuestions($userLevelAndAccData->userID,true);

		$curLevel=$this->session->userdata('freeQuesLevel');
		//print $curLevel;
		$isFreeQuesExhausted=$this->calRemainingFreeQues($curLevel);
		//print $isFreeQuesExhausted;
		if($isFreeQuesExhausted)
		{
			if($this->category == 'ADMIN' || $this->category == 'School Admin' || $this->category == 'TEACHER') //added by nivedita
			{
				$isContentExhaustedTeacher = array('isContentExhaustedTeacher'=>1);
				$this->session->set_userdata($isContentExhaustedTeacher);
				return;
			}
			else
			{
				$this->setExhaustionLogic($curLevel);			
				return;
			}
		}

	}
	
	/**
	 * function description : To get previously attempted qcode groupskillID details  
	 * param1   userID
	 * param2   selectedGroupID 
	 * @return  array , of which groupskillID qcodes was attempted previously and how many;
	 * 
	 * */
	
	function getPreviousAttemptedGroupID($userID,$selectedGroupID=null){
		$limit = "4";  // 3+1(To know the 3 questions skill and 4th questions skill)
		$attemptedGroupQns=0;
		if($selectedGroupID > 0)
			$limit ="8"; // 7+1(To know the 7 group skill and 8th group skill)
		$getPreviousAttGroupDetSql=$this->dbEnglish->query("select q.skillID,p.qcode from ".$this->questionAttemptClassTbl." p,questions q where p.userId=".$userID." and p.passageId=0 and p.qcode=q.qcode order by p.lastModified desc LIMIT ".$limit);
		if($getPreviousAttGroupDetSql->num_rows() > 0){
			$groupArr=array();
			foreach($getPreviousAttGroupDetSql->result_array() as $row){
				$skillIDArr=array();
				$skillIDArr=explode(',', $row['skillID']);
				$getGroupSkillIDSql=$this->dbEnglish->query("select groupSkillID,skilID from groupSkillMaster where find_in_set('".$skillIDArr[0]."',skilID) <> 0");
				$groupSkillIDData = $getGroupSkillIDSql->row();
				$groupSkills=$groupSkillIDData->skilID;
				$qcodeArr[$groupSkillIDData->groupSkillID]++;
				array_push($groupArr,$groupSkillIDData->groupSkillID);
			}
			
			if($selectedGroupID > 0){
				//$groupArr=array_reverse($groupArr);

				//arrayfilter() added by nivedita as this array was containing empty elements as well so it was getting stuck for freeQuestions
				$groupArr=array_reverse(array_filter($groupArr));
				$qcodeArr=array();
				$j=0;
				for($i=count($groupArr)-1;$i>=0;$i--)
				{
						if($j==0 || $j==1)
							$final[$groupArr[$i]] += 1;	
						if($groupArr[$i] != $groupArr[$i-1])
						{
							if($j==0)
								$j=1;
							else{
								$j=2;
							}
						}
				}
				
				foreach($final as $key=>$val)
				{
					$qcodeArr[$key]=$final[$key];
				}
			}else{
				$qcodeArr=array();
			}
			
			return $qcodeArr;
		}
	}
	
	/**
	 * function description : To get the latest userLevelAccLog free question content type attempt count  
	 * param1   userID
	 * @return  int , quesPsgAttemptCount value for the latest entry of the quesPsgAttemptCount.
	 * 
	 * */
	
	
	function getLogFreeQuesTotal($userID){
		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userLevelAndAccuracyLog');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',freeQuesContentTypeConst);
		$this->dbEnglish->order_by('scoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserLevelAndAccLogSql = $this->dbEnglish->get();
		if($getUserLevelAndAccLogSql->num_rows() > 0){
			$userLevelAndAccData = $getUserLevelAndAccLogSql->row();
			return $userLevelAndAccData->quesPsgAttemptCount;
		}
		else
			return 0;
	}
	
	/**
	 * function description : To calculate the unattempted free questions on the passed free question level,check exhaustion condition and set exhaustion flag  
	 * param1   freeQuesLevel
	 * @return  boolean , true if in exhuastion and false if not.
	 * 
	 * */

	function calRemainingFreeQues($freeQuesLevel){
		$freeQuesMsLevel=$freeQuesLevel-3;
		$getCurrLevelFreeQuesCntSql=$this->dbEnglish->query('select count(*) as totalFreeQues from questions where passageID=0 and msLevel='.$freeQuesMsLevel.' and status=6 and skillID in (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23)');
		$currLevelFreeQuesCntData = $getCurrLevelFreeQuesCntSql->row();
		$currLevelFreeQuesCnt=$currLevelFreeQuesCntData->totalFreeQues;
	
		$attemptedFreeQuesArr=$this->questionspage_model->getPreviousAttemptedNCQQues($this->user_id,$freeQuesMsLevel);
		$attemptedFreeQuesCnt=count($attemptedFreeQuesArr);
		if($currLevelFreeQuesCnt-$attemptedFreeQuesCnt < 100){
			$data=array('isFreeQuesContentExhaust'=>1);
			$this->dbEnglish->where('userID', $this->user_id);
			$this->dbEnglish->update('userCurrentStatus', $data);
			$this->session->set_userdata($data);
			//print "AAA";
			return true;
		}
		else{
			
			return false;
		}
			
	}

	/**
	 * function description : sets exhuastion logic based free question if exhuastion condition is satisfied  
	 * param1   freeQuesLevel
	 * @return  none.
	 * 
	 * */

	public function setExhaustionLogic($freeQuesLevel){
		if(!$this->session->userdata('isFreeQuesContentExhaust'))
				$this->sendMail($this->freeQuesContExhaustMailMessage);

		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userexhaustionlogiclog');
		$this->dbEnglish->where('userID',$this->user_id);
		$this->dbEnglish->where('contentType',freeQuesContentTypeConst);
		$this->dbEnglish->order_by('exScoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserExhaustionLogSql = $this->dbEnglish->get();
		
		if($getUserExhaustionLogSql->num_rows() > 0){
			$userExhaustionData = $getUserExhaustionLogSql->row();
			$exhaustionFreeQuesArr=$this->setNextSessionFreeQuestions($this->user_id,true,$userExhaustionData->level);
			//exit;
		}
		else{
			$this->calFreeQuesAccAndSetExhaustionLog($freeQuesLevel);
		}
	}



	/**
	 * function description : calculates free question accuracy and sets exhaustion log  
	 * param1   freeQuesLevel
	 * @return  none.
	 * 
	 * */
	
	function calFreeQuesAccAndSetExhaustionLog($freeQuesLevel)
	{
		$this->dbEnglish->Select('group_concat(scoringID) as attemptedScoringIDs');
		$this->dbEnglish->from('userLevelAndAccuracyLog');
		$this->dbEnglish->where('userID',$this->user_id);
		$this->dbEnglish->where('contentType',freeQuesContentTypeConst);
		$this->dbEnglish->where('level',$freeQuesLevel);
		$getPreviousAttLogsDetSql = $this->dbEnglish->get();
		//print $this->dbEnglish->last_query();
		$previousAttLogsDetData=$getPreviousAttLogsDetSql->result_array();
		if($previousAttLogsDetData[0]['attemptedScoringIDs'] != null){
			$attemptedScoringIDs=$previousAttLogsDetData[0]['attemptedScoringIDs'];
			$getCurrLevelTotalAccSql=$this->dbEnglish->query('select p.qcode as qcode,sum(p.correct) as totalCorrect,q.level as userLevel,count(*) as totalQuestions from '.$this->questionAttemptClassTbl.' p,userLevelAndAccuracyLog q where p.scoringID=q.scoringID and q.scoringID IN ('.$attemptedScoringIDs.')');
			$getCurrLevelTotalAccData=$getCurrLevelTotalAccSql->row();
			$avgAccuracy=($getCurrLevelTotalAccData->totalCorrect/$getCurrLevelTotalAccData->totalQuestions)*100;
			
			if(($avgAccuracy <= 50 && $freeQuesLevel!=4) || ($avgAccuracy >= 50 && $freeQuesLevel==9))
				$freeQuesLevel=$freeQuesLevel-freeQuesLevelConst;	
			else if(($avgAccuracy >= 50 && $freeQuesLevel!=9) || ($avgAccuracy <= 50 && $freeQuesLevel==4))
				$freeQuesLevel=$freeQuesLevel+freeQuesLevelConst;	
		
			//print "Free Ques level in exhaustion".$freeQuesLevel;
			$freeQuesMsLevel=$freeQuesLevel-3;
			//print 'select count(*) as totalFreeQues from questions where passageID=0 and msLevel='.$freeQuesLevel.' and status=6';
			$getCurrLevelFreeQuesCntSql=$this->dbEnglish->query('select count(*) as totalFreeQues from questions a LEFT JOIN '.$this->questionAttemptClassTbl.' b on a.qcode=b.qcode and b.userID='.$this->user_id.' and b.passageID=0 where a.passageID=0 and a.msLevel='.$freeQuesMsLevel.' and a.status=6 and a.skillID in (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23) and b.qcode is null ');
			$currLevelFreeQuesCntData = $getCurrLevelFreeQuesCntSql->row();
			$currLevelFreeQuesCnt=$currLevelFreeQuesCntData->totalFreeQues;
			
			$data = array(
					'userID' => $this->user_id,
					'contentType' => freeQuesContentTypeConst,
					'accuracy' => 0,
					'level' => $freeQuesLevel,
					'psgsToShow' => $currLevelFreeQuesCnt
				);

			$this->dbEnglish->insert('userexhaustionlogiclog', $data);		
			$data=array('isContentExhaust'=>1,'isFreeQuesContentExhaust'=>1);
			$this->session->set_userdata($data);
		 	$this->dbEnglish->where('userID', $this->user_id);
			$this->dbEnglish->update('userCurrentStatus', $data); 

			$this->session->unset_userdata('sessionfreeQues');
			//print "Free Ques level in exhaustion 2".$freeQuesLevel;
			$this->setNextSessionFreeQuestions($this->user_id,true,$freeQuesLevel);
		}
	}

	
	/**
	 * function description : checks userExhaustionlogiclog entry for the user and updates the count of attempt for free question  
	 * param1   userID
	 * param1   contentType
	 * @return  none.
	 * 
	 * */
	
	
	public function updateUserExhaustLevelAndAccLog($userID,$contentType){
		$level=$this->session->userdata('freeQuesLevel');
		$this->dbEnglish->Select('*');
		$this->dbEnglish->from('userexhaustionlogiclog');
		$this->dbEnglish->where('userID',$userID);
		$this->dbEnglish->where('contentType',$contentType);
		$this->dbEnglish->order_by('exScoringID','desc');
		$this->dbEnglish->limit(1);
		$getUserExhaustionLogSql = $this->dbEnglish->get();
		if($getUserExhaustionLogSql->num_rows() > 0){
			$userLevelAndAccData = $getUserExhaustionLogSql->row();
			$this->updateFreeQuesAttemptCount($userLevelAndAccData);	
		}
		else{
			$this->calFreeQuesAccAndSetExhaustionLog($level);
		}			
	}

	//greater than or equal to condition added for offline cases where count will be increased
	
	/**
	 * function description : updates free question attempt count in the exscoringID row for this user and calls the calExhaustFreeQuesAccAndChangeLevel function if attempt count reaches to psgsToShow 
	 * param1   userExhaustionData 
	 * param2   passageID 
	 * param3   contentType 
	 * @return  none.
	 * 
	 * */
	
	public function updateFreeQuesAttemptCount($userExhaustionData,$passageID,$contentType){
		$userExhaustionData->psgAttemptCount=$userExhaustionData->psgAttemptCount+1;
		$data=array('psgAttemptCount'=> $userExhaustionData->psgAttemptCount);
		$this->dbEnglish->where('userID', $userExhaustionData->userID);
		$this->dbEnglish->where('contentType', $userExhaustionData->contentType);
		$this->dbEnglish->where('exScoringID', $userExhaustionData->exScoringID);

		$this->dbEnglish->update('userexhaustionlogiclog', $data);
		//print $this->dbEnglish->last_query();
		if($this->dbEnglish->affected_rows() == 1){
			if($userExhaustionData->psgAttemptCount >= $userExhaustionData->psgsToShow){
				$this->calExhaustFreeQuesAccAndChangeLevel($userExhaustionData);

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
	 * function description : calculates free question exhuastion accuracy and change level and set content according to that 
	 * param1   userExhaustionData , user exhaustion free question row for the logged in user
	 * @return  none.
	 * 
	 * */
	
	public function calExhaustFreeQuesAccAndChangeLevel($userExhaustionData){
		$totalCorrects=0;		
		$this->dbEnglish->Select('p.qcode as qcode,p.correct as correct,q.accuracy as Accuracy,q.level as userLevel');
		$this->dbEnglish->from($this->questionAttemptClassTbl.' p');
		$this->dbEnglish->join('userexhaustionlogiclog q', 'p.exScoringID=q.exScoringID', 'inner');
		$this->dbEnglish->where('q.exScoringID',$userExhaustionData->exScoringID);
		$freeQuesDataSql = $this->dbEnglish->get();
		//print $this->dbEnglish->last_query();
		$freeQuesDataArr = $freeQuesDataSql->result_array();	
		//print_r($freeQuesDataArr);

		foreach ($freeQuesDataArr as $row)
		{	
			$totalCorrects=$totalCorrects+$row['correct'];
		}	
		
		$avgAccuracy=$totalCorrects/$userExhaustionData->psgsToShow*100;
		
		$data=array('accuracy'=> round($avgAccuracy,2));
		$this->dbEnglish->where('exScoringID', $userExhaustionData->exScoringID);
		$this->dbEnglish->where('contentType', $userExhaustionData->contentType);
		$this->dbEnglish->update('userexhaustionlogiclog', $data);
		$userLevel=$freeQuesDataArr[0]['userLevel'];
		
		if(($avgAccuracy <= 50 && $userLevel!=4) || ($avgAccuracy >= 50 && $userLevel==9))
			$userLevel=$userLevel-freeQuesLevelConst;	
		else if(($avgAccuracy >= 50 && $userLevel!=9) || ($avgAccuracy <= 50 && $userLevel==4))
			$userLevel=$userLevel+freeQuesLevelConst;
		
		$freeQuesMsLevel=$userLevel-3;
		$getCurrLevelFreeQuesCntSql=$this->dbEnglish->query('select count(*) as totalFreeQues from questions a LEFT JOIN '.$this->questionAttemptClassTbl.' b on a.qcode=b.qcode and b.userID='.$this->user_id.' and b.passageID=0 where a.passageID=0 and a.msLevel='.$freeQuesMsLevel.' and a.status=6 and a.skillID in (1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23) and b.qcode is null ');
		$currLevelFreeQuesCntData = $getCurrLevelFreeQuesCntSql->row();
		$currLevelFreeQuesCnt=$currLevelFreeQuesCntData->totalFreeQues;

		//Shivam- If currlevel freequestion count is 0 then call function to get the newlevel so as to make the correct entry in the table.
		if ($currLevelFreeQuesCnt == 0){
			list($userLevel, $currLevelFreeQuesCnt) = $this->getmslevel($userExhaustionData->userID);
		}
		
		$data = array(
				'userID' => $userExhaustionData->userID,
				'contentType' => $userExhaustionData->contentType,
				'accuracy' => 0,
				'level' => $userLevel,
				'psgsToShow' => $currLevelFreeQuesCnt
			);
		$this->dbEnglish->insert('userexhaustionlogiclog', $data);
		$this->session->unset_userdata('sessionfreeQues');
		$this->setNextSessionFreeQuestions($userExhaustionData->userID,true,$userLevel);
	}

/**
	 * function description : This function will be called for Getting the newmslevel and no of 
	 						unattempted free questions which needs to be set in the userexhaustionlogiclog table.
	 * param1   userid
	 * @return  array.
	 * 
	 *
*/
	public function getmslevel($userID){

		$this->dbEnglish->Select('count(distinct(a.qcode)) as unattempt_question, a.mslevel as newmslevel ');
		$this->dbEnglish->from('questions a ');
		$this->dbEnglish->join($this->questionAttemptClassTbl." b", 'a.qcode = b.qcode and b.userID ='.$userID, 'left outer');
		$this->dbEnglish->where('a.status',6);
		$this->dbEnglish->where('a.passageID',0);
		$this->dbEnglish->where('b.qcode is null');
		$this->dbEnglish->group_by('newmslevel');
		$this->dbEnglish->order_by("unattempt_question desc");
		$this->dbEnglish->limit(1);

		$getmaxunattempt = $this->dbEnglish->get();

		$maxquesPending = 0;	
		$maxquesPending=$getmaxunattempt->num_rows();

		//Get the new ms level which we need to set in the userexhaustionlogic log.
		if($maxquesPending>0){

			$row = $getmaxunattempt->row();

			$newlevel = $row->newmslevel;
			$newpsgstoshow = $row->unattempt_question;

			//Change the newlevel as per our mindspark logic.
			$newlevel = $newlevel + 3;
			
		}
		else{
			//In worst scenereo if no row is present then give alert to user.
			echo '<script language="javascript">';
			echo 'alert("No free questions are available.")';
			echo '</script>';
			return;
		}

		//Return the array which contains the newlevel and no of unattempted questions to show.
		return array($newlevel, $newpsgstoshow);
		
	}

/*
	 * function description : This function will insert/update the userexhaustionlogiclog table for
	 						 the mslevel for which maximum no of questions are available to attempt.
	 * param1   userid
	 * @return  none.
	 * 
*/
	public function updateOrInsertAfterNoQuesleftinExhaustion($userID){

		//Get the new level and passages to show in the variable.
		list($newlevel, $psgtoshow) = $this->getmslevel($userID);

		$this->dbEnglish->select('exScoringID');
		$this->dbEnglish->from('userexhaustionlogiclog');
		$this->dbEnglish->where('psgsToShow',0);
		$this->dbEnglish->where('accuracy',0);
		$this->dbEnglish->where('contentType',"Free_Question");
		$this->dbEnglish->where('userid',$userID);
		$this->dbEnglish->order_by('exScoringID','desc');
		$this->dbEnglish->limit(1);
		$checkifexists = $this->dbEnglish->get();

		$row = $checkifexists->row();

		$rowcount = 0;	
		$rowcount=$checkifexists->num_rows();

		If($rowcount == 1){
			//If row is present then update the same row.
			$data=array('level'=> $newlevel,'psgsToShow'=>$psgtoshow );
			$this->dbEnglish->where('exScoringID',$row->exScoringID);
			$this->dbEnglish->update('userexhaustionlogiclog', $data);

		}else{

			//Insert row in the table if it is not present. 
			$data = array(
					'userID' => $userID,
					'contentType' => 'Free_Question',
					'accuracy' => 0,
					'level' => $newlevel,
					'psgsToShow' => $psgtoshow
				);
			$this->dbEnglish->insert('userexhaustionlogiclog', $data);

		}
		//Call the below function to set the free questions.
		$this->setNextSessionFreeQuestions($userID,true,$newlevel);

	}

	public function bunchingOrderFlow($bunchingjson,$childClass)
	{
		$currentOrder = "";
		$json = json_decode($bunchingjson);
		$data = $json->bunchids->class;
		foreach ($data as $key => $value) {
		if($key==$childClass) :
			$currentOrder = $value->orders;
		endif;
		}
		return $currentOrder;
  	}

  public function nextSchoolBunchingOrder($schoolCode,$childClass,$groupSkillID){
    $bunchsql = "select bunchOrder from schoolBunchingOrder where schoolCode = '$schoolCode'";
	$bunching_order = $this->dbEnglish->query($bunchsql)->row();
	if($bunching_order) : 
	  $currentBunching = $this->bunchingorderFlow($bunching_order->bunchOrder,$childClass);
	endif;
    return $currentBunching;
  }
   public function selectAttmptdQstnCurrentBnchQuery($currentBunch,$userID,$qstnAtmptClss) {
    $selectAttmptdQstnCurrentBnch="select distinct (qcode) as questionsAttempted from $qstnAtmptClss qa where passageID=0 and userID=".$userID." AND bunchID=$currentBunch"; 
	return $this->dbEnglish->query($selectAttmptdQstnCurrentBnch)->result_array();
  }

    //function to get next bunch Id
  public function findNextBunchID($schoolBunchingOrder,$currentBunch,$userInfo) {        
	$schoolBunchingFlow = $this->nextSchoolBunchingOrder($userInfo['schoolCode'],$userInfo['childClass'],$userInfo['groupSkillID']);
	if($currentBunch!=0) :
		$arrLast = array_slice($schoolBunchingFlow, array_search($currentBunch,$schoolBunchingFlow)+1);
		$arrInit = array_slice($schoolBunchingFlow, 0,array_search($currentBunch,$schoolBunchingFlow)+1);
		$finalbunchFlowArr = implode($arrInit,",");
	else :
		$finalbunchFlowArr = $schoolBunchingFlow;
	endif;
	return $finalbunchFlowArr[0];
  }

  //function to conver array into string
  function SplitArrayValue($result){
    $values = array_map('array_pop', $result);
    return implode(',', $values);
  }

   //function to get Attempted Bunching Qcode
  public function getAttemptedBunchingQcode($userID,$newBunchingID,$currentAttempFlowCount,$qstnAtmptClss) {
    $nextFlowCount = $currentAttempFlowCount + 1;
    $SQL = "select qcode from $qstnAtmptClss where userID=$userID and passageID=0 and bunchID=$newBunchingID and attemptCount =$nextFlowCount ";
    return  $this->dbEnglish->query($SQL)->result();  
  }

}
