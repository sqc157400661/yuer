<?php
$path = dirname(dirname(__FILE__));
require_once ($path . "/www/c_vars.php");
require_once ($path . "/class/rsa.class.php");
require_once ($path . "/class/java_send_mail_class.php");
require_once($path . "/www/c_funcs.php");
require_once($path . "/www/c_funcs_req.php");
require_once($path . "/www/c_funcs_plus.php");
require_once ($path . "/www/c_funcs_mkr.php");
require_once ($path . "/config/config_url.php");
echo sprintf("[%s]start...\n", date('Y-m-d H:i:s'));
ini_set("memory_limit", "2048M");
$p=getopt("f:n:r:s:l:i:p:e:v:a:b:c:d:y:t:g:");
$f=$p['f'];//文件名
$realdiff=$p['r'];//orderquerydetail realdifferent
$env=$p['s'];//环境
$name=$p['n'];//接口名
$nolimit=$p['l'];//是否限制开盘期间不能跑
$interface=$p['i'];
$port=$p['p'];
$sendmailflag=$p['v'];//是否邮件发送差异
$email=isset($p['e'])?$p['e']:'zhangyi@myhexin.com,wuhaohan@myhexin.com,shiqingchuang@myhexin.com,zhanghe@myhexin.com';
$addra=$p['a'];
$addrb=$p['b'];
$baseuri=empty($p['c'])?"/go_pass":$p['c'];
$addrd=$p['d'];
$trim=$p['t'];
$y=$p['y'];//格式
$gline=is_numeric($p['g'])?$p['g']:1000;//记录执行位置的频率 默认1000行记录一次;
if(empty($f)){
	$day=date("Ymd",strtotime('-1 days'));
	echo $day."\n";
	$f="/tmp/".$name.".".$day;
}
if (!file_exists($f)) {
    echo "file not exists!\n";
    die();
}
$filterArr=array('recent_trust_muser'=>'lasttime---21');//特殊过滤处理数组
$difflen=array('do_rsa'=>'type=>encrypt');//接口=>condition
$fp = fopen($f, 'r');
$filter=array();
$returnarr=array();
$content="";
$contentdetail="";
$special=array('order_query_detail','wlh_order_query_detail');//需要特殊处理的对比
$diffRequestArr=array();
$i=0;
$total=0;//记录请求数量
$samephp=0;//记录php原始响应大小与 现在一致数量
$samego=0;
$goErrcodearr=array();
$phpErrcodearr=array();
$basename=basename($f).basename(dirname($f)).".line";//文件名
$linefilepath="/tmp/".$basename;
$nowline=0;
if(file_exists($linefilepath)){
	$line=file_get_contents($linefilepath);//上次文件执行到的位置
	$nowline=is_numeric($line)?$line:0;
}
$unq=array();
while (!feof($fp)) {
	$i++;
	$row = trim(fgets($fp));
	if($nowline>$i){
		continue;
	}
	if (empty($row)) {
		continue;
	}
	$h=date("H");
	if($h>6 && $h<16 && empty($nolimit) ){
		//echo date("Y-m-d H:i:s")." time out  \n";
		//break;
		sleep(1);//开盘期间缓慢执行
	}
	//解析日志
  $info=  explode("] [" ,trim($row,"]"));
  $getinfo=$info[3];//get参数
  $rsize=$info[5];//响应大小
  $postinfo=$info[13];//post参数

  $xieyiinfo=explode(" ",$getinfo);
  $realp='';
  $pos=strpos($xieyiinfo[1],'?');
  $kvinfo=array();
  if(is_int($pos)){
	  $paramstr=substr($xieyiinfo[1],$pos+1);
	  parse_str($paramstr,$tmpdata);
	  $getdata=array();
	  foreach($tmpdata as $k=>$v){
		    $kvinfo[$k]=urldecode($v);
	  		if($k=='atv2') continue;//token跳过
			$getdata[$k]=urldecode($v);
			if($y){
				$kvinfo['resp']=$y;
			}
	  }
	  $realp=http_build_query($getdata);
  }
  if($addrd){
	  if(strpos(explode('?',  $xieyiinfo[1])[0],$addrd)===false)continue;
		$version=$addrd;
  }
  else{
	    if(strpos(explode('?',  $xieyiinfo[1])[0],'verify')===false)continue;
		$version=strpos(explode('?',  $xieyiinfo[1])[0],'verify2')===false?'/verify':"/verify2";
  }

  $getrealp= empty($realp)?$version:$version."?".$realp;
  $reqtypeflag=0;
  $postdata=array();
  if($xieyiinfo[0]=='GET'){
	$reqtypeflag=1;
  }else{
	  $find="Content-Disposition: form-data; name=";
	  $postdata=array();
	  if(strpos($postinfo,$find)!==false){//form-data 
			//解析formdata
		    $reqtypeflag=3;
			$split=explode('\x0A',$postinfo)[0];
			$a=explode($split,$postinfo);
			foreach($a as $k=>$v){
			if(!empty($v)){
				 $d=explode($find,$v)[1];
				 $e=explode('\x0D\x0A',$d);
				 $f=trim($e[0],'\x22');
                if($k=='atv2') continue;//token跳过
				 $tmp=str_replace("\x","%",$e[2]);
				 //无法判断是否是汉字 只知道汉字都转换成了
				 $postdata[$f]= urldecode($tmp);
				 $kvinfo[$f]=urldecode($tmp);
				if($y){
					$kvinfo['resp']=$y;
				}
			  }
			}
	  }else{//post
	  	$reqtypeflag=2;
		
		if($postinfo!="-"){
			parse_str($postinfo,$tmpdata);
			foreach($tmpdata as $k=>$v){
				if($k=='atv2') continue;//token跳过
				$tmp=str_replace("\x","%",$v);
				$postdata[$k]=urldecode($tmp);
				$kvinfo[$k]=urldecode($tmp);
				if($y){
					$kvinfo['resp']=$y;
				}	 				 			
			}
		}
	  }
  }
  //解析日志end
   if($interface){
	  $allowarr= explode(",",$interface);
	  if(!isset($kvinfo['reqtype']) || !in_array($kvinfo['reqtype'],$allowarr)){
		  continue;
	  }
   }
  	$reqtype=strtolower($kvinfo['reqtype']);
  
	$demo= $getrealp." ".http_build_query($postdata);
	//echo $demo."\n";
	if(isset($unq[md5($demo)])){//重复请求过滤掉
		continue;
	}
	if(count($unq)>10000){//防止数组过大 清空
			$unq=array();
	}
	$unq[md5($demo)]=1;
	if($env=='dev')
	{       $realaddra=empty($addra)?"10.0.20.252:8100":$addra;
		    $realaddrb=empty($addra)?"10.0.20.22:8000":$addrb;
			//$gores = sendPostRequest('10.0.20.22','8000',"/go_pass".$version,$kvinfo, 3);
            $gores =   sendRequest($reqtypeflag, $realaddrb.$baseuri.$getrealp,$postdata,$rsize,2);
			//$phpres =sendPostRequest('10.0.20.252','8100',$version,$kvinfo, 3);
            $phpres =   sendRequest($reqtypeflag,$realaddra.$getrealp,$postdata,$rsize,1);
	}
	elseif($env=='test'){
		    $realaddra=empty($addra)?"10.10.80.83:10081":$addra;
		    $realaddrb=empty($addra)?"10.10.80.83:8000":$addrb;
			//$gores = sendPostRequest('10.0.20.22','8000',"/go_pass".$version,$kvinfo, 3);
            $gores =   sendRequest($reqtypeflag, $realaddrb.$baseuri.$getrealp,$postdata,$rsize,2);
			//$phpres =sendPostRequest('10.0.20.252','8100',$version,$kvinfo, 3);
            $phpres =   sendRequest($reqtypeflag,$realaddra.$getrealp,$postdata,$rsize,1);
	}
	else{
			$realaddra=empty($addra)?"10.0.0.29:10088":$addra;
		    $realaddrb=empty($addra)?"10.0.0.29:8000":$addrb;
			//$phpres = sendPostRequest('10.0.0.29','10088',$version,$kvinfo, 3);
			$phpres =   sendRequest($reqtypeflag,$realaddra.$getrealp,$postdata,$rsize,1);
			$gores =   sendRequest($reqtypeflag, $realaddrb.$baseuri.$getrealp,$postdata,$rsize,2);
			//$gores =sendPostRequest($ip,$port,"/go_pass".$version,$kvinfo, 3);
	}

	if($trim){
		$goresult=str_replace(array(">\r\n",">\r",">\n"),">", $gores['content']);
        $presult =str_replace(array(">\r\n",">\r",">\n"),">", $phpres['content']);
	}else{
		 $goresult= $gores['content'];
         $presult =$phpres['content'];
	}
      if(isset($difflen[$reqtype])){
		  if(!empty($difflen[$reqtype])){//特定字段条件下处理
				$tmplenArr=explode("=>",$difflen[$reqtype]);
				if(isset($kvinfo[$tmplenArr[0]]) && strtolower($kvinfo[$tmplenArr[0]])==$tmplenArr[1] && strlen($goresult)==strlen($presult)){
					continue;
				}
			  
		  }else{
			  if(strlen($goresult)==strlen($presult)){//对比长度一致
				  continue;
			  }
		  }
	  }
       
       if($goresult==$presult){
        // echo "same\n";
       }else{

        if(strtolower($kvinfo['resp'])=='json'){
			$goresResult= json_decode($goresult,true);
			$phpresResult= json_decode($presult,true);
			$goArr=$goresResult['result'];
			$phpArr=$phpresResult['result'];
			
		}else{
			$goresResult= authXmlParsecmd($goresult);//先不改底层方法authXmlParse ，省的影响太大,重命名一个authXmlParsecmd
			$phpresResult= authXmlParsecmd($presult);
			$goArr=$goresResult['item'];
			$phpArr=$phpresResult['item'];
			$goresResult['code']=$goresResult['ret']['code'];
			$phpresResult['code']=$phpresResult['ret']['code'];
		} 	 
		
	   if(!isset($goArr) || !isset($phpArr)){
			echo date("Y-m-d H:i:s")."diff error:" .$demo."\n";
			echo "new".$goresult."\n";
			echo "old".$presult."\n";
                                    $diffRequestArr[$i]['version']=$getrealp;
                                    $diffRequestArr[$i]['kvinfo']=$postdata;
									$diffRequestArr[$i]['rsize']=$rsize;
		   continue;
	   }

        $diffcotent="";
     if($goresResult['code']!=0){
         $goErrcodearr[$goresResult['code']]++;
	 }
	 if($phpresResult['code']!=0){
		   $phpErrcodearr[$phpresResult['code']]++;
	 }
	if($goresResult['code']!=$phpresResult['code']){
                                    $diffRequestArr[$i]['version']=$getrealp;
                                    $diffRequestArr[$i]['kvinfo']=$postdata;
									$diffRequestArr[$i]['rsize']=$rsize;
	    echo "code new". $goresResult['code']."  :code php ".$phpresResult['code'];
	}

		if(in_array($reqtype,$special))   {
				  $goitem= $goArr;
				  $goArr=array();
				  foreach($goitem as $gk=>$gv){
					$goArr[$gv['sid']]=$gv;
				  }
				$phpitem= $phpArr;
				$pArr=array();
				foreach($phpitem as $pk=>$pv){
					$pArr[$pv['sid']]=$pv;
				}
				 foreach($goArr as $gk=>$gv){
				   if(!isset($pArr[$gk])){
					   echo date("Y-m-d H:i:s")."diff error:3 php no sid ".$gk .$demo."\n";
                                    $diffRequestArr[$i]['version']=$getrealp;
                                    $diffRequestArr[$i]['kvinfo']=$postdata;
									$diffRequestArr[$i]['rsize']=$rsize;
					   var_dump($goresResult); var_dump($phpresResult);
				   }else{
					  $diff=array_diff($pArr[$gk],$gv);
					  if($diff){
						  foreach($diff as $dk=>$dv){
							  if(floatval($pArr[$gk][$dk])==$gv[$dk] && empty($realdiff)){
								  unset($diff[$dk]);
							  }else{
									unset($diff[$dk]);
									$kphp=$dk."p";
									$kgo=$dk."g";
									$diff[$kgo]=isset($gv[$dk])?$gv[$dk]:"nodata";
									$diff[$kphp]=$pArr[$gk][$dk];
                                    $diffRequestArr[$i]['version']=$getrealp;
                                    $diffRequestArr[$i]['kvinfo']=$postdata;
									$diffRequestArr[$i]['rsize']=$rsize;
							  }
						  }
						  if(!empty($diff)){
							   $diff['sid']=$gk;
							   echo "diff error 2:"."-".json_encode($diff)."-".$demo."\n";
								$diffRequestArr[$i]['version']=$getrealp;
								$diffRequestArr[$i]['kvinfo']=$postdata;
								$diffRequestArr[$i]['rsize']=$rsize;

						  }else{

							//  echo "same 2\n";
						  }

					  }else{

						//  echo "same 3\n";
					  }

				   }
				   if($realdiff){
						$diff=array_diff($gv,$pArr[$gk]);
						foreach($diff as $dk=>$dv){
									unset($diff[$dk]);
									$kphp=$dk."p";
									$kgo=$dk."g";
									$diff[$kgo]=$gv[$dk];
									$diff[$kphp]=isset($pArr[$gk][$dk])?$pArr[$gk][$dk]:"nodata";
						  }
						  if(!empty($diff)){
							   $diff['sid']=$gk;
							   echo "diff error new to old:"."-".json_encode($diff)."-".$demo."\n";
									$diffRequestArr[$i]['version']=$getrealp;
                                    $diffRequestArr[$i]['kvinfo']=$postdata;
									$diffRequestArr[$i]['rsize']=$rsize;
						  }else{


						  }

				   }

				 }
		}else{
			$cond1=$cond2=false;
			if(is_array($phpArr) && is_array($goArr) && !empty($phpArr) && !empty($goArr)){
				$cond1=count($phpArr) ==count( $phpArr,1) && count($goArr) ==count( $goArr,1);
				$cond2=$cond1==false && count($phpArr)==1 && count($phpArr[0]) ==count( $phpArr[0],1) && count($goArr[0]) ==count( $goArr[0],1);
                if($cond2==true){
                    $goArr=$goArr[0];
                    $phpArr=$phpArr[0];
				}
            }
            if ($cond1 || $cond2){//都是一维数组
            	    $diffgo=array_diff($goArr,$phpArr);
					 $diffphp=array_diff($phpArr,$goArr);
					 $diffkeygo=array_diff_key($goArr,$phpArr);
					 $diffkeyphp=array_diff_key($phpArr,$goArr);
					 if($diffgo || $diffphp || $diffkeygo || $diffkeyphp){
					 	echo $version."?".$demo."\n";
						$diffRequestArr[$i]['version']=$getrealp;
						$diffRequestArr[$i]['kvinfo']=$postdata;
						$diffRequestArr[$i]['rsize']=$rsize;
					 }
					 if($diffgo){
						 echo "--------------\n";
						 echo date("Y-m-d H:i:s").":".__LINE__." :new diff old:".json_encode($diffgo)."\n";
						 echo "old result".$presult."\n";
						 echo "new result".$goresult."\n";
						 echo "--------------\n";
					 }
					 if($diffphp){
						  echo "--------------\n";
						 echo  date("Y-m-d H:i:s").":".__LINE__.":old diff new:".json_encode($diffphp)."\n";
						 echo "old result".$presult."\n";
						 echo "new result".$goresult."\n";
						  echo "--------------\n";
					 }
					 if($diffkeygo){
						 echo  date("Y-m-d H:i:s").":".__LINE__."new diffkey old:".json_encode($diffkeygo)."\n";
					 }
					 if($diffkeyphp){
						 echo  date("Y-m-d H:i:s").":".__LINE__."old diffkey new:".json_encode($diffkeyphp)."\n";
					 }
					
		

			}else{//类型不一致 字符串， or 多维数组
                 echo $version."?".$demo."\n";
   				 echo date("Y-m-d H:i:s").":".__LINE__."new result:".$goresult."-----old result:".$presult."\n";
				$diffRequestArr[$i]['version']=$getrealp;
				$diffRequestArr[$i]['kvinfo']=$postdata;
				$diffRequestArr[$i]['rsize']=$rsize;

			}

//		 if(gettype($phpArr)!=gettype($goArr)){//类型不一致
//
//
//
//
//		 }elseif (is_string($phpArr) || is_string($goArr)){//类型不一致有一个为字符串
//
//             echo date("Y-m-d H:i:s").json_encode($phpArr)."-----".json_encode($goArr)."\n";
//             $diffRequestArr['version']=$version;
//             $diffRequestArr['kvinfo']=$kvinfo;
//		 }
//		 else{//二位数组对比每一个key 值 稍微麻烦一些   需要定义一个key来比较，看了下今年实现的 剩余的目标 除了cookiedomain 其他都没有，先临时输出结果 后面有空再细化处理他
//
//
//
//		 }

	}
      

  }
  
  if($i%$gline ===0){
	file_put_contents($linefilepath,$i);		
  }
  
}
echo sprintf("[%s]part 1 end...\n", date('Y-m-d H:i:s'));
if ($sendmailflag) {
	echo "------------------repeat diff------------------\n";
	if(!empty($diffRequestArr)){//存在差异的请求 重新请求
		$diffcontent="";
		foreach ($diffRequestArr as $val){

            $h=date("H");
			if($h>8 && $h<16 && empty($nolimit) ){
				//echo date("Y-m-d H:i:s")." time out  \n";
				//break;
				sleep(1);//开盘期间缓慢执行
			}
					
			
            if($env=='dev')
            {
				 $realaddra=empty($addra)?"10.0.20.252:8100":$addra;
		         $realaddrb=empty($addra)?"10.0.20.22:8000":$addrb;
                //$gores = sendPostRequest('10.0.20.22','8000',"/go_pass".$version,$kvinfo, 3);
                $gores =   sendRequest($reqtypeflag, $realaddrb.$baseuri.$val['version'],$val['kvinfo'],$val['rsize'],2);
                //$phpres =sendPostRequest('10.0.20.252','8100',$version,$kvinfo, 3);
                $phpres =   sendRequest($reqtypeflag,$realaddra.$val['version'],$val['kvinfo'],$val['rsize'],1);
            }
			elseif($env=='test'){
				$realaddra=empty($addra)?"10.10.80.83:10081":$addra;
		        $realaddrb=empty($addra)?"10.10.80.83:8000":$addrb;
                //$gores = sendPostRequest('10.10.80.83','8000',"/go_pass".$version,$kvinfo, 3);
                $gores =   sendRequest($reqtypeflag,$realaddrb.$baseuri.$val['version'],$val['kvinfo'],$val['rsize'],2);
                //$phpres =sendPostRequest('10.10.80.83','10081',$version,$kvinfo, 3);
                $phpres =   sendRequest($reqtypeflag,$realaddra.$val['version'],$val['kvinfo'],$val['rsize'],1);
            }
            else{
				$realaddra=empty($addra)?"10.0.0.29:10088":$addra;
				$realaddrb=empty($addra)?"10.0.0.29:8000":$addrb;
                //$phpres = sendPostRequest('10.0.0.29','10088',$version,$kvinfo, 3);
                $phpres =   sendRequest($reqtypeflag,$realaddra.$val['version'],$val['kvinfo'],$val['rsize'],1);
                $gores =   sendRequest($reqtypeflag, $realaddrb.$baseuri.$val['version'],$val['kvinfo'],$val['rsize'],2);
                //$gores =sendPostRequest($ip,$port,"/go_pass".$version,$kvinfo, 3);
            }
            usleep(1000);
			if($trim){
				$goresult=str_replace(array("\r\n","\r","\n"),"", $gores['content']);
				$presult =str_replace(array("\r\n","\r","\n"),"", $phpres['content']);
			}else{
				 $goresult= $gores['content'];
				 $presult =$phpres['content'];
			}
      
            $goresult= $gores['content'];
            $presult =$phpres['content'];
            if(strtolower($kvinfo['resp'])=='json'){
                $goresResult= json_decode($goresult,true);
                $phpresResult= json_decode($presult,true);
                $goArr=$goresResult['result'];
                $phpArr=$phpresResult['result'];

            }else{
                $goresResult= authXmlParsecmd($goresult);
                $phpresResult= authXmlParsecmd($presult);
                $goArr=$goresResult['item'];
                $phpArr=$phpresResult['item'];
				$goresResult['code']=$goresResult['ret']['code'];
				$phpresResult['code']=$phpresResult['ret']['code'];
            }
            if($goresResult['code']!=0){
                $goErrcodearr[$goresResult['code']]++;
            }
            if($phpresResult['code']!=0){
                $phpErrcodearr[$phpresResult['code']]++;
            }
            if($goresult!=$presult) {
				$cond1=$cond2=false;
				if(is_array($phpArr) && is_array($goArr) && !empty($phpArr) && !empty($goArr)){
					$cond1=count($phpArr) ==count( $phpArr,1) && count($goArr) ==count( $goArr,1);
					$cond2=$cond1==false && count($phpArr)==1 && count($phpArr[0]) ==count( $phpArr[0],1) && count($goArr[0]) ==count( $goArr[0],1);
                    if($cond2==true){
                        $goArr=$goArr[0];
                        $phpArr=$phpArr[0];
                    }
				}
                if ($cond1 || $cond2){//都是一维数组
                    $diffgo = array_diff($goArr, $phpArr);
                    $diffphp = array_diff($phpArr, $goArr);
					 $diffkeygo=array_diff_key($goArr,$phpArr);
					 $diffkeyphp=array_diff_key($phpArr,$goArr);
		    		if($goresResult['code']!=$phpresResult['code']){
		    			  echo $val['version'] . "?" . http_build_query($val['kvinfo'])."\n";
	   	  				  echo "code new". $goresResult['code']."  :code php ".$phpresResult['code'];
	   	  				  $diffcotent .= $val['version'] . "?" . http_build_query($val['kvinfo'])."\r\n";
		  				  $diffcotent .= "code new". $goresResult['code']."  :code php ".$phpresResult['code'];
	       			 }
					if($diffgo  || $diffphp || $diffkeygo || $diffkeyphp ){
						echo "--------------------------" . date('Y-m-d H:i:s') . "----------------------\n";
						$diffcotent .=  "--------------------------" . date('Y-m-d H:i:s') . "----------------------\r\n";
						echo $val['version'] . "?" . http_build_query($val['kvinfo'])."\n";
						$diffcotent .= $val['version'] . "?" . http_build_query($val['kvinfo'])."\r\n";
                        $diffcotent .= "old result" . $presult."\r\n";
                        $diffcotent .= "new result" . $goresult."\r\n";
					}
					
                    if ($diffgo) {
                        echo date("Y-m-d H:i:s").":".__LINE__.  ":new diff old:" . json_encode($diffgo)  . "\n";
                        $diffcotent .= "new diff old" . json_encode($diffgo)."\r\n";;
                    }
                    if ($diffphp) {
                        echo date("Y-m-d H:i:s") .":".__LINE__. ":old diff new:" . json_encode($diffphp) . "\n";
                        $diffcotent .= "old diff new" . json_encode($diffphp)."\r\n";;
                    }
					 if($diffkeygo){
						 echo  date("Y-m-d H:i:s").":".__LINE__.":new diffkey old:".json_encode($diffkeygo)."\n";
						   $diffcotent .= "new diffkey old" . json_encode($diffkeygo)."\r\n";;
					 }
					 if($diffkeyphp){
						 echo  date("Y-m-d H:i:s").":".__LINE__. ":old diffkey new:".json_encode($diffkeyphp)."\n";
						 $diffcotent .= "old diffkey new" . json_encode($diffkeyphp)."\r\n";;
					 }
				

                } else {//类型不一致 字符串， or 多维数组
					if($goresResult['code']!=$phpresResult['code']){
						echo "code new". $goresResult['code']."  :code php ".$phpresResult['code']."\n";
					   $diffcotent .= "code new". $goresResult['code']."  :code php ".$phpresResult['code']."\r\n";
					}
                    echo date("Y-m-d H:i:s") . $presult . "-----" . $goresult . "\n";
					$diffcotent .=  "................." . date('Y-m-d H:i:s') . "................."."\r\n";
					$diffcotent .= $val['version'] . "?" . http_build_query($val['kvinfo'])."\r\n";
                    $diffcotent .= "old result" . $presult."\r\n";
                    $diffcotent .= "new result" . $$goresult."\r\n";
                }
            }

        }



	}
    $type=empty($diffcotent)?"simple":"multipart";
	$oMail = new CSendMail('inner', $type);
	$oMail->email_to = $email;
	$oMail->email_subject = $reqtype."-diff";
	$text.="totalnum:".$total/2 ."<br/>";//记录请求数量
	$text.="oldsameresponsenum:".$samephp ."<br/>";//记录请求数量
	$text.="newsameresponsenum:".$samego."<br/>";//记录请求数量
	$text.="new errorcode num".var_export($goErrcodearr,true)."<br/>";//记录请求数量
	$text.="old errorcode num".var_export($phpErrcodearr,true)."<br/>";//记录请求数量
	echo $text."\n";
	$oMail->email_text = $text;
	if($diffcotent) {
		$oMail->attachment = array(array('name' => $reqtype.'-diff.txt', 'data' => $diffcotent));
	}else{
		echo "same all";
	}
	if ($oMail->send(false)) {
	echo "EMAIL SEND SUCCESS\n";
	}
	else {
	echo "FAIL\n";
	}


} 


