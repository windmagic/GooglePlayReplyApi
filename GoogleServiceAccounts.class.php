<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/5/15
 * Time: 11:19
 */
class GoogleServiceAccounts
{
	private  $piKey;
	private  $email;
	private $access_token;
	private $scope ;
	private $package;
	static private $instances;
	private $defaultLang ="en";
	public function __construct($init='')
	{

		if(!is_array($init)){
			$init = json_decode($init,true);
		}
		$this->email = $init['client_email'];
		$this->piKey = openssl_pkey_get_private($init['private_key']);
	}

	public static function getInstance($key_text){
		$key_arr=json_decode($key_text,true);
		if(!empty($key_arr['private_key_id'])){
					$key=$key_arr['private_key_id'];
		}else{
					throw new Exception("invalid key");
		}
		if(!(self::$instances[$key] instanceof self)){
			self::$instances[$key] = new self($key_text);
		}
		
		return self::$instances[$key];
	}
	public function setScope($scope){
		$this->scope = $scope;
	}
	public function getAccessToken(){ //获取链接token
		$now = time();
		if(isset($this->access_token['expires_in'])&&isset($this->access_token['start_time'])){
			if($this->access_token['start_time']+$this->access_token['expires_in']>$now){
				return $this->access_token['access_token'];
			}
		}
		$this->access_token = [];
		$title = ["alg"=>"RS256","typ"=>"JWT"];
		$date = time();
		$body = [
			"iss"=>$this->email,
			"scope"=>$this->scope?$this->scope:"https://www.googleapis.com/auth/androidpublisher",
			"aud"=>"https://www.googleapis.com/oauth2/v4/token",
			"exp"=>$date+3600,
			"iat"=>$date
		];
		$title_json =  json_encode($title,JSON_UNESCAPED_SLASHES);
		$body_json =  json_encode($body,JSON_UNESCAPED_SLASHES);
		$segments['title'] = $this->base64Url($title_json);
		$segments['body'] = $this->base64Url($body_json);
		$segments_input = join('.',$segments);
		$sign = openssl_sign($segments_input,$signature,$this->piKey,"sha256WithRSAEncryption");
		if($sign !== true){
			throw  new Exception('signature failed');
		}
		$segments['bat'] = $this->base64Url($signature);
		$code  = join(".",$segments);
		$postFields=$post_data = 'grant_type='.urlencode('urn:ietf:params:oauth:grant-type:jwt-bearer').'&assertion='.$code;
		$end = $this->getUrl('https://www.googleapis.com/oauth2/v4/token',$postFields);
		if($end){
			$end['start_time'] = $now;
		}
		$this->access_token = $end;
		return $this->access_token['access_token'];
	}

	private  function base64Url($input){
		$input = str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
		return $input;
	}
	public function setPackage($package){
		$this->package =  $package;
	}

	public function getDoubleToken(){
		$two = $this->getReviews(1,'','en');
		$one=$this->getReviews(1);
		if($one['reviews'][0]['reviewId']==$two['reviews'][0]['reviewId']){
			return [$one['tokenPagination']['nextPageToken'],$two['tokenPagination']['nextPageToken']];
		}else{
			return [];
		}
	}
	public function getReviews($size=100,$pageToken='',$lang=''){
		$packageName = $this->package;
		$qs_arr = [
			'access_token'=>$this->getAccessToken(),
			//'translationLanguage'=>$this->defaultLang,
			#'startIndex'=>100,
			'maxResults'=>$size,
		];
		if($pageToken){
			$qs_arr = array_merge($qs_arr,['token'=>$pageToken,]);
		}
		if($lang){
			$qs_arr = array_merge($qs_arr,['translationLanguage'=>$lang,]);
		}
		$qs = http_build_query($qs_arr);
		$url ="https://www.googleapis.com/androidpublisher/v2/applications/$packageName/reviews?$qs";
		$end = $this->getUrl($url);
		return $end;
	}

	public function getReviewsOne($reviewId){
		$packageName = $this->package;
		$qs_arr = [
			'access_token'=>$this->getAccessToken(),
	//		'translationLanguage'=>'en',
			#'startIndex'=>100,
			#'maxResults'=>$size,
		];
		$qs = http_build_query($qs_arr);
		$url ="https://www.googleapis.com/androidpublisher/v2/applications/$packageName/reviews/$reviewId?$qs";
		$end = $this->getUrl($url);
		return $end;
	}

	public function reply($reviewId,$text){
		$packageName = $this->package;
		$qs_arr = [
			'access_token'=>$this->getAccessToken(),
			"reviewId"=>$reviewId,
		];
		$post = [
			"replyText"=>($text),
		];
		$postField = (json_encode($post));
		$qs = http_build_query($qs_arr);
		$url ="https://www.googleapis.com/androidpublisher/v2/applications/$packageName/reviews/$reviewId:reply?$qs";
		$header = ["Content-Type: application/json"];
		$end = $this->getUrl($url,$postField,$header);
		if(isset($end['result'])){
			return true;
		}else{
			return false;
		}
	}

	public function appendDetail(&$commands){

		foreach($commands as $k=>&$v){
				$reviewId = $v['reviewId'];
				foreach($v['comments'] as &$v1){
					$uc = &$v1['userComment'];
					if(isset($uc['originalText'])){
						$end = $this->getReviewsOne($reviewId);
						$uc['originalLang']=($end['comments'][0]['userComment']['reviewerLanguage']);
					}else{
						$uc['originalLang']= $uc['reviewerLanguage'];
					}

				}
		}

	}

	private function getUrl($url,$postFields='',$header_ex=[]){
		$header = array(
			'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
			'Accept-Language: zh-CN,zh;q=0.8,und;q=0.6,en;q=0.4',
			'User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36',
		);
		if(is_array($header_ex)&&$header_ex){
			$header = array_merge($header,$header_ex);
		}
		$mainUrl = $url;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_URL, $mainUrl);
		if($postFields){
			if(is_array($postFields)){
				$postFields = http_build_query($postFields);
			}
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
#curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
#curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	#	echo $postFields."\n";
		$error = 	(curl_error($ch));
	#	($return_code);

		$main = curl_exec($ch);
		$main = json_decode($main,true);
		return $main;
	}
}