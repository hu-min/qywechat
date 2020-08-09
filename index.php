<?php
header("Content-Type:text/html;charset=utf-8");
include_once "WXBizMsgCrypt.php";  
// 第三方发送消息给公众平台   
$encodingAesKey = "";
$token = "";
$corpId = "";

class wechatCallbackapiTest extends WXBizMsgCrypt
{
    //验证URL有效
    public function valid()
    {
		$sVerifyMsgSig = $_GET["msg_signature"];
		$sVerifyTimeStamp = $_GET["timestamp"];
		$sVerifyNonce = $_GET["nonce"];
		$sVerifyEchoStr = $_GET["echostr"];
		$sEchoStr = "";
		$errCode = $this->VerifyURL($sVerifyMsgSig, $sVerifyTimeStamp, $sVerifyNonce, $sVerifyEchoStr, $sEchoStr); 
		if ($errCode == 0) {
			// 验证URL成功，将sEchoStr返回
			echo $sEchoStr;
		}
    }

    //响应消息
    public function responseMsg()
    {
		$sReqMsgSig = $_GET['msg_signature'];
		$sReqTimeStamp = $_GET['timestamp'];
		$sReqNonce = $_GET['nonce'];
		//$sReqData = $GLOBALS["HTTP_RAW_POST_DATA"];
		$sReqData = file_get_contents("php://input"); 
		$sMsg = "";  // 解析之后的明文
		$this->logger(" DE \r\n".$sReqData);

		$errCode = $this->DecryptMsg($sReqMsgSig, $sReqTimeStamp, $sReqNonce, $sReqData, $sMsg); 
		//判断解密成功 if ($errCode == 0)，可以暂时忽略
		$this->logger(" RR \r\n".$sMsg);
		$postObj = new DOMDocument();  
		$postObj->loadXML($sMsg);
		//$postObj = simplexml_load_string($sMsg, 'SimpleXMLElement', LIBXML_NOCDATA);
		$RX_TYPE = trim($postObj->getElementsByTagName('MsgType')->item(0)->nodeValue);
		//消息类型分离
		switch ($RX_TYPE)
		{
			case "event":
				$sRespData = $this->receiveEvent($postObj);
				break;
			case "text":
				$sRespData = $this->receiveText($postObj);
				break;
			case "image":
				$sRespData = $this->receiveImage($postObj);
				break;
			case "location":
				$sRespData = $this->receiveLocation($postObj);
				break;
			case "voice":
				$sRespData = $this->receiveVoice($postObj);
				break;
			case "video":
			case "shortvideo":
				$sRespData = $this->receiveVideo($postObj);
				break;
			case "link":
				$sRespData = $this->receiveLink($postObj);
				break;
			default:
				$sRespData = "unknown msg type: ".$RX_TYPE;
				break;
		}

		$this->logger(" RT \r\n".$sRespData);
		//加密
		$sEncryptMsg = ""; //xml格式的密文
		$errCode = $this->EncryptMsg($sRespData, $sReqTimeStamp, $sReqNonce, $sEncryptMsg);
		$this->logger(" EC \r\n".$sEncryptMsg);
		echo $sEncryptMsg;
    }

    //接收事件消息
    private function receiveEvent($object)
    {
        $content = "";
        switch ($object->getElementsByTagName('Event')->item(0)->nodeValue)  //类型数据参考：https://github.com/DongDavid/wechat/blob/eab1d0399203145cedb937a10214f03cfc570812/qy/ServerComponent.php
        {
			case "subscribe":
                $content = "欢迎关注企业号";
                break;
            case "enter_agent":
            //    $content = "欢迎进入企业号应用";
                break;
            case "unsubscribe":
                $content = "取消关注";
                break;
            case "click":
                switch ($object->getElementsByTagName('EventKey')->item(0)->nodeValue)
                {
                    case "get_url":
                        $content = get_clurl();
                        break;
                    case "COMPANY":
                        $content = array();
                        $content[] = array("Title"=>"方倍工作室", "Description"=>"", "PicUrl"=>"http://discuz.comli.com/weixin/weather/icon/cartoon.jpg", "Url" =>"http://m.cnblogs.com/?u=txw1958");
                        break;
                    default:
                        $content = "点击菜单：".$object->getElementsByTagName('EventKey')->item(0)->nodeValue;
                        break;
                }
                break;
            case "view":
                $content = "跳转链接 ".$object->getElementsByTagName('EventKey')->item(0)->nodeValue;
                break;
            case "LOCATION":
                break;
            case "scancode_waitmsg":
				$content = "扫码带提示：类型 ".$object->getElementsByTagName('ScanType')->item(0)->nodeValue." 结果：".$object->getElementsByTagName('ScanResult ')->item(0)->nodeValue;
                break;
            case "scancode_push":
				$content = "扫码推事件";
                break;
            case "pic_sysphoto":
                $content = "系统拍照";
                break;
            case "pic_weixin":
                $content = "相册发图：数量 ".$object->getElementsByTagName('ScanCodeInfo')->item(0)->nodeValue;
                break;
            case "pic_photo_or_album":
				$content = "拍照或者相册：数量 ".$object->getElementsByTagName('ScanCodeInfo')->item(0)->nodeValue;
                break;
            case "location_select":
				$temp = $object->getElementsByTagName('SendLocationInfo')->item(0);
                $content = "接收的位置信息：\n{$tmp->getElementsByTagName('Label')->item(0)->nodeValue}\n经线：｛$tmp->getElementsByTagName('Location_X')->item(0)->nodeValue}纬线：{$tmp->getElementsByTagName('Location_Y')->item(0)->nodeValue}精度：{$tmp->getElementsByTagName('Scale')->item(0)->nodeValue}";
                break;
			case "batch_job_result":
                break;
            default:
                $content = "receive a new event: ".$object->getElementsByTagName('Event')->item(0)->nodeValue;
                break;
        }

        if(is_array($content)){
            $result = $this->transmitNews($object, $content);
        }else{
            $result = $this->transmitText($object, $content);
        }
        return $result;
    }

