<?
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

date_default_timezone_set('UTC');

// ####### db work
$mysqli = new mysqli("localhost", "", "", "");  <- set your own here
if ($mysqli->connect_errno)
{
	echo("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error );
}
$mysqli->set_charset('utf8');
//echo $mysqli->host_info . "\n";
// ####### db work

// select squeue
$mysqli->query("LOCK TABLES squeue WRITE");
$mysqlRes = $mysqli->query("SELECT * FROM squeue ORDER BY dt ASC LIMIT 0,10");
if (!$mysqlRes)
{
	echo("\nmysql select posts from squeue error: (" . $mysqli->errno . ") " . $mysqli->error."\n");
}
$mysqli->query("UNLOCK TABLES");
if (!$mysqlRes)
{
	die();
}
// select squeue

if ( $mysqlRes->num_rows < 1 )
{
	echo( "\nmysql: no feeds in table \n");
	die();	
}


//$dbRow = $mysqlRes->fetch_assoc();

$posts = array();
while ($post = $mysqlRes->fetch_assoc())
{
    $posts[] = $post;
}

foreach ($posts as $item)
{

	$sednresult = sendPost( base64_decode( $item['json'] ));

	if ( $sednresult !== NULL && isset( $sednresult['id'] ))
	{
		// post id created
		// delete squeue
		$mysqli->query("LOCK TABLES squeue WRITE");
		$mysqlRes = $mysqli->query("DELETE FROM squeue WHERE id=".$item['id'] );
		if (!$mysqlRes)
		{
			echo("\nmysql delete post: ".$sednresult['id'].", from squeue error: (" . $mysqli->errno . ") " . $mysqli->error."\n");
		}
		$mysqli->query("UNLOCK TABLES");
		if (!$mysqlRes)
		{
			die();
		}
		break;
		// delete squeue
	}
	else
	{
		$result = var_export(  $sednresult, true );
		echo( "\npost did not sent:". $result ."\n");
		//sleep( 2 );
	}
}


function sendPost( $postJson )
{
	$ch = curl_init("https://dirty.ru/api/posts/?domain_prefix=yoursubdomain"); <- set your own here

	$headers = array();
	$headers[] = 'Host: dirty.ru';
	$headers[] = 'Origin: https://dirty.ru';
	$headers[] = 'Content-type: application/json';
	$headers[] = 'Content-Length: ' . strlen($postJson);
	$headers[] = 'X-Futuware-SID: '; <- set your own here
	$headers[] = 'X-Futuware-UID: '; <- set your own here

	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.3; Win64, x64; Trident/7.0; Touch; rv:11.0) like Gecko");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POST, 1 );
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postJson);

	//perform our request
	$result = curl_exec($ch);
	curl_close($ch);

	//echo( var_export( $result, true ));

	return json_decode($result, true);
}

?>