<?php
/*	FarsiWeb, Jalali to Gregorian and Gregorian to Jalali Convertor
	V 1.0
	(c) Copyright 2000, FarsiWeb.info. All Rights Reserved.
	http://www.farsiweb.info

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

	-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	Thanks to :
	- Milad Rastian         (http://www.mrastian.com)
	- Amir Masoud Torkian   (http://www.amirkhan.com)
	- All FarsiWeb Members
	-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-
	*/

/*
	:: Name jDate
	:: programmers Milad Rastian,Amir Masoud Torkian
	:: Ver 1.0
	:: Public
	:: Base on http://www.farsiweb.info
*/

function jdate($format,$timestamp='',$none='',$time_zone='Asia/Tehran',$tr_num='fa'){

    $T_sec=0;/* <= functions only in other zones */

    if($time_zone!='local')date_default_timezone_set(($time_zone==='')?'Asia/Tehran':$time_zone);
    $ts=$T_sec+(($timestamp==='' || $timestamp=='now')?time():tr_num($timestamp));
    $date=explode('_',date('H_i_j_n_O_P_s_w_Y',$ts));
    list($j_y,$j_m,$j_d)=gregorian_to_jalali($date[8],$date[3],$date[2]);
    $doy=($j_m<7)?(($j_m-1)*31)+$j_d-1:(($j_m-7)*30)+$j_d+185;
    $kab=(((($date[8]%33)%4)-1)==((int)(($j_y%33)*.05)))?1:0;
    $sl=strlen($format);
    $out='';
    for($i=0; $i<$sl; $i++){
        $sub=substr($format,$i,1);
        if($sub=='\\'){
            $out.=substr($format,++$i,1);
            continue;
        }
        switch($sub){

            case'C':case'E':case'R':case'x':case'X':
            $out.='<br/>';
            break;

            case'B':case'e':case'g':case'G':case'h':case'I':case'T':case'u':case'Z':
            $out.=$date[8];
            break;

            case'a':
                $out.=($date[0]<12)?'ق.ظ':'ب.ظ';
                break;

            case'A':
                $out.=($date[0]<12)?'قبل از ظهر':'بعد از ظهر';
                break;

            case'b':
                $out.=(int)($j_m/3.1)+1;
                break;

            case'd':
                $out.=($j_d<10)?'0'.$j_d:$j_d;
                break;

            case'D':
                $out.=jdate_words(array('kh'=>$date[6]),' ');
                break;

            case'f':
                $out.=jdate_words(array('mm'=>$j_m),' ');
                break;

            case'F':
                $out.=jdate_words(array('mm'=>$j_m),' ');
                break;

            case'H':
                $out.=$date[0];
                break;

            case'i':
                $out.=$date[1];
                break;

            case'j':
                $out.=$j_d;
                break;

            case'J':
                $out.=jdate_words(array('rr'=>$j_d),' ');
                break;

            case'k';
                $out.=tr_num(100-(int)($doy/($kab+365)*1000)/10,$tr_num);
                break;

            case'K':
                $out.=tr_num((int)($doy/($kab+365)*1000)/10,$tr_num);
                break;

            case'l':
                $out.=jdate_words(array('rh'=>$date[6]),' ');
                break;

            case'L':
                $out.=$kab;
                break;

            case'm':
                $out.=($j_m>9)?$j_m:'0'.$j_m;
                break;

            case'M':
                $out.=jdate_words(array('km'=>$j_m),' ');
                break;

            case'n':
                $out.=$j_m;
                break;

            case'O':
                $out.=$date[4];
                break;

            case'p':
                $out.=$date[5];
                break;

            case'P':
                $out.=jdate_words(array('mb'=>$j_m),' ');
                break;

            case'q':
                $out.=jdate_words(array('sh'=>$j_y),' ');
                break;

            case'Q':
                $out.=$kab+364;
                break;

            case's':
                $out.=$date[6];
                break;

            case'S':
                $out.='ام';
                break;

            case't':
                $out.=($j_m!=12)?(($j_m<7)?31:30):($kab+29);
                break;

            case'U':
                $out.=$ts;
                break;

            case'v':
                $out.=jdate_words(array('ss'=>($j_y%100)),' ');
                break;

            case'V':
                $out.=jdate_words(array('ss'=>$j_y),' ');
                break;

            case'w':
                $out.=($date[6]==6)?0:$date[6]+1;
                break;

            case'W':
                $out.=(int)($doy/7);
                break;

            case'y':
                $out.=substr($j_y,2,2);
                break;

            case'Y':
                $out.=$j_y;
                break;

            case'z':
                $out.=$doy;
                break;

            default:
                $out.=$sub;
        }
    }
    return($tr_num=='en')?tr_num($out,'en','.'):(($tr_num=='fa')?tr_num($out,'fa','.'):$out);
}

function jstrftime($format,$timestamp='',$none='',$time_zone='Asia/Tehran',$tr_num='fa'){
    return jdate($format,$timestamp,$none,$time_zone,$tr_num);
}

function jmktime($h='',$m='',$s='',$jm='',$jd='',$jy='',$none='',$timezone='Asia/Tehran'){
    if($timezone!='local')date_default_timezone_set($timezone);
    if($h===''){
        return time();
    }else{
        list($h,$m,$s,$jm,$jd,$jy)=explode('_',tr_num($h.'_'.$m.'_'.$s.'_'.$jm.'_'.$jd.'_'.$jy));
        if($m===''){
            return mktime($h);
        }else{
            if($s===''){
                return mktime($h,$m);
            }else{
                if($jm===''){
                    return mktime($h,$m,$s);
                }else{
                    $j_y=($jy>1300)?$jy-1300:0;
                    $j_m=($jm>0)?$jm:0;
                    $j_d=($jd>0)?$jd:0;
                    list($g_y,$g_m,$g_d)=jalali_to_gregorian($j_y+1300,$j_m,$j_d);
                    return mktime($h,$m,$s,$g_m,$g_d,$g_y);
                }
            }
        }
    }
}