    //接收文本消息
    private function receiveText($object)
    {
        $keyword = trim($object->getElementsByTagName('Content')->item(0)->nodeValue);
        if (strstr($keyword, "帮助")){
			$content = "帮助信息";
		}else if (strstr($keyword, "表情")){
			$content = "中国：".$this->bytes_to_emoji(0x1F1E8).$this->bytes_to_emoji(0x1F1F3)."\n仙人掌：".$this->bytes_to_emoji(0x1F335);
		}else if (strstr($keyword, "单图文")){
			$content = array();
			$content[] = array("Title"=>"单图文标题",  "Description"=>"单图文内容", "PicUrl"=>"https://img.zcool.cn/community/01316d57f39bdba84a0e282b886914.jpg@1280w_1l_2o_100sh.jpg", "Url" =>"http://tqay.com");
		}else if (strstr($keyword, "图文") || strstr($keyword, "多图文")){
			$content = array();
			$content[] = array("Title"=>"多图文1标题", "Description"=>"", "PicUrl"=>"https://img.zcool.cn/community/01316d57f39bdba84a0e282b886914.jpg@1280w_1l_2o_100sh.jpg", "Url" =>"http://tqay.com");
			$content[] = array("Title"=>"多图文2标题", "Description"=>"", "PicUrl"=>"http://d.hiphotos.bdimg.com/wisegame/pic/item/f3529822720e0cf3ac9f1ada0846f21fbe09aaa3.jpg", "Url" =>"http://tqay.com");
			$content[] = array("Title"=>"多图文3标题", "Description"=>"", "PicUrl"=>"http://g.hiphotos.bdimg.com/wisegame/pic/item/18cb0a46f21fbe090d338acc6a600c338644adfd.jpg", "Url" =>"http://tqay.com");
		}else{
			$content = "信息已收到！";
		}

		if(is_array($content)){
			$result = $this->transmitNews($object, $content);
		}else{
			$result = $this->transmitText($object, $content);
		}
        return $result;
    }

    //接收图片消息
    private function receiveImage($object)
    {
        $content = array("MediaId"=>$object->getElementsByTagName('MediaId')->item(0)->nodeValue);
        $result = $this->transmitImage($object, $content);
        return $result;
    }

    //接收位置消息
    private function receiveLocation($object)
    {
		$content = "坐标信息:\n位置:{$object->getElementsByTagName('Label')->item(0)->nodeValue}\n纬度:{$object->getElementsByTagName('Location_X')->item(0)->nodeValue}经度:{$object->getElementsByTagName('Location_Y')->item(0)->nodeValue}";
		$result = $this->transmitText($object, $content);
        return $result;
    }

    //接收语音消息
    private function receiveVoice($object)
    {
        if (isset($object->getElementsByTagName('Recognition')->item(0)->nodeValue) && !empty($object->getElementsByTagName('Recognition')->item(0)->nodeValue)){
            $content = "你刚才说的是：".$object->getElementsByTagName('Recognition')->item(0)->nodeValue;
            $result = $this->transmitText($object, $content);
        }else{
            $content = array("MediaId"=>$object->getElementsByTagName('MediaId')->item(0)->nodeValue);
            $result = $this->transmitVoice($object, $content);
        }
        return $result;
    }

    //接收视频消息
    private function receiveVideo($object)
    {
        $content = array("MediaId"=>$object->getElementsByTagName('MediaId')->item(0)->nodeValue, "ThumbMediaId"=>$object->getElementsByTagName('ThumbMediaId')->item(0)->nodeValue, "Title"=>"视频标题", "Description"=>"视频描述");
        $result = $this->transmitVideo($object, $content);
        return $result;
    }
	
    //接收链接消息
    private function receiveLink($object)
    {
        $content = "你发送的是链接，标题为：".$object->getElementsByTagName('Title')->item(0)->nodeValue."；内容为：".$object->getElementsByTagName('Description')->item(0)->nodeValue."；链接地址为：".$object->getElementsByTagName('Url')->item(0)->nodeValue;
        $result = $this->transmitText($object, $content);
        return $result;
    }
	
