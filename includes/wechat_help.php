<?php 

	// database login information
	define("DB_HOST", "115.28.188.144");
	define("DB_USER", "root");
	define("DB_PWD", "password");
	define("DB_NAME", "wechat_sfu");

	define("CUR_YEAR", "2016");
	define("CUR_SEASON", "Fall");
	//echo update_course_info(1);
	
	define("MIN_ACCESS_TOKEN_LIFE", 200);
	define("APPID", "wx74ba47e83501fda6");
	define("APPSECRET", "9921c0b100dc9a490f5964d79da36042");
	define("ACCESS_TOKEN_FILE", "/var/www/access_token");
	//get_access_token();
	
	function get_schedule($uid, $date) {
		$dayMask = (intval(getdate($date)["wday"], 10) + 6) % 7;
		$dayMask = pow(2, $dayMask);
		$date = sprintf("%d-%02d-%02d", getdate($date)["year"], getdate($date)["mon"], getdate($date)["mday"]);
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		$ret = "";
		if (!$conn) { // fail to connect to the database
			return "无法连接到数据库";
		}
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		$sql = "SELECT class_name, component, description, start_time, end_time, location, professor " .
				"FROM courses " .
				"WHERE uid = \"{$uid}\" " .
				"AND validity = true " .
				"AND start_date <= \"{$date}\" " .
				"AND end_date >= \"{$date}\" " .
				"AND day&{$dayMask} " . 
				"ORDER BY start_time;";
		$result = mysql_query($sql, $conn);
		if ($result === false) {
			// MySQL returns false, indicating an error
			$msg = mysql_error();
			mysql_close($conn); // close the connection to the database
			return "与数据库的连接出现异常：" . $msg;
		} 
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$ret .= "********课程信息*********\n" .
					"名称: {$row["class_name"]}({$row["description"]})\n" .
					"类型: {$row["component"]}\n" .
					"时间: " . add_colon_to_time($row["start_time"]) . "-" . add_colon_to_time($row["end_time"]) . "\n" .
					"地点: {$row["location"]}\n" .
					"教授: {$row["professor"]}\n" . 
					"------------------------\n\n";
		}
		mysql_close($conn); // close the connection to the database
		if ($ret == "") {
			$ret = "今天没有课";
		}
		return $ret;
	}
	
	function get_routine($openid) {
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		if (!$conn) { // fail to connect to the database
			return "无法连接到数据库";
		}
		mysql_set_charset("utf8", $conn);
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		
		// find out if the user is in registration process
		$sql = "SELECT * FROM register WHERE openid = \"{$openid}\";";
		$result = mysql_query($sql, $conn);
		if ($result === false) {
			// MySQL returns false, indicating an error
			mysql_close($conn); // close the connection to the database
			return "与数据库的连接出现异常：" . mysql_error();
		} 
		if (mysql_num_rows($result) != 1) {
			mysql_close($conn); // close the connection to the database
			return "normal";
		} else {
			$row = mysql_fetch_row($result);
			$user = $row[1];
			$pwd = $row[2];
			mysql_close($conn); // close the connection to the database
			if ($user == "") {
				return "register_username";
			} else if ($pwd == "") {
				return "register_password";
			} else {
				return "register_confirm";
			}
		}
	}
	
	function get_username($openid) {
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		if (!$conn) { // fail to connect to the database
			return "无法连接到数据库";
		}
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		$sql = "SELECT username FROM users WHERE openid = \"{$openid}\";";
		$result = mysql_query($sql, $conn);
		if ($result === false) {
			// MySQL returns false, indicating an error
			$msg = mysql_error();
			mysql_close($conn); // close the connection to the database
			return "与数据库的连接出现异常：" . $msg;
		} 
		if (mysql_num_rows($result) > 0) {
			// username is found
			$row = mysql_fetch_row($result);
			$username = $row[0];
			mysql_close($conn); // close the connection to the database
			return "您当前注册的账号为：{$username}";
		} else {
			mysql_close($conn); // close the connection to the database
			return "您还没有注册";
		}
	}
	
	function clear_user($openid) {
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		if (!$conn) { // fail to connect to the database
			return "无法连接到数据库";
		}
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		$sql = "DELETE FROM users WHERE openid = \"{$openid}\";";
		mysql_query($sql, $conn);
		$count = mysql_affected_rows($conn);
		mysql_close($conn);
		if ($count == 0) {
			return "数据库中没有您的记录";
		} else {
			return "注销成功";
		}
	}

	function cancel_reminder($openid) {
		$uid = get_uid($openid);
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD);
		if (!$conn) {
			return "无法连接到数据库";
		}
		mysql_select_db(DB_NAME, $conn);
		$sql = "INSERT INTO no_reminder_users(openid, uid) VALUES(\"{$openid}\", {$uid});";
		if (mysql_query($sql) == false) { // insertion fails
			$err_no = mysql_errno();
			if ($err_no == "1062") { 
				return "课程提醒已经处于关闭状态";
			}
			else{
				$err_msg = mysql_error();
				mysql_close($conn);
				return $err_msg;	
			}
			
		} else {
			mysql_close($conn);
			return "已取消课程提醒";
		}
	}

	function add_reminder($openid){
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD);
		if (!$conn){
			return "无法连接到数据库";
		}
		mysql_select_db(DB_NAME, $conn);
		$sql = "DELETE FROM no_reminder_users WHERE openid = \"{$openid}\";";
		mysql_query($sql, $conn);
		if (mysql_affected_rows() == 0){
			$msg = "课程提醒已经处于开启状态";
		}
		else{
			$msg = "课程提醒开启成功";
		}
		mysql_close($conn);
		return $msg;
	}
	
	function register($openid, $keyType, $key = "") {
		switch ($keyType) {
			case "first": {
				$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
				if (!$conn) { // fail to connect to the database
					return "无法连接到数据库";
				}
				mysql_set_charset("utf8", $conn);
				mysql_select_db(DB_NAME, $conn); // switch to the specific database
				
				// check if the user has already registered
				$sql = "SELECT username FROM users WHERE openid = \"{$openid}\";";
				$result = mysql_query($sql, $conn);
				if ($result === false) {
					// MySQL returns false, indicating an error
					mysql_close($conn); // close the connection to the database
					return "与数据库的连接出现异常：" . mysql_error();
				} 
				if (mysql_num_rows($result) > 0) {
					// this openid has already registered
					$row = mysql_fetch_row($result);
					$username = $row[0];
					mysql_close($conn); // close the connection to the database
					return "您已经注册为：{$username}。若要重新注册，请先注销当前账号";
				} 
				
				$sql = "INSERT INTO register(openid) VALUES(\"{$openid}\");";
				if (mysql_query($sql) === false) {
					$msg = mysql_error();
					mysql_close($conn);
					return "注册失败：" . $msg;
				} else {
					mysql_close($conn);
					return "请输入用户名（SFU Computing ID）";
				}
			}
			case "username": {
				$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
				if (!$conn) { // fail to connect to the database
					return "无法连接到数据库";
				}
				mysql_set_charset("utf8", $conn);
				mysql_select_db(DB_NAME, $conn); // switch to the specific database
				$key = mysql_real_escape_string($key);
				$sql = "UPDATE register SET username = \"{$key}\" WHERE openid = \"{$openid}\";";
				if (!mysql_query($sql)) {
					$msg = mysql_error();
					mysql_close($conn);
					return "注册失败：" . $msg;
				} else {
					mysql_close($conn);
					return "用户名已记录，请输入密码";
				}
			}
			case "password": {
				$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
				if (!$conn) { // fail to connect to the database
					return "无法连接到数据库";
				}
				mysql_set_charset("utf8", $conn);
				mysql_select_db(DB_NAME, $conn); // switch to the specific database
				$sql = "SELECT username FROM register WHERE openid = \"{$openid}\";";
				$result = mysql_query($sql, $conn);
				if ($result === false) {
					// MySQL returns false, indicating an error
					$msg = mysql_error();
					mysql_close($conn); // close the connection to the database
					return "与数据库的连接出现异常：" . $msg;
				} 
				if (mysql_num_rows($result) != 1) {
					mysql_close($conn); // close the connection to the database
					return "注册异常";
				} 
				$row = mysql_fetch_row($result);
				$username = $row[0];
				$key = mysql_real_escape_string($key);
				$sql = "UPDATE register SET password = \"{$key}\" WHERE openid = \"{$openid}\";";
				$flag = mysql_query($sql, $conn);
				mysql_close($conn);
				if ($flag) {
					return "您的账号是：" . $username . " 密码是：" . $key . "。确认请回复1；如要取消，请回复其他任意字符串";
				} else {
					return "注册异常";
				}
			}
			case "confirm": {
				$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
				if (!$conn) { // fail to connect to the database
					return "无法连接到数据库";
				}
				mysql_set_charset("utf8", $conn);
				mysql_select_db(DB_NAME, $conn); // switch to the specific database
				$sql = "SELECT username, password FROM register WHERE openid = \"{$openid}\";";
				$result = mysql_query($sql, $conn);
				if ($result === false) {
					// MySQL returns false, indicating an error
					mysql_close($conn); // close the connection to the database
					return "与数据库的连接出现异常：" . mysql_error();
				} 
				if (mysql_num_rows($result) != 1) {
					mysql_close($conn); // close the connection to the database
					return "注册异常";
				} 
				$row = mysql_fetch_row($result);
				$username = $row[0];
				$password = $row[1];
				
				$flag = true;
				if ($key == "1") { // confirm
					$sql = "INSERT INTO users(openid, username, password) VALUES (\"{$openid}\", \"{$username}\", \"{$password}\");";
					$flag = mysql_query($sql);
				} 
				$sql = "DELETE FROM register WHERE openid = \"{$openid}\";"; // delete record in register table
				$flag = $flag && mysql_query($sql);
				mysql_close($conn);
				if ($flag) {
					return "操作成功";
				} else {
					// if the user already had an account, and would like to create a new account
					// it will fails
					// record in the register table is cleaned, and no change is made to the users table
					return "操作失败"; 
				}
			}
			default: return "注册异常";
		}
	}
	
	function get_openid($uid) {
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		if (!$conn) { // fail to connect to the database
			return -1;
		}
		mysql_set_charset("utf8", $conn);
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		$sql = "SELECT openid FROM users WHERE uid = \"{$uid}\";";
		$result = mysql_query($sql, $conn);
		if ($result === false) {
			// MySQL returns false, indicating an error
			mysql_close($conn); // close the connection to the database
			return -1;
		} else if (mysql_num_rows($result) != 1) {
			mysql_close($conn); // close the connection to the database
			return -1; 
		} else {
			$row = mysql_fetch_row($result);
			$openid = $row[0];
			//mysql_close($conn);
			return $openid;
		}
	}

	function get_uid($openid) {
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		if (!$conn) { // fail to connect to the database
			return -1;
		}
		mysql_set_charset("utf8", $conn);
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		$sql = "SELECT uid FROM users WHERE openid = \"{$openid}\";";
		$result = mysql_query($sql, $conn);
		if ($result === false) {
			// MySQL returns false, indicating an error
			mysql_close($conn); // close the connection to the database
			return -1;
		} else if (mysql_num_rows($result) != 1) {
			mysql_close($conn); // close the connection to the database
			return 1; // the test account
		} else {
			$row = mysql_fetch_row($result);
			$uid = $row[0];
			mysql_close($conn);
			return $uid;
		}
	}
	/*
		Update user's course information in the database. A string is returned to indicate whether the update is successful
	*/
	function update_course_info($uid) {
		
		$conn = mysql_connect(DB_HOST, DB_USER, DB_PWD); // establish connection to the database
		if (!$conn) { // fail to connect to the database
			return "无法连接到数据库";
		}
		mysql_set_charset("utf8", $conn);
		mysql_select_db(DB_NAME, $conn); // switch to the specific database
		
		// find out the login account for that openid in the database
		$sql = "SELECT username, password " .
				"FROM users " .
				"WHERE uid = {$uid};";
		$result = mysql_query($sql, $conn);
		if ($result === false) {
			// MySQL returns false, indicating an error
			$msg = mysql_error();
			mysql_close($conn); // close the connection to the database
			return "与数据库的连接出现异常：" . $msg;
		} 
		if (mysql_num_rows($result) != 1) {
			mysql_close($conn); // close the connection to the database
			return "数据库中没有发现该用户的登录信息";
		} 
		$row = mysql_fetch_row($result);
		$user = $row[0];
		$pwd = $row[1];
		
		// fetch the user's schedule from SFU's website
		$schedule = fetch_course_schedule($user, $pwd);
		
		
		// cancel existing schedule
		$sql = "UPDATE courses " .
				"SET validity = false " .
				"WHERE uid = {$uid};";

		if (!mysql_query($sql)) {
			$msg = mysql_error();
			mysql_close($conn); // close the connection to the database
			return "无法从数据库中删除原有课程信息：". $msg;
		}
		
		// insert the schedule into the database
		if (count($schedule) > 0) {
			$sql = "INSERT INTO courses(uid, class_name, class_number, section, component, description, grading_option, grade, units, status, start_time, end_time, day, location, start_date, end_date, professor, validity) 
				VALUES";
			foreach ($schedule as $course) {
				$sql .= "(" . $uid . ", " .
						"\"{$course["class_name"]}\" ," .
						"\"{$course["class_number"]}\" ," .
						"\"{$course["section"]}\" ," .
						"\"{$course["component"]}\" ," .
						"\"{$course["description"]}\" ," .
						"\"{$course["grading_option"]}\" ," .
						"\"{$course["grade"]}\" ," .
						"\"{$course["units"]}\" ," .
						"\"{$course["status"]}\" ," .
						"\"{$course["start_time"]}\" ," .
						"\"{$course["end_time"]}\" ," .
						"\"{$course["day"]}\" ," .
						"\"{$course["location"]}\" ," .
						"\"{$course["start_date"]}\" ," .
						"\"{$course["end_date"]}\" ," .
						"\"{$course["professor"]}\" ," .
						"true)," ;
			}
			$sql[strlen($sql)-1] = ';';
			if (!mysql_query($sql)) {
				$msg = mysql_error();
				mysql_close($conn); // close the connection to the database
				return "无法在数据库中插入新的课程信息：". $msg;
			}
		} 
		mysql_close($conn); // close the connection to the database
		return "更新成功！";
	}
	
	
	
	
	
	function fetch_course_schedule($user, $pwd) {
		// login parameters
		$timezoneOffset = "-480";
		$userid = strtoupper($user);
		$loginURL = "https://go.sfu.ca/psp/paprd/EMPLOYEE/EMPL/?cmd=login";
		$cookieFile =tempnam("../temp", "cookie");
		
		// post fields
		$postFields =	"timezoneOffset=" . $timezoneOffset . 
			"&user=" . $user . 
			"&pwd=" . $pwd . 
			"&userid=" . $userid . 
			"&Submit=Login";
			
		// login
		// Login error is NOT dealt with, should be modified later
		$ch = curl_init($loginURL);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36");
		curl_setopt($ch, CURLOPT_HEADER, 0);//0显示
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//1不显示
		curl_setopt($ch, CURLOPT_POST, 1);//POST数据
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);//保存cookie
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);//加上POST变量
		curl_exec($ch);
		curl_close($ch);
		
		// Retrieve schedule page
		$url = "https://sims-prd.sfu.ca/psc/csprd_5/EMPLOYEE/HRMS/c/SA_LEARNER_SERVICES.SS_ES_STUDY_LIST.GBL";
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36");
		curl_setopt($ch, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
		$data =	curl_exec($ch); // The entire page, in HTML
		curl_close($ch);
		
		// Parse the HTML data
		require("../includes/parse.php");
		$html = new simple_html_dom();
		$html->load($data);
		// fetch all courses one by one
		$course = 0;
		$node = $html->find("span[id=DERIVED_SSE_DSP_CLASS_DESCR\$span\${$course}]", 0); // class_name, 0 means the first one
		$table = $html->find("table[id=ACE_\$ICField69\${$course}]", 0); // the table
		$rowCount = 0;
		$profId = 0;
		$ret = array();
		while ($node !== null && $table !== null) { // found another course
			$newRecord = array();
			$newRecord["class_name"] = trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=STDNT_ENRL_SSVW_CLASS_NBR\${$course}]", 0); // class_number
			$newRecord["class_number"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=CLASS_TBL_VW_CLASS_SECTION\${$course}]", 0); // section
			$newRecord["section"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=PSXLATITEM_XLATSHORTNAME\$56\$\${$course}]", 0); // component
			$newRecord["component"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=CLASS_TBL_VW_DESCR\${$course}]", 0); // description
			$newRecord["description"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=GRADE_BASIS_TBL_DESCRFORMAL\${$course}]", 0); // grading_option
			$newRecord["grading_option"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=STDNT_ENRL_SSVW_CRSE_GRADE_OFF\${$course}]", 0); // grade
			$newRecord["grade"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=STDNT_ENRL_SSVW_UNT_TAKEN\${$course}]", 0); // units
			$newRecord["units"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			$node = $html->find("span[id=PSXLATITEM_XLATSHORTNAME\${$course}]", 0); // status
			$newRecord["status"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
			if (strstr($newRecord["status"], "Dropped") !== false) {
				$rowCount += 1; // dropped record takes one row
				$course += 1; // to next course
				$node = $html->find("span[id=DERIVED_SSE_DSP_CLASS_DESCR\$span\${$course}]", 0); // class_name, 0 means the first one
				$table = $html->find("table[id=ACE_\$ICField69\${$course}]", 0); // the table
				continue;
			} else {
				// retrieve stuffs in table
				$node = $table->find("span[id=CLASS_MTG_VW_MEETING_TIME_START\${$rowCount}]", 0); // start_time
				while ($node != null) { // time information is found
					$newRecord["start_time"] = parse_time(trim_html_escape_chars($node->plaintext));
					$node = $table->find("span[id=CLASS_MTG_VW_MEETING_TIME_END\${$rowCount}]", 0); // end_time
					$newRecord["end_time"] = ($node == null) ? "" : parse_time(trim_html_escape_chars($node->plaintext));
					$node = $table->find("span[id=DERIVED_SSE_DSP_CLASS_MTG_DAYS\${$rowCount}]", 0); // day
					$newRecord["day"] = ($node == null) ? "" : parse_day(trim_html_escape_chars($node->plaintext));
					$node = $table->find("span[id=DERIVED_SSE_DSP_DESCR40\${$rowCount}]", 0); // location
					$newRecord["location"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
					$node = $table->find("span[id=DERIVED_SSE_DSP_START_DT\${$rowCount}]", 0); // start_date
					$newRecord["start_date"] = ($node == null) ? "" : parse_date(trim_html_escape_chars($node->plaintext));
					$node = $table->find("span[id=DERIVED_SSE_DSP_END_DT\${$rowCount}]", 0); // end_date
					$newRecord["end_date"] = ($node == null) ? "" : parse_date(trim_html_escape_chars($node->plaintext));
					$node = $table->find("span[id=PERSONAL_VW_NAME\$94\$\${$profId}]", 0); // professor
					if ($node != null) {
						$profId += 1; // to next prof
					}
					$newRecord["professor"] = ($node == null) ? "" : trim_html_escape_chars($node->plaintext);
					array_push($ret, $newRecord);
					
					$rowCount += 1;
					$node = $table->find("span[id=CLASS_MTG_VW_MEETING_TIME_START\${$rowCount}]", 0); // to next meeting time
				}
			}
			$course += 1; // to next course
			$node = $html->find("span[id=DERIVED_SSE_DSP_CLASS_DESCR\$span\${$course}]", 0); // class_name, 0 means the first one
			$table = $html->find("table[id=ACE_\$ICField69\${$course}]", 0); // the table
		}
		
		return $ret;
	}
	
	function parse_day($day) {
		$ret = 0;
		if (strstr($day, "Mon") !== false) {
			$ret += 1;
		}
		if (strstr($day, "Tue") !== false) {
			$ret += 2;
		}
		if (strstr($day, "Wed") !== false) {
			$ret += 4;
		}
		if (strstr($day, "Thu") !== false) {
			$ret += 8;
		}
		if (strstr($day, "Fri") !== false) {
			$ret += 16;
		}
		if (strstr($day, "Sat") !== false) {
			$ret += 32;
		}
		if (strstr($day, "Sun") !== false) {
			$ret += 64;
		}
		return $ret;
	}
	
	function parse_date($date) {
		for ($i = 0; $i < strlen($date); $i++) {
			if ($date[$i] == "/") {
				$date[$i] = "-";
			}
		}
		return $date;
	}
	
	function parse_time($time) {
		preg_match("/(\d+):(\d+)(\w+)/", $time, $matches);
		if (empty($matches)) {
			return "";
		}
		$hour = intval($matches[1], 10);
		if ($matches[3] == "PM" && $hour < 12) {
			$hour += 12;
		}
		return sprintf("%2d%02s", $hour, $matches[2]);
	}
	
	function add_colon_to_time($time) {
		$hour = floor(intval($time) / 100);
		$min = intval($time) % 100;
		return sprintf("%2d", $hour) . ":" . sprintf("%02d", $min);
	}
	
	function trim_html_escape_chars($input) {
		$input = html_entity_decode($input);
		return trim(preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $input)); 
	}
	
	function get_access_token() {
		$fp = fopen(ACCESS_TOKEN_FILE, "r+");
		if (fscanf($fp, "%s%d", $access_token, $expire_time) == 2 && time() < $expire_time - MIN_ACCESS_TOKEN_LIFE) {
			fclose($fp);
			return $access_token;
		} else {
			fclose($fp);
			
			// update is required
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=" . APPID . "&secret=" . APPSECRET;
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
			$data =	curl_exec($ch); // json package
			curl_close($ch);
			$package = json_decode($data, true);
			if (isset($package["access_token"])) {
				$fp = fopen(ACCESS_TOKEN_FILE, "w");
				fprintf($fp, "%s %d", $package["access_token"], $package["expires_in"] + time());
				fclose($fp); 
				return $package["access_token"];
			} else {
				// error occurs
				return -1;
			}
		}
	}
?>