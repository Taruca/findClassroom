<?php
	require_once 'smtp.php';

 	$ch = curl_init();

 	curl_setopt($ch,CURLOPT_URL,"http://jwxt.bupt.edu.cn/zxqDtKxJas.jsp");
 	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
 	curl_setopt($ch,CURLOPT_HEADER,0);

 	$html = curl_exec($ch);

 	$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($response_code <> '200') 
		$message = "无法获得".$url."上的数据，错误代码:". $response_code. "<br>" ;
	else {
		//防止中文乱码
        $htmlStr = mb_convert_encoding($html, 'utf-8', 'GBK,UTF-8,ASCII');

        //获取页面中的时间
        $dateFlag = preg_match('/\d{4}年\d{1,2}月\d{1,2}日/U', $htmlStr, $dateMatches);
        if (!$dateFlag) {
        	$dateStr = getCurrentDate();
        } else {
			$dateStr = $dateMatches[0];
        }

        //滤除冗余table，只留下最后一个有用的table
        $tableStr = getStringBetween($htmlStr, "pageAlign", "</td>");
        // echo "$tableStr";
        $tableStr = str_replace("\r\n", '', $tableStr); //清除换行符 
		$tableStr = str_replace("\n", '', $tableStr); //清除换行符 
		$tableStr = str_replace("\t", '', $tableStr); //清除制表符 
		$tableStr = str_replace(" ", '', $tableStr); //清除空格符 

		//按课程时间截取字符串，存入
        $courseArr = array();
        while ($spos = stripos($tableStr, '<tr>')) {
        	$epos = stripos($tableStr, '</tr>');

           	$courseArr[] = substr($tableStr, $spos + 4, $epos - $spos + 1);
        	$tableStr = substr($tableStr, $epos + 4);
        }

        //$classroomArr[$courseTime][$building][$classroom]
        $classroomArr = array();
        foreach ($courseArr as $key => $value) {
        	// preg_match_all("/教[\s\S]*楼[\s\S]*<br>/U", $value, $matches);
        	preg_match_all("/\d-\d{3}/U", $value, $matches);
        	foreach ($matches[0] as $k => $v) {
        		$arr = explode('-', $v);
        		$classroomArr[$key][$arr[0]][] = $arr[1];
        	}
        }
		
        $suitableClassroom = array();
        //查找上午连续的课程
        $morningFlag = hasSameClass($classroomArr[1][3], $classroomArr[0][3], $suitableClassroom['morning']);
        //查找下午连续课程
        $afternoonFlag = hasSameClass($classroomArr[3][3], $classroomArr[2][3], $suitableClassroom['afternoon']);
        //查找晚上连续课程
        $nightFlag = hasSameClass($classroomArr[5][3], $classroomArr[4][3], $suitableClassroom['night']);

        $classroom = array();
        $mAFlag = hasSameClass($suitableClassroom['afternoon'], $suitableClassroom['morning'], $classroom['ma']);
        $aNFlag = hasSameClass($suitableClassroom['afternoon'], $suitableClassroom['night'], $classroom['an']);
        $mANFlag = hasSameClass($classroom['ma'], $classroom['an'], $classroom['man']);
        
        //根据数组拼字符串
        $classroomStr = "今天教三的自习室分布情况：\n";
        if ($mANFlag) {
        	$classroomStr .= "全天都没有课的自习室有：";
        	foreach ($classroom['man'] as $key => $value) {
        		$classroomStr .= $value .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；\n";
        } 
        if ($mAFlag) {
        	$classroomStr .= "上午和下午没有课的自习室有：";
        	foreach ($classroom['ma'] as $key => $value) {
        		$classroomStr .= $value .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；\n";
        } 
        if($aNFlag) {
        	$classroomStr .= "下午和晚上都没有课的自习室有：";
        	foreach ($classroom['an'] as $key => $value) {
        		$classroomStr .= $value .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；\n";
        } 
        if($morningFlag) {
        	$classroomStr .= "上午没有课的自习室有：";
        	foreach ($suitableClassroom['morning'] as $key => $value) {
        		$classroomStr .= $value .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；\n";
        } 
        if($afternoonFlag) {
        	$classroomStr .= "下午没有课的自习室有：";
        	foreach ($suitableClassroom['afternoon'] as $key => $value) {
        		$classroomStr .= $value .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；\n";
        } 
        if($nightFlag) {
        	$classroomStr .= "晚上没有课的自习室有：";
        	foreach ($suitableClassroom['night'] as $key => $value) {
        		$classroomStr .= $value .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；\n";
        }

        $emailStr = "今天是" .$dateStr ."\n";
        if (!($mANFlag || $mAFlag || $aNFlag || $morningFlag || $afternoonFlag || $nightFlag)) {
        	$emailStr .= '今天没有连续的自习室，去教务找找其他教学楼吧。';
        } else {
			$emailStr .= $classroomStr;
        }
        $emailStr .= '教务网站:http://jwxt.bupt.edu.cn/zxqDtKxJas.jsp';
        echo "$emailStr";

		/**
		 *实例化邮件类
		 */
		$smtpserver = "smtp.163.com";              //SMTP服务器
		$smtpserverport = 25;                      //SMTP服务器端口
		$smtpusermail = "liuziyang_lzy@163.com";      //SMTP服务器的用户邮箱
		$smtpemailto = "395158242@qq.com";       //发送给谁
		$smtpuser = "liuziyang_lzy@163.com";         //SMTP服务器的用户帐号
		$smtppass = "wy2716190";                 //SMTP服务器的用户密码
		$mailsubject = "测试邮件系统";        //邮件主题
		$mailbody = "<h1>你的用户名是张三，密码是123147mcl </h1>";      //邮件内容
		$mailtype = "HTML";                      //邮件格式（HTML/TXT）,TXT为文本邮件
		$smtp = new smtp($smtpserver, $smtpserverport, true, $smtpuser, $smtppass);
		$smtp->debug = true;                     //是否显示发送的调试信息
		$smtp->sendmail($smtpemailto, $smtpusermail, $mailsubject, $mailbody, $mailtype);
	}

	//获取从$sMark开始到$sMark后最后一个$eMark为止的字符串
	function getStringBetween($str, $sMark, $eMark)
	{
		$spos = stripos($str, $sMark);
		$strTemp = substr($str, $spos);
		$epos = strrpos($strTemp, $eMark);

		if ($spos == false || $epos == false) {
			return false;
		} else {
			$betweenStr = substr($strTemp, 0, $epos);
			return $betweenStr;
		}
	}

	//判空
	function isNullOrEmpty($obj) {
    	return (!isset($obj) || empty($obj) || $obj == null);
	}

	//检查是否有相同教室
	function hasSameClass($needleArr, $targetArr, &$resultArr)
	{
		foreach ($needleArr as $key => $value) {
        	if (in_array($value, $targetArr)) {
        	 	$resultArr[] = $value;
        	}
        }
        return !isNullOrEmpty($resultArr);
	}

	//得到'x年x月x日'格式当前时间
	function getCurrentDate()
	{
		$date = date('Y-m-d');
		$dateArr = explode('-', $date);
		$dateStr = $dateArr[0] .'年' .$dateArr[1] .'月' .$dateArr[2] .'日';
		return $dateStr;;
	}
?>