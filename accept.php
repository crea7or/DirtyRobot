<?
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

date_default_timezone_set('UTC');


//#######################################################
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head><title>sci feed</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<meta http-equiv="Content-Language" content="ru"/>
<style type="text/css">
body
{
	margin-top: 10px;
	font-family: Tahoma, Sans-serif;
	font-size: 10pt;
	text-align: left;
}
table
{
	font-family: Tahoma, Sans-serif;
	font-size: 10pt;
	text-align: left;
}
</style>
<script>

</script>
</head>
<body text="#000000" vlink="#103e65" alink="#2491ec" link="#1963a1" bgcolor="#ffffff">
	<table align="center" Width="900">
		<tr><td align="center">


<?
//#######################################################


$start = $_GET["first"];
if ( is_numeric($start) === false )
{
	$start = 0;
}

$step = 10;
$okdb = false;
$errstr = "";

// ####### db work
$mysqli = new mysqli("localhost", "", "", ""); <- set your own here
if ($mysqli->connect_errno)
{
	echo("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error."\n" );
	$errstr = "Failed to connect to MySQL";
}
else
{
	$mysqli->set_charset('utf8');
	//echo $mysqli->host_info . "\n";
	// ####### db work

	$mysqli->query("LOCK TABLES aqueue WRITE");
	$mysqlRes = $mysqli->query("SELECT * FROM aqueue ORDER BY dt ASC LIMIT ".$start.",".$step);
	if (!$mysqlRes)
	{
	    echo("\nmysql select aqueue query error: (" . $mysqli->errno . ") " . $mysqli->error."\n");
	    $errstr = "mysql select aqueue query error";
	}
	else
	{
		$okdb = true;
	}
	$mysqli->query("UNLOCK TABLES");
}

echo( '<div style="background: #FFBD00; padding: 15px;"><p>');

if ( strlen($errstr) > 0 )
{
	echo( '<div style="background: red; padding: 15px;"><p>'.$errstr."</p></div><br>" );
}
else
{
	echo('<a href="?first=0">начало</a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
	echo('<a href="?first='.$start.'">следующие</a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');

	if ( $start > 0)
	{
		$prev =  $start - $step;
		if ( $prev < 0 )
		{
			$prev = 0;
		}
		echo('<a href="?first='.$prev.'">назад</a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');
	}

	if ( $mysqlRes->num_rows == $step )
	{
		$next = $start + $step;
		echo('<a href="?first='.$next.'">пропустить</a>');
	}
}

echo( "</p></div><br>" );

echo('</td></tr><tr><td>');

if ( $mysqlRes->num_rows < 1 )
{
	echo( "\nmysql: no posts in table \n");	
	$okdb = false;
}
else
{
	$okdb = true;
}

if ( $okdb )
{
	$posts = array();

	while ($post = $mysqlRes->fetch_assoc())
	{
	    $posts[] = $post;
	}

	echo('<div id="posts"></div>'."\n");

?>
	<script type="text/javascript">


	function act( id, act )
	{
		console.log("obj"+id);
		document.getElementById("obj"+id).disabled = true;

    	var xmlhttp = new XMLHttpRequest();

    	xmlhttp.onreadystatechange = function()
    	{
    		if (xmlhttp.readyState == XMLHttpRequest.DONE )
    		{
	        	if (xmlhttp.status == 200)
	        	{
	        		postDiv = document.getElementById("obj"+id);
	        		postDiv.parentNode.removeChild(postDiv);
	            	console.log('200');

				}
				else
				{
					console.log("status: "+xmlhttp.status);
	            	document.getElementById("obj"+id).disabled = false;
				}
			}
			else
			{
           		console.log("rst: "+xmlhttp.readyState);
			}
        };

	    xmlhttp.open("GET", "action.php?item="+id+"&action="+act, true);
	    xmlhttp.send();
	}

	var posts=[];
	var postsIds=[];

<?		

	foreach ($posts as $item)
	{
		echo("posts.push(".(string)base64_decode( $item["json"]).");\n");
		echo("postsIds.push(".(int)( $item["id"]).");\n");
	}
	echo("</script>\n");
	
	?>
	
	<script type="text/javascript">
	
	var cont = document.getElementById("posts");
	if ( cont )
	{
		var arrayLength = posts.length;
		for (var i = 0; i < arrayLength; i++)
		{
			var pst = posts[i];
			//console.log( pst );

			mainDiv = document.createElement("div");
			mainDiv.setAttribute('id', 'obj'+postsIds[i] );
			mainDiv.setAttribute('style', 'background: #F0F0F0; padding: 5px;');

			// control panel
			newDiv = document.createElement("div");
			newDiv.setAttribute('style', 'background: #FFDD20; padding: 15px;');
			newA = document.createElement("a");			
			newA.setAttribute('href', "#" );
			newA.setAttribute('onclick', "act("+postsIds[i]+","+1+"); return false;" );
			newA.appendChild( document.createTextNode("публикуем"));
			newDiv.appendChild( newA );
			newDiv.appendChild( document.createTextNode( '\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0' ));
			newA = document.createElement("a");			
			newA.setAttribute('href', "#" );
			newA.setAttribute('onclick', "act("+postsIds[i]+","+0+"); return false;" );
			newA.appendChild( document.createTextNode("фигня какая-то"));
			newDiv.appendChild( newA );
			// control panel

			mainDiv.appendChild( newDiv );


			newDiv = document.createElement("div");
			newDiv.setAttribute('style', 'margin: 5px;' );
			newA = document.createElement("a");
			if (typeof pst.data.link !== "undefined")
			{		
				newA.setAttribute('href', pst.data.link.url );
			}

			newH2 = document.createElement("h2");
			newH2.appendChild( document.createTextNode( pst.data.title ));
			newA.appendChild( newH2 );
			newDiv.appendChild( newA );


			if ( pst.data.type === "link")
			{
				if ( pst.data.link.type == "web")
				{
					if (typeof pst.data.media !== "undefined" &&  pst.data.media != null )
					{
						newImg = document.createElement("img");
						newImg.setAttribute('src', pst.data.media.url );
						newImg.setAttribute('style', "max-width: 900px;" );
						newDiv.appendChild( newImg );						
					}
				}
				else if ( pst.data.link.type == "video")
				{
					newIfr = document.createElement("iframe");
					newIfr.setAttribute('src', pst.data.url );
					newIfr.setAttribute('allowfullscreen', "" );
					newIfr.setAttribute('width', "480" );
					newIfr.setAttribute('height', "270" );
					newDiv.appendChild( newIfr );
				}

				pstText = document.createElement("div");
				pstText.setAttribute('style', 'margin: 5px;' );
				pstText.innerHTML = pst.data.text;
				newDiv.appendChild( pstText );	
			}
			else if ( pst.data.type === "article")
			{
				if (typeof pst.data.blocks !== "undefined")
				{
					
					var bLength = pst.data.blocks.length;
					for (var j = 0; j < bLength; j++)
					{
						pstText = document.createElement("div");
						pstText.setAttribute('style', 'margin: 5px;');
						if ( pst.data.blocks[j].type === "text")
						{
							pstText.innerHTML = pst.data.blocks[j].text;							
						}
						else if ( pst.data.blocks[j].type === "image")
						{
							var newImg = document.createElement("img");
							newImg.setAttribute('src', pst.data.blocks[j].url );
							newImg.setAttribute('style', "max-width: 900px;" );
							pstText.appendChild( newImg );						
						}
						else if ( pst.data.blocks[j].type === "embed")
						{						
							var newIfr = document.createElement("iframe");
							newIfr.setAttribute('src', pst.data.blocks[j].url );
							newIfr.setAttribute('allowfullscreen', "" );
							newIfr.setAttribute('width', "480" );
							newIfr.setAttribute('height', "270" );
							pstText.appendChild( newIfr );
						}
						newDiv.appendChild( pstText );
					}					
				}
			}
			
			mainDiv.appendChild( newDiv );

			// control panel
			newDiv = document.createElement("div");
			newDiv.setAttribute('style', 'background: #FFDD20; padding: 15px;');
			newA = document.createElement("a");
			newA.setAttribute('href', "#" );
			newA.setAttribute('onclick', "act("+postsIds[i]+","+1+"); return false;" );
			newA.appendChild( document.createTextNode("публикуем"));
			newDiv.appendChild( newA );
			newDiv.appendChild( document.createTextNode( '\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0\u00A0' ));
			newA = document.createElement("a");
			newA.setAttribute('href', "#" );
			newA.setAttribute('onclick', "act("+postsIds[i]+","+0+"); return false;" );
			newA.appendChild( document.createTextNode("фигня какая-то"));
			newDiv.appendChild( newA );
			// control panel

			mainDiv.appendChild( newDiv );


			cont.appendChild( mainDiv );
			cont.appendChild( document.createElement("br"));
			cont.appendChild( document.createElement("br"));
			cont.appendChild( document.createElement("br"));
		}
	}
	else
	{
		console.log('no cont');
	}


</script>

	<?

}

?>
</td></tr></table>
</body>
</html>
