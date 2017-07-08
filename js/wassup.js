/**
 * wassup.js - Some common javascripts used in Wassup
 *  @since v1.9 (2015-10-05)
 */
var actionparam="";		//for Wassup ajax actions
var _countDowncontainer="0";	//for Detail/Online refresh countdown
var _currentSeconds=0;
var tickerID=0;
var selftimerID=0;
function ActivateCountDown(strContainerID, initialValue){
	_countDowncontainer=document.getElementById(strContainerID);
	SetCountdownText(initialValue);
	tickerID=window.setInterval("CountDownTick()",1000);
	selftimerID=setTimeout('wSelfRefresh()',initialValue*1000+2000);
}
function CountDownTick(){
	if(_currentSeconds >0){SetCountdownText(_currentSeconds-1);}
	else{clearInterval(tickerID);tickerID=0;}
}
function SetCountdownText(seconds){
	_currentSeconds=seconds;
	var strText=AddZero(seconds);
	if(_countDowncontainer){_countDowncontainer.innerHTML=strText;}
}
function AddZero(num){return((num >= "0")&&(num < 10))?"0"+num:num+"";}
//for Options screen navigation
function wScrollTop(){document.body.scrollTop=document.documentElement.scrollTop=0;}