    //回复文本消息
    private function transmitText($object, $content)
    {
		if (!isset($content) || empty($content)){
			return "";
		}

		$xmlTpl = "<xml>
	<ToUserName><![CDATA[%s]]></ToUserName>
	<FromUserName><![CDATA[%s]]></FromUserName>
	<CreateTime>%s</CreateTime>
	<MsgType><![CDATA[text]]></MsgType>
	<Content><![CDATA[%s]]></Content>
</xml>";
		$result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), $content);

        return $result;
    }
    //回复图文消息
    private function transmitNews($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return "";
        }
        $itemTpl = "        <item>
            <Title><![CDATA[%s]]></Title>
            <Description><![CDATA[%s]]></Description>
            <PicUrl><![CDATA[%s]]></PicUrl>
            <Url><![CDATA[%s]]></Url>
        </item>
";
        $item_str = "";
        foreach ($newsArray as $item){
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[news]]></MsgType>
    <ArticleCount>%s</ArticleCount>
    <Articles>
$item_str    </Articles>
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }

    //回复图片消息
    private function transmitImage($object, $imageArray)
    {
        $itemTpl = "<Image>
        <MediaId><![CDATA[%s]]></MediaId>
    </Image>";

        $item_str = sprintf($itemTpl, $imageArray['MediaId']);

        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[image]]></MsgType>
    $item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复语音消息
    private function transmitVoice($object, $voiceArray)
    {
        $itemTpl = "<Voice>
        <MediaId><![CDATA[%s]]></MediaId>
    </Voice>";

        $item_str = sprintf($itemTpl, $voiceArray['MediaId']);
        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[voice]]></MsgType>
    $item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复视频消息
    private function transmitVideo($object, $videoArray)
    {
        $itemTpl = "<Video>
        <MediaId><![CDATA[%s]]></MediaId>
        <ThumbMediaId><![CDATA[%s]]></ThumbMediaId>
        <Title><![CDATA[%s]]></Title>
        <Description><![CDATA[%s]]></Description>
    </Video>";

        $item_str = sprintf($itemTpl, $videoArray['MediaId'], $videoArray['ThumbMediaId'], $videoArray['Title'], $videoArray['Description']);

        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[video]]></MsgType>
    $item_str
</xml>";

        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }

    //回复多客服消息
    private function transmitService($object)
    {
        $xmlTpl = "<xml>
    <ToUserName><![CDATA[%s]]></ToUserName>
    <FromUserName><![CDATA[%s]]></FromUserName>
    <CreateTime>%s</CreateTime>
    <MsgType><![CDATA[transfer_customer_service]]></MsgType>
</xml>";
        $result = sprintf($xmlTpl, $object->FromUserName, $object->ToUserName, time());
        return $result;
    }
    //回复第三方接口消息
    private function relayPart3($url, $rawData)
    {
        $headers = array("Content-Type: text/xml; charset=utf-8");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawData);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

	//字节转Emoji表情
	function bytes_to_emoji($cp)
	{
		if ($cp > 0x10000){		# 4 bytes
			return chr(0xF0 | (($cp & 0x1C0000) >> 18)).chr(0x80 | (($cp & 0x3F000) >> 12)).chr(0x80 | (($cp & 0xFC0) >> 6)).chr(0x80 | ($cp & 0x3F));
		}else if ($cp > 0x800){	# 3 bytes
			return chr(0xE0 | (($cp & 0xF000) >> 12)).chr(0x80 | (($cp & 0xFC0) >> 6)).chr(0x80 | ($cp & 0x3F));
		}else if ($cp > 0x80){	# 2 bytes
			return chr(0xC0 | (($cp & 0x7C0) >> 6)).chr(0x80 | ($cp & 0x3F));
		}else{					# 1 byte
			return chr($cp);
		}
	}

    //日志记录
    public function logger($log_content)
    {
        if(isset($_SERVER['HTTP_APPNAME'])){   //SAE
            sae_set_display_errors(false);
            sae_debug($log_content);
            sae_set_display_errors(true);
        }else if($_SERVER['REMOTE_ADDR'] != "127.0.0.1"){ //LOCAL
            $max_size = 500000;
            $log_filename = "log.xml";
            if(file_exists($log_filename) and (abs(filesize($log_filename)) > $max_size)){unlink($log_filename);}
            file_put_contents($log_filename, date('Y-m-d H:i:s').$log_content."\r\n", FILE_APPEND);
        }
    }
}
$wechatObj = new wechatCallbackapiTest($token, $encodingAesKey, $corpId);
$wechatObj->logger(' http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].(empty($_SERVER['QUERY_STRING'])?"":("?".$_SERVER['QUERY_STRING'])));

if (!isset($_GET['echostr'])) {
    $wechatObj->responseMsg();
}else{
    $wechatObj->valid();
}

?>