/**
 * xml简单解析（针对认证中心的XML）
 * copy from c_funcs_plus 便于调试
 * @author hht
 */
function authXmlParsecmd($xml)
{
	$xml=trim($xml);
    $obj = @simplexml_load_string($xml);
    $xmlarr = array('ret'=>array(), 'item'=>array());
    if ($obj == false){
		  $xml = str_replace('encoding="GB2312"', 'encoding="GBK"', $xml);//gb2312含有的字符集较少，会出现解不出来的情况
		  $xml = str_replace('encoding="gb2312"', 'encoding="GBK"', $xml);
		  $obj = @simplexml_load_string($xml);
		  if($obj==false){
			 echo "xml parse fail:".$xml;
			 //自定义解析
             return dataparse($xml);
			
		  }
	}
		
    foreach ($obj as $name => $value)
    {
        if ($name == 'item')
        {
            $i = 0;
            foreach ($obj->item as $item)
            {
                foreach ($item->attributes() as $akey => $aval)
                {
                    $xmlarr[$name][$i][$akey] = strval($aval);
                }
                $i++;
            }
        }
        else
        {
            foreach ($obj->{$name}->attributes() as $akey => $aval)
            {
                $xmlarr[$name][$akey] = strval($aval);
            }
        }
    }
    return $xmlarr;
} 
//临时处理 仅支持认证中心一维数组返回的处理
function dataparse($xml){

    $xml=str_replace(array("<?xml","?>",'<',">","<ret", "/",">","<item","version=","encoding=")," ", $xml);
	$arr=explode('" ',$xml);
	$array= array('ret'=>array(), 'item'=>array());;
	foreach($arr as $k=>$v){
		$v=trim($v);
		$test=explode("=",$v);
		if(count($test)==2){
			$tmpkey=trim(end(explode(" ",$test[0])));
			if($tmpkey=='code' || $tmpkey=="msg"){
				$array['ret'][$tmpkey]=trim(str_replace('"','',$test[1]));
			}else{
				$array['item'][0][$tmpkey]=trim(str_replace('"','',$test[1]));
			}
		}
	}

	return $array;
}
/**构造类似curl -f 的请求*/
function sendcurlfRequest($url, $post_arr)
{   
    global $filterArr,$reqtype,$kvinfo;
	$opt[CURLOPT_POST] = 1;
	$opt[CURLOPT_RETURNTRANSFER] = 1;
	$opt[CURLOPT_POSTFIELDS] =$post_arr;
    $opt[CURLOPT_URL] = $url;
    $curl = curl_init();
    curl_setopt_array($curl, $opt);

	$content = curl_exec($curl);
	$response = curl_getinfo($curl);
	curl_close($curl);
	$return['size'] = $response['size_download'];
	if(array_key_exists($reqtype,$filterArr)){
		$jiequArr=explode("---",$filterArr[$reqtype]);
		$jqstart=strpos($content,$jiequArr[0]);
		if($jqstart!==false){
			$strqu=substr($content,$jqstart,$jiequArr[1]);
			$content=str_replace($strqu,"",$content);
		}	
	}
	$content=str_replace(array('utf-8','gb2312','gbk'),array('UTF-8','GB2312','GBK'),$content);
	$return['content'] = $content;
	return $return;
}