function gregorian_to_jalali($g_y,$g_m,$g_d,$mod=''){
    list($g_y, $g_m, $g_d) = explode('_', tr_num($g_y.'_'.$g_m.'_'.$g_d));/* <= Extra :اين سطر ، جزء تابع اصلي نيست */
    $g_days_in_month=array(31,28,31,30,31,30,31,31,30,31,30,31);
    $j_days_in_month=array(31,31,31,31,31,31,30,30,30,30,30,29);
    $gy=$g_y-1600;
    $gm=$g_m-1;
    $gd=$g_d-1;
    $g_day_no=365*$gy+floor(($gy+3)/4)-floor(($gy+99)/100)+floor(($gy+399)/400);
    for($i=0; $i<$gm; ++$i)$g_day_no+=$g_days_in_month[$i];
    if($gm>1 && (($gy%4==0 && $gy%100!=0) || ($gy%400==0)))$g_day_no++;
    $g_day_no+=$gd;
    $j_day_no=$g_day_no-79;
    $j_np=floor($j_day_no/12053);
    $j_day_no%=12053;
    $jy=979+33*$j_np+4*floor($j_day_no/1461);
    $j_day_no%=1461;
    if($j_day_no>=366){
        $jy+=floor(($j_day_no-1)/365);
        $j_day_no=($j_day_no-1)%365;
    }
    for($i=0; $i<11 && $j_day_no>=$j_days_in_month[$i]; ++$i)$j_day_no-=$j_days_in_month[$i];
    $jm=$i+1;
    $jd=$j_day_no+1;
    return($mod==='')?array($jy,$jm,$jd):$jy.$mod.$jm.$mod.$jd;
}

function jalali_to_gregorian($j_y,$j_m,$j_d,$mod=''){
    list($j_y, $j_m, $j_d) = explode('_', tr_num($j_y.'_'.$j_m.'_'.$j_d));/* <= Extra :اين سطر ، جزء تابع اصلي نيست */
    $j_days_in_month=array(31,31,31,31,31,31,30,30,30,30,30,29);
    $g_days_in_month=array(31,28,31,30,31,30,31,31,30,31,30,31);
    $jy=$j_y-979;
    $jm=$j_m-1;
    $jd=$j_d-1;
    $j_day_no=365*$jy+floor($jy/33)*8+floor((($jy%33)+3)/4);
    for($i=0; $i<$jm; ++$i)$j_day_no+=$j_days_in_month[$i];
    $j_day_no+=$jd;
    $g_day_no=$j_day_no+79;
    $gy=1600+400*floor($g_day_no/146097);
    $g_day_no%=146097;
    $leap=true;
    if($g_day_no>=36525){
        $g_day_no--;
        $gy+=100*floor($g_day_no/36524);
        $g_day_no%=36524;
        if($g_day_no>=365)$g_day_no++;
        else $leap=false;
    }
    $gy+=4*floor($g_day_no/1461);
    $g_day_no%=1461;
    if($g_day_no>=366){
        $leap=false;
        $g_day_no--;
        $gy+=floor($g_day_no/365);
        $g_day_no%=365;
    }
    for($i=0; $g_day_no>=$g_days_in_month[$i]+($i==1 && $leap); $i++)$g_day_no-=$g_days_in_month[$i]+($i==1 && $leap);
    $gm=$i+1;
    $gd=$g_day_no+1;
    return($mod==='')?array($gy,$gm,$gd):$gy.$mod.$gm.$mod.$gd;
}

function jdate_words($array,$mod=''){
    foreach($array as $type=>$num){
        $num=(int)tr_num($num);
        switch($type){
            case'ss':
                $sl=strlen($num);
                $xy3=substr($num,$sl-3,1);
                $h3=$h34=$h4='';
                if($xy3==1){
                    $p34=substr($num,$sl-2,2);
                    $h34=array('10'=>'ده','11'=>'یازده','12'=>'دوازده','13'=>'سیزده','14'=>'چهارده','15'=>'پانزده','16'=>'شانزده','17'=>'هفده','18'=>'هجده','19'=>'نوزده');
                    $h3=$h34[$p34];
                }else{
                    $xy4=substr($num,$sl-1,1);
                    $h4=array('0'=>'', '1'=>'یک', '2'=>'دو', '3'=>'سه', '4'=>'چهار', '5'=>'پنج', '6'=>'شش', '7'=>'هفت', '8'=>'هشت', '9'=>'نه');
                    $h3=array('0'=>'', '2'=>'بیست', '3'=>'سی', '4'=>'چهل', '5'=>'پنجاه', '6'=>'شصت', '7'=>'هفتاد', '8'=>'هشتاد', '9'=>'نود');
                    $h3=$h3[substr($num,$sl-2,1)];
                    $h4=$h4[$xy4];
                }
                $out=($xy3==1 || $xy4==0)?$h3:(($h3=='')?$h4:$h3.' و '.$h4);
                break;
        }
    }
    return $out;
}

function tr_num($str,$mod='en',$mf='٫'){
    $num_a=array('0','1','2','3','4','5','6','7','8','9','.');
    $key_a=array('۰','۱','۲','۳','۴','۵','۶','۷','۸','۹',$mf);
    return($mod=='fa')?str_replace($num_a,$key_a,$str):str_replace($key_a,$num_a,$str);
}

?>