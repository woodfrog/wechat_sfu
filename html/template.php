<?php 
	check_upcoming_courses(30);


	function check_upcoming_courses($minutes) {
		require_once("/var/www/includes/wechat_help.php");
		$date = time();
		$now = intval(getdate($date)["hours"], 10) * 60 + intval(getdate($date)["minutes"], 10); // numeric representation of the current minitue of the day
		
		$dayMask = (intval(getdate($date)["wday"], 10) + 6) % 7;
		$dayMask = pow(2, $dayMask);
		$date = sprintf("%d-%02d-%02d", getdate($date)["year"], getdate($date)["mon"], getdate($date)["mday"]);
		
		
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		$ret = "";
		if (!$conn) { // fail to connect to the database
			return "无法连接到数据库";
		}
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		$sql = "SELECT uid, class_name, description, start_time, end_time, location, professor, (((start_time)%100)+floor(start_time/100)*60)-{$now} as difference " .
				"FROM courses " .
				"WHERE validity = true " .
				"AND (((start_time)%100)+floor(start_time/100)*60) - {$now} > 0 " .
				"AND (((start_time)%100)+floor(start_time/100)*60) - {$now} <= {$minutes} " . 
				"AND start_date <= \"{$date}\" " .
				"AND end_date >= \"{$date}\" " .
				"AND day&{$dayMask} " .
				"AND uid NOT IN (SELECT uid FROM no_reminder_users) " . 
				"ORDER BY start_time;";
		echo $sql;
		$result = mysql_query($sql, $conn);
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$courseInfo = array("countDown" => "{$row["difference"]}分钟",
								"name" => $row["class_name"] . "({$row["description"]})", 
								"professor" => $row["professor"], 
								"location" => $row["location"], 
								"time" => add_colon_to_time($row["start_time"]) . "-" . add_colon_to_time($row["end_time"]));
			send_template_message(get_openid($row["uid"]), $courseInfo);
		}
		mysql_close($conn); // close the connection to the database
	}
	
	

	/* courseInfo is an associative array, with key "countDown", "name", "professor", "location" and "time" */
	function send_template_message($openid, $courseInfo) {
		require_once("/var/www/includes/wechat_help.php");
		$accessToken = get_access_token();
		if ($accessToken != -1) { // no error occurs
			$url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=" . $accessToken;
			
			$postData = array("touser" => $openid,
						"template_id" => "ewANIcgyUA52n5oTKOlPZiTFa-MIGOusFH1d2WV3iz4", 
						"data" => array("countDown" => array("value" => $courseInfo["countDown"],
																"color" => "#000000"),
										"name" => array("value" => $courseInfo["name"],
														"color" => "#000000"),
										"professor" => array("value" => $courseInfo["professor"],
														"color" => "#000000"),
										"location" => array("value" => $courseInfo["location"],
														"color" => "#000000"),
										"time" => array("value" => $courseInfo["time"],
														"color" => "#000000")
								));
			$postFields = json_encode($postData);
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);//0显示
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//1不显示
			curl_setopt($ch, CURLOPT_POST, 1);//POST数据
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);//加上POST变量
			curl_exec($ch);
			curl_close($ch);
			
		}
	}

?>