function sendcurlpostRequest($url, $post_arr, $timeout=10, $version = '1.0', $header_arr = array())
{   global $filterArr,$reqtype;
	$return = array();
	$post_str = http_build_query($post_arr);
	$curl = curl_init();
	if ($header_arr)
		$header = $header_arr;
	else
		$header = array("Content-Type: application/x-www-form-urlencoded");

	curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
	if (is_array($post_arr) && count($post_arr)>0)
	{
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $post_str);
	}
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
	if ($version == '1.0')
		curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);

	$content = curl_exec($curl);
	$response = curl_getinfo($curl);
	curl_close($curl);
	$return['size'] = $response['size_download'];
	if(array_key_exists($reqtype,$filterArr)){
		$jiequArr=explode("---",$filterArr[$reqtype]);
		$jqstart=strpos($content,$jiequArr[0]);
		if($jqstart!==false){
			$strqu=substr($content,$jqstart,$jiequArr[1]);
			$content=str_replace($strqu,"",$content);
		}	
	}
	$content=str_replace(array('utf-8','gb2312','gbk'),array('UTF-8','GB2312','GBK'),$content);
	$return['content'] = $content;
	return $return;

}
function sendcurlgetRequest($url,$arr=array() ,$timeout=3, &$errno=0,&$errmsg='')
{   
    global $filterArr,$reqtype;
    $ret_str = ''; 
    $ch = curl_init($url);
    if ($ch)
    {   
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET           => 1,  
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT        => $timeout,        
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_FAILONERROR => 1,
        );  
        curl_setopt_array($ch, $options); 
        $ret_str = curl_exec($ch); 
		$response = curl_getinfo($ch);
        $errno = curl_errno($ch);
        if ($errno)
        {   
            $errmsg  = curl_error($ch) ;
        }   
        curl_close($ch); 
    }   
    unset($ch);
    $return['size'] = $response['size_download'];
	if(array_key_exists($reqtype,$filterArr)){
		$jiequArr=explode("---",$filterArr[$reqtype]);
		$jqstart=strpos($ret_str,$jiequArr[0]);
		if($jqstart!==false){
			$strqu=substr($ret_str,$jqstart,$jiequArr[1]);
			$ret_str=str_replace($strqu,"",$ret_str);
		}
	}
	$ret_str=str_replace(array('utf-8','gb2312','gbk'),array('UTF-8','GB2312','GBK'),$ret_str);	
	$return['content'] = $ret_str;
    return $return;
}
function sendRequest($type=1,$url,$postdata=array(),$osize=0,$ytype=0){
	global $total,$samephp,$samego;
    $total++;
	if($type==1){
		$r= sendcurlgetRequest($url);
		if($r['size']==$osize){
            $ytype==1?$samephp++:$samego++;
		}
        return $r;
	}elseif($type==2){
        $r=sendcurlpostRequest($url,$postdata);
        if($r['size']==$osize){
            $ytype==1?$samephp++:$samego++;
        }
        return $r;
	}else{
        $r=sendcurlfRequest($url,$postdata);
        if($r['size']==$osize){
            $ytype==1?$samephp++:$samego++;
        }
        return $r;
	}
	
}
echo sprintf("[%s]part 2 end...\n", date('Y-m-d H:i:s'));
?>
