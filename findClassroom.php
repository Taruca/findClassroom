<?php

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
        $classroomStr = "<strong>今天教三的自习室分布情况：</strong><br>";
        if ($mANFlag) {
        	$classroomStr .= "全天都没有课的自习室有：<br>";
        	foreach ($classroom['man'] as $key => $value) {
        		$classroomStr .= "<strong>" .$value ."</strong>" .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；<br>";
        } 
        if ($mAFlag) {
        	$classroomStr .= "上午和下午没有课的自习室有：<br>";
        	foreach ($classroom['ma'] as $key => $value) {
        		$classroomStr .= "<strong>" .$value ."</strong>" .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；<br>";
        } 
        if($aNFlag) {
        	$classroomStr .= "下午和晚上都没有课的自习室有：<br>";
        	foreach ($classroom['an'] as $key => $value) {
        		$classroomStr .= "<strong>" .$value ."</strong>" .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；<br>";
        } 
        if($morningFlag) {
        	$classroomStr .= "上午没有课的自习室有：<br>";
        	foreach ($suitableClassroom['morning'] as $key => $value) {
        		$classroomStr .= "<strong>" .$value ."</strong>" .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；<br>";
        } 
        if($afternoonFlag) {
        	$classroomStr .= "下午没有课的自习室有：<br>";
        	foreach ($suitableClassroom['afternoon'] as $key => $value) {
        		$classroomStr .= "<strong>" .$value ."</strong>" .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；<br>";
        } 
        if($nightFlag) {
        	$classroomStr .= "晚上没有课的自习室有：<br>";
        	foreach ($suitableClassroom['night'] as $key => $value) {
        		$classroomStr .= "<strong>" .$value ."</strong>" .',';
        	}
        	$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
        	$classroomStr .= "；<br>";
        }
		$classroomStr .= "第九节课没有课的教室有：<br>";
		foreach ($classroomArr[4][3] as $key => $value) {
			$classroomStr .= "<strong>" .$value ."</strong>" .',';
		}
		$classroomStr = substr($classroomStr, 0, strlen($classroomStr) - 1);
		$classroomStr .= "；<br>";

        $emailStr = "今天是" .$dateStr ."<br>";
		$emailStr .= "<hr>";

		$emailStr .= $classroomStr;
		$emailStr .= '<hr>';
		$emailStr .= "<strong>详细课程情况：</strong><br>";
		$emailStr .= $htmlStr;
		$emailStr .= '<hr>';
        $emailStr .= '教务网站: <a>http://jwxt.bupt.edu.cn/zxqDtKxJas.jsp</a>';

//		$toAddress = 'fujiale1993@163.com';
		$toAddress = '395158242@qq.com';
		echo sendMail($emailStr, $toAddress);
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
		$dateStr = '<font color=red>' .$dateArr[0] .'</font>' .'年' .'<font color=red>' .$dateArr[1] .'</font>' .'月' .'<font color=red>' .$dateArr[2] .'</font>' .'日';
		return $dateStr;
	}

	function sendMail($content, $toAddress) {
		header("content-type:text/html;charset=utf-8");
		ini_set("magic_quotes_runtime",0);
		require_once 'class.phpmailer.php';

		try {
			$mail = new PHPMailer(true);
			$mail->IsSMTP();
			$mail->CharSet='UTF-8'; //设置邮件的字符编码，这很重要，不然中文乱码
			$mail->SMTPAuth   = true;                  //开启认证
			$mail->Port       = 25;
			$mail->Host       = "smtp.163.com";
			$mail->Username   = "liuziyang_lzy@163.com";
			$mail->Password   = "wy2716190";
			//$mail->IsSendmail(); //如果没有sendmail组件就注释掉，否则出现“Could  not execute: /var/qmail/bin/sendmail ”的错误提示
			$mail->AddReplyTo("liuziyang_lzy@163.com","lzy");//回复地址
			$mail->From       = "liuziyang_lzy@163.com";
			$mail->FromName   = "刘子阳";
			$to = $toAddress;
			$mail->AddAddress($to);
			$mail->Subject  = "亲爱滴";
			$mail->Body = $content;
//			$mail->Body = '<h1>phpmail演示</h1>这是php点点通（<font color=red>www.phpddt.com</font>）对phpmailer的测试内容<br>aaa';
			$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; //当邮件不支持html时备用显示，可以省略
			$mail->WordWrap   = 80; // 设置每行字符串的长度
			//$mail->AddAttachment("f:/test.png");  //可以添加附件
			$mail->IsHTML(true);
			$mail->Send();
			return '邮件已发送';
		} catch (phpmailerException $e) {
			return "邮件发送失败：".$e->errorMessage();
		}
	}