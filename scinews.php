<?
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_regex_encoding('UTF-8');

date_default_timezone_set('UTC');

// Dirty API classes
class PostData
{
	public $data;
	public $tags;
}

class LinkPostDataLink
{
	public $url;
	public $type;
}

class LinkPostData
{
	public $title;
	public $text;
	public $render_type;
	public $link;
	public $media;
	public $type;
}

class LinkPostDataMedia
{
	public $url;
	public $type;
}

class ArticlePostData
{
	public $subtitle;
	public $title;
	public $header_color; // enum string white black
	public $cover_image; // Cover Image
	public $blocks; // array one of Text Block Embed Block Image Block
	public $type; //constant Constant string article
}

class ArticlePostTextBlock
{
	public $text;
	public $type; // constant Constant string text
}

class ArticlePostImageBlock
{
	public $url;
	public $text;
	public $align; // enum string right center left
	public $type; // constant Constant string image
}

class ArticlePostEmbedBlock
{
	public $url;
	public $align; // enum string right center left
	public $type; // constant Constant string embed
}
// Dirty API classes

// RSS XML classes
class RssPost
{
    public $dateTime;
    public $link;
    public $title;
    public $description;
    public $categories = array();
}
// RSS XML classes


// ####### db work
$mysqli = new mysqli("localhost", "", "", ""); <- set your own here
if ($mysqli->connect_errno)
{
	echo("Failed to connect to MySQL: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error );
}
$mysqli->set_charset('utf8');
//echo $mysqli->host_info . "\n";
// ####### db work

do
{
		$feedLastFetchTime = 0;

		// select feeds
		$mysqli->query("LOCK TABLES feeds WRITE");
		$mysqlRes = $mysqli->query("SELECT * FROM  feeds ORDER BY lastFetchTime");
		if (!$mysqlRes)
		{
		    echo("\nmysql select feeds query error: (" . $mysqli->errno . ") " . $mysqli->error."\n");
		}
		$mysqli->query("UNLOCK TABLES");
		if (!$mysqlRes)
		{
			break;
		}
		// select feeds


		if ( $mysqlRes->num_rows < 1 )
		{
			echo( "\nmysql: no feeds in table \n");
			break;
		}

		$dbRow = $mysqlRes->fetch_assoc();
		echo($dbRow['rssLink'] ."<br>");

		$feedLastFetchTime = time();

		// update feeds
		$mysqli->query("LOCK TABLES feeds WRITE");
		$mysqlRes = $mysqli->query("UPDATE feeds SET lastFetchTime = '".$feedLastFetchTime."' WHERE lastFetchTime = '".$dbRow['lastFetchTime']."'");
		if (!$mysqlRes)
		{
		    echo("\nmysql update feeds query error: (" . $mysqli->errno . ") " . $mysqli->error."\n");			
		}
		$mysqli->query("UNLOCK TABLES");
		if (!$mysqlRes)
		{
			break;
		}
		// updare feeds

		$feed = simplexml_load_file($dbRow['rssLink']);
 		if ( $feed === FALSE )
 		{
			echo( "\nfailed to load feed from url: ".$dbRow['rssLink']."\n");
			break;
        }

        $rssPosts = array();

		$lastPostDateTime = 0;
      	foreach ($feed->channel->item as $item)
        {
        	$dateTime = strtotime($item->pubDate);
        	if ( $dateTime > $dbRow['lastItemTime'])
        	{
        		if ( $lastPostDateTime < $dateTime )
        		{
        			$lastPostDateTime = $dateTime;
        		}

        		$post = new RssPost();
        		$post->dateTime = (int)$dateTime;
        		$post->link = trim((string)$item->link );
        		$post->title = trim((string)$item->title );
		   		$post->description = $item->description;

				$catcount = 0;
				$post->categories = array();
        		foreach($item->category as $category)
				{				    
				    $post->categories[] = $category;
			    	$catcount++;
				    if ( $catcount > 4 )
				    {
				    	break;
				    }
				}
				$rssPosts[] = $post;
			}
        }

        echo("posts count: ".count($rssPosts)."<br>");

        if ( $lastPostDateTime > 0 )
        {
        	// updare feeds
        	$mysqli->query("LOCK TABLES feeds WRITE");
			$mysqlRes = $mysqli->query("UPDATE feeds SET lastItemTime ='".$lastPostDateTime ."' WHERE lastFetchTime = '".$feedLastFetchTime."' ");
			if (!$mysqlRes)
			{
			    echo("\nmysql update feeds query error: (" . $mysqli->errno . ") " . $mysqli->error."\n");
			}
			$mysqli->query("UNLOCK TABLES");
			if (!$mysqlRes)
			{
				break;
			}
			// updare feeds

	     	foreach ($rssPosts as $post)
	        {
	        	
	        	echo($post->dateTime . "<br>");
	        	echo($post->link . "<br>");
	        	echo($post->title . "<br>");      
		     	foreach ($post->categories as $category)
		        {
		        	echo($category . " / "); 
		        }
				echo ("<br>");
				echo ("<br>");				
				

				// load post into DOM
				$domDoc = loadHtmlToDom( $post->description );
				// check for valid post
				if ( $domDoc === FALSE )
				{
					echo( "\nerror in post: ".$post->link." not valid DOM \n");
					continue;
				}


				$iter = 0;
				while( cleanNodes($domDoc ) > 0 )
				{
					$iter++;
				};
				removeFbimages( $domDoc );

				$imgs = getNodesByName( $domDoc, "img");
				$imgCnt = count( $imgs );
				if ( $imgCnt == 1 )
				{
					$post_image_url = $imgs[0]->getAttribute("src");
				}

				$vids = getNodesByName($domDoc, "iframe");
				$vidCnt = count( $vids );
				if ( $vidCnt == 1 )
				{
					$post_video_url = $vids[0]->getAttribute("src");
				}


				echo( "<br>imgs: ".$imgCnt." vids: ".$vidCnt."<br>");

				// array to change br's to end of line
				$endOfLine = array(	"<br>" => "\x0A" );

				// post object
				$postdata = new PostData();
				$var_postType = 0;
				// 0 - simple text
				// 1 - simple image
				// 2 - simple video
				// 3 - article
				$postdata->tags = array();
		     	foreach ($post->categories as $category)
		        {
		        	$postdata->tags[] = (string)$category;
		        }

				if ( $imgCnt === 0 && $vidCnt === 0 )
				{
					// simple post, only text in post
					$var_postType = 0;
					$linkdata = new LinkPostDataLink();
					$linkpost = new LinkPostData();
					$linkdata->url = $post->link;
					$linkdata->type = 'web';
					$linkpost->title = strip_tags( $post->title );
					$linkpost->text = strip_tags( strtr( $domDoc->saveHTML(), $endOfLine ), "<a>");
					$linkpost->render_type = 'maxi';
					$linkpost->link =  $linkdata;
					$linkpost->media = null;
					$linkpost->type = 'link';
					$postdata->data = $linkpost;
				}
				else if ( $imgCnt === 1 && $vidCnt === 0 )
				{
					// Only one image in post
					$var_postType = 1;
					$linkdata = new LinkPostDataLink();
					$linkpost = new LinkPostData();
					$linkpostmedia = new LinkPostDataMedia();
					$linkdata->url = $post->link;
					$linkdata->type = 'web';
					$linkpostmedia->url = $post_image_url;
					$linkpostmedia->type = 'image';
					$linkpost->title = strip_tags( $post->title );
					$linkpost->text = strip_tags( strtr( $domDoc->saveHTML(), $endOfLine ), "<a>");
					$linkpost->render_type = 'midi';
					$linkpost->link =  $linkdata;
					$linkpost->media = $linkpostmedia;
					$linkpost->type = 'link';
					$postdata->data = $linkpost;
				}
				else if ( $var_img == 0 && $var_video == 1 )
				{
					// Only one video in post
					$var_postType = 2;
					$linkdata = new LinkPostDataLink();
					$linkpost = new LinkPostData();
					$linkdata->url = $post_video_url;
					$linkdata->type = 'video';
					$linkpost->title = strip_tags( $post->title );
					$linkpost->text = strip_tags( strtr( $domDoc->saveHTML(), $endOfLine ), "<a>");
					 // add link to original post
					$linkpost->text .= '<a href="'. $post->link.'">'."Ссылка на источник"."</a>.\x0A";
					$linkpost->render_type = 'midi';
					$linkpost->link =  $linkdata;
					$linkpost->media = null;
					$linkpost->type = 'link';
					$postdata->data = $linkpost;
				}
				else
				{
					// article
					$var_postType = 3;

					$articlepost = new ArticlePostData();
					$articlepost->title = strip_tags( $post->title );
					$articlepost->blocks = getArticleBlocksFromDOMNode( $domDoc );

					// add link to original post
					$articlepost->blocks[] = createTextBlock( '<a href="'. $post->link.'">'."Ссылка на источник"."</a>." );

					$articlepost->type = 'article';
					$postdata->data = $articlepost;
				}

				$jsonResult = json_encode($postdata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

				// adding record
				$mysqli->query("LOCK TABLES aqueue WRITE");
				$sql = "INSERT INTO aqueue (link, json, dt) VALUES ('".base64_encode($post->link)."','".base64_encode($jsonResult)."',".$post->dateTime.")";
				$mysqlRes = $mysqli->query( $sql );
				if (!$mysqlRes)
				{
				    echo("\nmysql update feeds query error: (" . $mysqli->errno . ") " . $mysqli->error. " rss link: " . $post->link ."\n");
				}
				else
				{
					echo( "\npost: ".$post->link." added into aqueue db\n");
				}
				$mysqli->query("UNLOCK TABLES");
				if (!$mysqlRes)
				{
					break;
				}
				// adding record			
			}
        }


}while(false);



//##############################################
//##############################################
//#####          functions            ##########
//##############################################
//##############################################

function getArticleBlocksFromDOMNode( $bodyNode )
{
	$blocks = array();
	$posttext = "";

	foreach( $bodyNode->childNodes as $item )
	{
		if ( $item->nodeName == "a" )
		{
			// first check for images inside
			$imgs = getNodesByName($item, "img" );
			if ( count( $imgs ) > 0 )
			{
				if ( strlen( $posttext ) > 0 )
				{
					$blocks[] = createTextBlock($posttext);
					$posttext = "";
				}
				foreach ($imgs as $img )
				{
					$imageUrl = $img->getAttribute("src");
					if ( strlen( $imageUrl) > 0 )
					{
						$altText = $img->getAttribute("alt");
						$blocks[] = createImageBlock( $imageUrl, $altText );
					}					
				}
			}
			else
			{
				$posttext .= '<a href="'. $item->getAttribute("href").'">'.$item->textContent."</a>";
			}			
		}
		else if ( $item->nodeName == "img" )
		{
			if ( strlen( $posttext ) > 0 )
			{
				$blocks[] = createTextBlock($posttext);
				$posttext = "";
			}

			// first check for images
			$imageUrl = $item->getAttribute("src");
			if ( strlen( $imageUrl) > 0 )
			{
				$altText = $item->getAttribute("alt");
				$blocks[] = createImageBlock( $imageUrl, $altText );
			}
		}
		else if ( $item->nodeName == "iframe" )
		{
			if ( strlen( $posttext ) > 0 )
			{
				$blocks[] = createTextBlock($posttext);
				$posttext = "";
			}

			$videoUrl = $item->getAttribute("src");
			if ( strlen( $videoUrl ) > 0 )
			{
				$block = new ArticlePostEmbedBlock();
				$block->url = $videoUrl;
				$block->type = "embed";
				$block->align = "center";
				$blocks[] = $block;
			}
		}
		else if ( $item->nodeName == "br" )
		{
			$posttext .= "\x0A";
		}
		else if ( $item->nodeName == "#text" )
		{
			$posttext .= $item->nodeValue;
		}		
	}

	if ( strlen( $posttext ) > 0 )
	{
		$blocks[] = createTextBlock($posttext);
		$posttext = "";
	}
	return $blocks;
}

function createTextBlock( $text )
{
	$block = new ArticlePostTextBlock();
	$block->text = $text;
	$block->type = "text";
	return $block;
}

function createImageBlock( $imageUrl, $altText )
{
	$block = new ArticlePostImageBlock();
	$block->url = $imageUrl;
	$block->type = "image";
	$block->align = "center";
	if ( $altText != null && strlen( $altText ) > 0 )
	{
		$block->text = $altText;
	}
	else
	{
		$block->text = "";
	}
	return $block;
}

function parseNode( $nde, $level )
{
	if ( $nde->childNodes )
	{
	   	foreach ($nde->childNodes as $nd)
		{
	       	parseNode( $nd, ($level + 1));
	 	}
	}

  	for( $i = 0; $i< $level; $i++)
	{
		echo(" ");
	};
	if ( $nde->nodeName == "#text")
	{		
		echo( $nde->textContent . "\n");
	}
	else
	{
		echo( $nde->nodeName . "\n");
	}
}

function countNodeName( $nde, $name )
{
	$count = 0;
	if ( $nde->childNodes )
	{
	   	foreach ($nde->childNodes as $nd)
		{
	       	$count += countNodeName( $nd, $name );
	 	}
	}

	if ( $nde->nodeName === $name)
	{		
		$count++;
	}
	return $count;
}

function getNodesByName( $nde, $name )
{
	$elems = array();
	if ( $nde->childNodes )
	{
	   	foreach ($nde->childNodes as $nd)
		{
	       	$elems = array_merge( getNodesByName( $nd, $name ), $elems);
	 	}
	}

	if ( $nde->nodeName === $name)
	{
		$elems[] = $nde;
	}
	return $elems;
}

function removeFbimages( $nde )
{
	if ( $nde->childNodes )
	{
		$onepiximg = array();

	   	foreach ($nde->childNodes as $nd)
		{
	       	removeFbimages( $nd );

			if ( $nd->nodeName === "img")
			{
				// filter bad images as we know	
				$imgnd = $nd->attributes->getNamedItem('src');
				if ( $imgnd != NULL )
				{
					if ( strpos( $imgnd->nodeValue, "l-stat.livejournal.net" ) !== false )
					{
						$onepiximg[] = $nd;
					}				
					else if (strpos( $imgnd->nodeValue, "feeds.feedburner.com" ) !== false )
					{
						$onepiximg[] = $nd;
					}
				}				
			}
	 	}
	   	foreach ($onepiximg as $rimg)
	 	{
	 		$nde->removeChild( $rimg );
	 	}
	}
}

function cleanNodes( $nde )
{
	$nodeOperations = 0;
	if ( $nde->childNodes )
	{		
		do
		{
			$toRemove = array();
			$nodeOperations = 0;
		   	foreach ($nde->childNodes as $nd)
			{
				if ( nameToRemove( (string)$nd->nodeName ))
				{
					if ( $nd->childNodes )
					{
						foreach ($nd->childNodes as $ndc)
						{
							$nn = $ndc->cloneNode( true );
							$nde->insertBefore( $nn, $nd );
						}
						$toRemove[] = $nd;
					}
					else
					{
						$toRemove[] = $nd;	
					}					
					$nodeOperations++;
				}
				else
				{
					if ( $nodeOperations == 0 )
					{
						$nodeOperations = cleanNodes( $nd );
					}
				}			
		 	}

		   	foreach ($toRemove as $remNd)
			{
				$nde->removeChild( $remNd );
			}

		}while( $nodeOperations > 0 );

	}
	return $nodeOperations;
}

function nameToRemove( $ndName )
{
	if ( $ndName === "a" || $ndName === "img" || $ndName === "iframe" || $ndName === "#text" )
	{
		return false;
	}
	return true;
}

function loadHtmlToDom( $content )
{
	$pairs = array(
		"\x0A" => "",
		"\x0D" => ""
	);
	$prePostBody = strip_tags( strtr( $content, $pairs ), "<img><a><p><br><iframe>");

	$pairs = array(
		"</p>" => "\x0A",
		"<br/>" => "\x0A",
		"<br />" => "\x0A",
		"<br>" => "\x0A",
		"<p>" => "\x0A"
	);

	$content = strip_tags( strtr( $prePostBody, $pairs ), "<img><a><iframe>");

	$dom = new DomDocument('1.0', 'UTF-8');
	$res = $dom->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.$content.'</body></html>');
	if (!$res)
	{
		return FALSE;
	}
	return $dom;
}

?>