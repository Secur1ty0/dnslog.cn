<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>DNSLog Platform</title>
<meta name="keywords" content="dnslog，dnslog平台"/>
<meta name="description" content="DNSLog平台"/>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
<div id=header style="text-align:center">
<a href=""><img src="banner.png" width="400" height="150"></a>
<hr style=" height:2px;border:none;border-top:2px dashed #87CEFA;"/><br>
</div>
<script>
function GetDomain()
{
	var xmlhttp;
	if (window.XMLHttpRequest)
	{
		xmlhttp=new XMLHttpRequest();
	}
	else
	{
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function()
	{
		if (xmlhttp.readyState==4 && xmlhttp.status==200)
		{
			document.getElementById("myDomain").innerHTML=xmlhttp.responseText;
		}
	}
	xmlhttp.open("GET","/getdomain.php?t="+ Math.random(),true);
	xmlhttp.send();
}

function GetRecords()
{
	var xmlhttp;
	if (window.XMLHttpRequest)
	{

		xmlhttp=new XMLHttpRequest();
	}
	else
	{
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	xmlhttp.onreadystatechange=function()
	{
		if (xmlhttp.readyState==4 && xmlhttp.status==200)
		{
			var abc = xmlhttp.responseText;
			obj = JSON.parse(abc);
			if(obj==""){
				ktable = "<tr bgcolor=\"#ADD3EF\"><th width=\"50%\">DNS Query Record</th><th width=\"25%\">IP Address</th><th width=\"25%\">Created Time</th></tr><td colspan=\"3\" align=\"center\">No Data</td>";
				document.getElementById("myRecords").innerHTML = ktable;
			}else{
			table = "<tr bgcolor=\"#ADD3EF\"><th width=\"50%\">DNS Query Record</th><th width=\"25%\">IP Address</th><th width=\"25%\">Created Time</th></tr>";
            for (var i = 0; i < obj.length ; i++ )
            {
				
				table = table + "<tr><td>"+obj[i][0]+"</td><td>"+obj[i][1]+"</td>"+"<td>"+obj[i][2]+"</td></tr>";
            }
			document.getElementById("myRecords").innerHTML = table;
		}
		}
	}
	xmlhttp.open("GET","/getrecords.php?t="+Math.random(),true);
	xmlhttp.send();
}
</script>
<div id="content" style="text-align:center;">
<button type="button" onclick="GetDomain()">Get SubDomain</button>
<button type="button" onclick="GetRecords()">Refresh Record</button><br><br>
<div id="myDomain">&nbsp;</div><br>
<center><table id="myRecords" width=700 border="0" cellpadding="5" cellspacing="1" bgcolor="#EFF3FF" style="word-break:break-all; word-wrap:break-all;">
  <tr bgcolor="#ADD3EF"><th width="50%">DNS Query Record</th><th width="25%">IP Address</th><th width="25%">Created Time</th>
  </tr>
  <tr>
    <td colspan="3" align="center">No Data</td>
  </tr>
</table></center>
</div>
</body>
<br>
<br>
<br>
<br>
<br>
<br>
<div id=footer>
<hr style=" height:2px;border:none;border-top:2px dashed #87CEFA;"/><br>
<center><span style="color:#ADD3EF">Copyright © 2019 DNSLog.cn All Rights Reserved.</span></center>
</div>
</html>