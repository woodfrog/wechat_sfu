<?php
/**
  * wechat php test
  */

//define your token

define("DEFAULT_MSG", "欢迎使用这个公众号，它能够帮助你查询SFU的课程表\n\n" .
					"-------------------------\n" .
					"回复【注册】，你可以将你的SFU账号与公众号绑定\n" . 
					"回复【注销】，你可以解绑你的SFU账号\n" . 
					"回复【当前账号】，你可以查看当前绑定的账号\n" .
					"回复【周一】~【周五】，你可以查看指定时间的课表\n" . 
					"回复【今天】，你可以查看今天的课表\n" . 
					"回复【刷新数据】，公众号将自动为你抓取SFU教务网上的最新数据\n\n" . 
					"快来试试吧~！");
require("../includes/wechat_help.php");
if (isset($_GET["signature"]) && isset($_GET["timestamp"]) && isset($_GET["nonce"]) && isset($_GET["echostr"])) {
	// this is a config test sent from wechat
	define("TOKEN", "song");
	$wechatObj = new wechatCallbackapiTest();
	$wechatObj->valid();
} else {
	$wechatObj = new wechatCallbackapiTest();
	$wechatObj->responseMsg();
}

class wechatCallbackapiTest
{
	public function valid()
    {
        $echoStr = $_GET["echostr"];

        //valid signature , option
        if($this->checkSignature()){
        	echo $echoStr;
        	exit;
        }
    }

    public function responseMsg()
    {
		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

      	//extract post data
		if (!empty($postStr)){
                /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
                   the best way is to check the validity of xml by yourself */
                libxml_disable_entity_loader(true);
              	$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
                $textTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[%s]]></MsgType>
							<Content><![CDATA[%s]]></Content>
							<FuncFlag>0</FuncFlag>
							</xml>"; 
                $time = time();
				$msgType = "text";
				
				switch ($postObj->MsgType) {
					case "text": {
						$keyword = trim($postObj->Content);
						switch (get_routine($fromUsername)) {
							case "register_username": {
								// user sent a username to me
								$contentStr = register($fromUsername, "username", $keyword);
								break;
							}
							case "register_password": {
								// user sent a password to me
								$contentStr = register($fromUsername, "password", $keyword);
								break;
							}
							case "register_confirm": {
								// user wants to confirm or cancel
								$contentStr = register($fromUsername, "confirm", $keyword);
								break;
							}
							default: {
								if (!empty( $keyword)) {
									switch ($keyword) {
										case "注册": {
											$contentStr = register($fromUsername, "first");
											break;
										}
										case "注销": {
											$contentStr = clear_user($fromUsername);
											break;
										}
										case "当前账号": {
											$contentStr = get_username($fromUsername);
											break;
										}
										case "刷新数据": {
											$contentStr = update_course_info(get_uid($fromUsername));
											break;
										}
										case "今天": {
											$contentStr = get_schedule(get_uid($fromUsername), time());
											break;
										}
										case "周一": {
											$contentStr = get_schedule(get_uid($fromUsername), 1473674400);
											break;
										}
										case "周二": {
											$contentStr = get_schedule(get_uid($fromUsername), 1473760800);
											break;
										}
										case "周三": {
											$contentStr = get_schedule(get_uid($fromUsername), 1473847200);
											break;
										}
										case "周四": {
											$contentStr = get_schedule(get_uid($fromUsername), 1473933600);
											break;
										}
										case "周五": {
											$contentStr = get_schedule(get_uid($fromUsername), 1474020000);
											break;
										}
										
										default: {
											$contentStr = DEFAULT_MSG;
										}
									}
								} else {
									echo "Input something...";
								}
							}
						}
						break;
					}
					case "event": {
						switch ($postObj->Event) {
							case "subscribe": {
								$contentStr = DEFAULT_MSG;
								break;
							}
							case "CLICK": {
								switch ($postObj->EventKey) {
									case "TODAY_SCHEDULE": {
										$contentStr = get_schedule(get_uid($fromUsername), time());
										break;
									}
									case "MON_SCHEDULE": {
										$contentStr = get_schedule(get_uid($fromUsername), 1473674400);
										break;
									}
									case "TUE_SCHEDULE": {
										$contentStr = get_schedule(get_uid($fromUsername), 1473760800);
										break;
									}
									case "WED_SCHEDULE": {
										$contentStr = get_schedule(get_uid($fromUsername), 1473847200);
										break;
									}
									case "THU_SCHEDULE": {
										$contentStr = get_schedule(get_uid($fromUsername), 1473933600);
										break;
									}
									case "FRI_SCHEDULE": {
										$contentStr = get_schedule(get_uid($fromUsername), 1474020000);
										break;
									}
									default: {
										$contentStr = DEFAULT_MSG;
									}
								}
							}
						}
						break;
					}
					default: {
						$contentStr = DEFAULT_MSG;
					}
				}
				$resultStr = sprintf($textTpl, $fromUsername, $toUsername, $time, $msgType, $contentStr);
				echo $resultStr;
        }else {
        	echo "";
        	exit;
        }
    }
		
	private function checkSignature()
	{
        // you must define TOKEN by yourself
        if (!defined("TOKEN")) {
            throw new Exception('TOKEN is not defined!');
        }
        
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        		
		$token = TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
}

?>
