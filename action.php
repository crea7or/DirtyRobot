<?
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

date_default_timezone_set('UTC');

// ####### db work
$mysqli = new mysqli("localhost", "", "", ""); <- set your own here
if ($mysqli->connect_errno)
{	
	header("HTTP/1.1 500 Not Found");
	die();
}
$mysqli->set_charset('utf8');
// ####### db work

$itemId = $_GET['item'];
$action = $_GET['action'];
if ( is_numeric($itemId) === true && is_numeric($action) === true )
{
	$table = "squeue";
	if ( $action == 0) // delete post
	{
		$table = "dqueue";
	}	

	$mysqlRes = $mysqli->query("LOCK TABLES ".$table." WRITE, aqueue WRITE");
	if ( $mysqlRes )
	{
		$mysqlRes = $mysqli->query("INSERT INTO ".$table." SELECT * FROM aqueue WHERE id=".$itemId );
		if ($mysqlRes)
		{
			$mysqlRes = $mysqli->query("DELETE FROM aqueue WHERE id=".$itemId );
			if ($mysqlRes)
			{
				header("HTTP/1.1 200 OK");			
			}	
			else
			{
				header("HTTP/1.1 500 Can't delete");			
			}
		}
		else
		{
			error_log( $mysqli->error );
			header("HTTP/1.1 500 Can't insert");		
		}
	}
	else
	{
		header("HTTP/1.1 500 Can't lock");
	}
	$mysqlRes = $mysqli->query("UNLOCK TABLES");
	die();
}

header("HTTP/1.1 404 Not Found");

?>