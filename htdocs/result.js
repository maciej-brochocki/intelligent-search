var http;
function ajaxGet(url,params,cfunc)
{
if (window.XMLHttpRequest)
  {// code for IE7+, Firefox, Chrome, Opera, Safari
  http=new XMLHttpRequest();
  }
else
  {// code for IE6, IE5
  http=new ActiveXObject("Microsoft.XMLHTTP");
  }
http.onreadystatechange=cfunc;
http.open("GET",url+"?"+params,true);
http.send();
}

function ajaxPost(url,params,cfunc)
{
if (window.XMLHttpRequest)
  {// code for IE7+, Firefox, Chrome, Opera, Safari
  http=new XMLHttpRequest();
  }
else
  {// code for IE6, IE5
  http=new ActiveXObject("Microsoft.XMLHTTP");
  }
http.onreadystatechange=cfunc;
http.open("POST",url,true);
//Send the proper header information along with the request
http.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
http.setRequestHeader("Content-length", params.length);
http.setRequestHeader("Connection", "close");
http.send(params);
}

var id;
function pollResult() {
ajaxPost("poll.php","id="+id,function()
	{
		if(http.readyState == 4 && http.status == 200) {
			var json = http.responseText;
			obj = JSON.parse(json);
			document.getElementById("response").innerHTML=obj.msg;
			if (obj.done)
			  {
			  }
			else
			  {
			  setTimeout(pollResult,1000);
			  }
		}
	});
}

function startPolling(new_id) {
	id = new_id;
	pollResult();
}
