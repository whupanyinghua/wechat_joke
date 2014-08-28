<?php

define("TOKEN","panyinghua");

$wechatObj = new wechatCallbackapiTest();
if(isset($_GET['echostr'])){
	$wechatObj->valid();
}else{
	$wechatObj->responseMsg2();
}

class wechatCallbackapiTest
{
	public function valid()
	{
		$echostr = $_GET['echostr'];
		if($this->checkSignature()){
			echo $echostr;
			exit;
		}
	}
	
	private function checkSignature()
	{
		$signature = $_GET["signature"];
		$timestamp = $_GET["timestamp"];
		$nonce = $_GET["nonce"];
		
		$token = TOKEN;
		$tmpArr = array($token,$timestamp,$nonce);
		sort($tmpArr);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);
		
		if($tmpStr == $signature){
			return true;
		}else{
			return false;
		}
		
	}
	
	public function responseMsg()
	{
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
		
		if(!empty($postStr)){
			$postObj = simplexml_load_string($postStr,"SimpleXMLElement",LIBXML_NOCDATA);
			$fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $keyword = trim($postObj->Content);
            $time = time();
			
			$textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[%s]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";
			if($keyword=="?" || $keyword=="？"){
				$msgType = "text";
				$contentStr = "请输入请求+参数，例如（天气+怀化，违章+车牌+发动机后五位）";
				$resultStr = sprintf($textTpl,$fromUsername,$toUsername,$time,$msgType,$contentStr);
				echo $resultStr;
			}else{
				$msgType = "text";
				$contentStr = "暂时不支持[".$keyword."]指令.";
				$resultStr = sprintf($textTpl,$fromUsername,$toUsername,$time,$msgType,$contentStr);
				echo $resultStr;
			}
		}else{
			echo "";
			exit;
		}
	}
	
	public function responseMsg2()
	{
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr)){
            $this->logger("R ".$postStr);
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);

            switch ($RX_TYPE)
            {
                case "event":
                    $result = $this->receiveEvent($postObj);
                    break;
                case "text":
                    $result = $this->receiveText($postObj);
                    break;
				default:
					$result = "";
					break;
            }
            $this->logger("T ".$result);
            echo $result;
        }else {
            echo "";
            exit;
        }
	}
	
	//接收事件类型的输入
	private function receiveEvent($object)
    {
        $content = "";
        switch ($object->Event)
        {
            case "subscribe":
                $content = "欢迎关注Sunny的公众帐号，我可以给您讲笑话哟，输入笑话或者讲笑话即可。更多指令，请输入?进行查询。";
                break;
        }
        $result = $this->transmitText($object, $content);
        return $result;
    }
	
	//接收Text类型的输入
	private function receiveText($object)
    {
        $resultStr = "";
        $keyword = trim($object->Content);
		if($keyword=="?" || $keyword=="？" || $keyword=="help"){
			$resultStr = $this->help($object);
		}else if(($keyword == '笑话')|| ($keyword == '讲笑话')){
			//进行笑话查询
			$url = "http://apix.sinaapp.com/joke/?appkey=trialuser"; 
			$output = file_get_contents($url);
			$content = json_decode($output, true);
			if(is_array($content)!=1){
				if(count($content)<=0){
					$contentStr = "木有任何新的笑话。";
				}else{
					$contentStr = $content;
				}
				$resultStr = $this->transmitText($object, $contentStr);
			}else{
				$resultStr = $this->transmitNews($object, $content);
			}
		}else{
			$contentStr = "暂时不支持[".$keyword."]指令。\n正确的指令是“笑话”或者“讲笑话”。";
			$resultStr = $this->transmitText($object, $contentStr);
		}
		
        return $resultStr;
		
    }
	
	//简单的帮助函数，可提示用户的输入
	private function help($object)
	{
		$content = "我可以给您讲笑话哟，直接输入笑话或者讲笑话即可。";
		return $this->transmitText($object,$content);
	}
	
	//发送简单text类型的信息
	private function transmitText($object, $content)
    {
        $textTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[text]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					</xml>";
        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $this->executeContentStr($content));
        return $result;
    }
	
	//发送图文消息(多条)
	private function transmitNews($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return;
        }
        $itemTpl = "<item>
					<Title><![CDATA[%s]]></Title>
					<Description><![CDATA[%s]]></Description>
					<PicUrl><![CDATA[%s]]></PicUrl>
					<Url><![CDATA[%s]]></Url>
					</item>";
        $item_str = "";
        foreach ($newsArray as $item){
            $item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'], $item['Url']);
        }
        $newsTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[news]]></MsgType>
					<ArticleCount>%s</ArticleCount>
					<Articles>
					$item_str</Articles>
					</xml>";

        $result = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), count($newsArray));
        return $result;
    }
	
	//发送图文消息(单条)
	private function transmitNew($object, $newsArray)
    {
        if(!is_array($newsArray)){
            return;
        }
        $itemTpl = "<item>
					<Title><![CDATA[%s]]></Title>
					<Description><![CDATA[%s]]></Description>
					<PicUrl><![CDATA[%s]]></PicUrl>
					<Url><![CDATA[%s]]></Url>
					</item>";
        $item_str = "";
        
        $item_str = sprintf($itemTpl, $newsArray['Title'], $newsArray['Description'], $newsArray['PicUrl'], $newsArray['Url']);
        
        $newsTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[news]]></MsgType>
					<ArticleCount>%s</ArticleCount>
					<Articles>
					$item_str</Articles>
					</xml>";

        $result = sprintf($newsTpl, $object->FromUserName, $object->ToUserName, time(), 1);
        return $result;
    }
	
	//日志记录
	private function logger($log_content)
    {
    
    }
    
    //处理网上抓取的笑话，用于去掉笑话后面的“技术支持  方倍工作室”字样
    private function executeContentStr($content)
    {
        $result = $content;
    	if ((strpos($content,"方倍工作室") > 0) && strlen($content)>30 ){
			$contentCount = count($content);
    		$result = substr($content,0,$contentCount-30);
		}
        return $result;
    }
	
}

?>