<?php
/*
Tagging System
Version 2.4.1
by:vbgamer45
http://www.smfhacks.com
*/

if (!defined('SMF'))
	die('Hacking attempt...');

function TagsMain()
{
	loadtemplate('Tagging');

	$subActions = array(
		'suggest' => 'SuggestTag',
		'suggest2' => 'SuggestTag2',
		'addtag' => 'TaggingSystem_Add',
		'submittag' => 'TaggingSystem_Submit',
		'deletetag' => 'DeleteTag',
		'admin' => 'TagsSettings',
		'admin2' => 'TagsSettings2',
		'cleanup' => 'TagCleanUp',
	);
	if (!empty($subActions[@$_GET['sa']]))
		$subActions[$_GET['sa']]();
	else
		ViewTags();
}

function ViewTags()
{
	global $context, $txt, $mbname, $scripturl, $user_info, $smcFunc,  $modSettings;

	if (isset($_REQUEST['tagid']))
	{
		// Show the tag results for that tag
		$id = (int) $_REQUEST['tagid'];

		// Find Tag Name
		$dbresult = $smcFunc['db_query']('', "
		SELECT
			tag
		FROM {db_prefix}tags
		WHERE ID_TAG = $id LIMIT 1");
		$row = $smcFunc['db_fetch_assoc']($dbresult);
		$smcFunc['db_free_result']($dbresult);

		$context['tag_search'] = $row['tag'];
		$context['page_title'] = $mbname . ' - ' . $txt['smftags_resultsfor'] . $context['tag_search'];
		$context['start'] = (int) $_REQUEST['start'];
		
		$dbresult = $smcFunc['db_query']('', "
		SELECT count(*) as total 
		FROM ({db_prefix}tags_log as l, {db_prefix}boards AS b, {db_prefix}topics as t, {db_prefix}messages as m)
		
		WHERE l.ID_TAG = $id AND b.ID_BOARD = t.ID_BOARD AND l.ID_TOPIC = t.id_topic  AND t.approved = 1 
		AND t.ID_FIRST_MSG = m.ID_MSG AND " . $user_info['query_see_board'] . " 
		");
		$totalRow = $smcFunc['db_fetch_assoc']($dbresult);
		$numofrows = $totalRow['total'];
		
		// Find Results
		$dbresult = $smcFunc['db_query']('', "
		SELECT t.num_replies,t.num_views,m.id_member,m.poster_name,m.subject,m.id_topic,m.poster_time, t.ID_BOARD
		FROM ({db_prefix}tags_log as l, {db_prefix}boards AS b, {db_prefix}topics as t, {db_prefix}messages as m)
		
		WHERE l.ID_TAG = $id AND b.ID_BOARD = t.ID_BOARD AND l.ID_TOPIC = t.id_topic  AND t.approved = 1 
		AND t.ID_FIRST_MSG = m.ID_MSG AND " . $user_info['query_see_board'] . " 
		ORDER BY m.ID_MSG DESC LIMIT $context[start],25 ");

		$context['tags_topics'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($dbresult))
		{
				$context['tags_topics'][] = array(
				'id_member' => $row['id_member'],
				'poster_name' => $row['poster_name'],
				'subject' => $row['subject'],
				'id_topic' => $row['id_topic'],
				'poster_time' => $row['poster_time'],
				'num_views' => $row['num_views'],
				'num_replies' => $row['num_replies'],

				);


		}
		$smcFunc['db_free_result']($dbresult);


		$context['sub_template']  = 'tagging_results';
		
		$context['page_index'] = constructPageIndex($scripturl . '?action=tags;tagid=' . $id, $_REQUEST['start'], $numofrows, 25);

	}
	else
	{
		$context['page_title'] = $mbname . ' - ' . $txt['smftags_popular'];

		// Tag cloud from http://www.prism-perfect.net/archive/php-tag-cloud-tutorial/
		$result = $smcFunc['db_query']('', "
		SELECT 
			t.tag AS tag, l.ID_TAG, COUNT(l.ID_TAG) AS quantity
		 FROM {db_prefix}tags as t, {db_prefix}tags_log as l WHERE t.ID_TAG = l.ID_TAG
		  GROUP BY l.ID_TAG
		  ORDER BY COUNT(l.ID_TAG) DESC, RAND() LIMIT " .  $modSettings['smftags_set_cloud_tags_to_show']);

		// here we loop through the results and put them into a simple array:
		// $tag['thing1'] = 12;
		// $tag['thing2'] = 25;
		// etc. so we can use all the nifty array functions
		// to calculate the font-size of each tag
		$tags = array();

		$tags2 = array();

		while ($row = $smcFunc['db_fetch_assoc']($result))
		{
		    $tags[$row['tag']] = $row['quantity'];
		    $tags2[$row['tag']] = $row['ID_TAG'];
		}

		if (count($tags2) > 0)
		{
			// change these font sizes if you will
			$max_size = $modSettings['smftags_set_cloud_max_font_size_precent']; // max font size in %
			$min_size = $modSettings['smftags_set_cloud_min_font_size_precent']; // min font size in %

			// get the largest and smallest array values
			$max_qty = max(array_values($tags));
			$min_qty = min(array_values($tags));

			// find the range of values
			$spread = $max_qty - $min_qty;
			if (0 == $spread)
			 { // we don't want to divide by zero
			    $spread = 1;
			}

			// determine the font-size increment
			// this is the increase per tag quantity (times used)
			$step = ($max_size - $min_size)/($spread);

			// loop through our tag array
			$context['poptags'] = '';
			$row_count = 0;
			foreach ($tags as $key => $value)
			{
				$row_count++;
			    // calculate CSS font-size
			    // find the $value in excess of $min_qty
			    // multiply by the font-size increment ($size)
			    // and add the $min_size set above
			    $size = $min_size + (($value - $min_qty) * $step);
			    // uncomment if you want sizes in whole %:
			    // $size = ceil($size);

			    // you'll need to put the link destination in place of the #
			    // (assuming your tag links to some sort of details page)
			    $context['poptags'] .= '<a href="' . $scripturl . '?action=tags;tagid=' . $tags2[$key] . '" style="font-size: '.$size.'%"';
			    // perhaps adjust this title attribute for the things that are tagged
			   $context['poptags'] .= ' title="'.$value.' things tagged with '.$key.'"';
			   $context['poptags'] .= '>'.$key.'</a> ';
			   if ($row_count > ($modSettings['smftags_set_cloud_tags_per_row']-1))
			   {
			   	$context['poptags'] .= '<br />';
			   	$row_count =0;
			   }
			    // notice the space at the end of the link
			}
		}


		// Find Results
		$dbresult = $smcFunc['db_query']('', "
		SELECT DISTINCT l.ID_TOPIC, t.num_replies,t.num_views,m.id_member,
		m.poster_name,m.subject,m.id_topic,m.poster_time, 
		t.id_board, g.tag, g.ID_TAG 
		 FROM ({db_prefix}tags_log as l, {db_prefix}boards AS b, {db_prefix}topics as t, {db_prefix}messages as m) 
		  LEFT JOIN {db_prefix}tags AS g ON (l.ID_TAG = g.ID_TAG)
		 WHERE b.ID_BOARD = t.id_board AND l.ID_TOPIC = t.id_topic AND t.approved = 1 AND t.id_first_msg = m.id_msg AND " . $user_info['query_see_board'] . " ORDER BY l.ID DESC LIMIT 20");

		$context['tags_topics'] = array();
		while ($row = $smcFunc['db_fetch_assoc']($dbresult))
		{
				$context['tags_topics'][] = array(
				'id_member' => $row['id_member'],
				'poster_name' => $row['poster_name'],
				'subject' => $row['subject'],
				'id_topic' => $row['id_topic'],
				'poster_time' => $row['poster_time'],
				'num_views' => $row['num_views'],
				'num_replies' => $row['num_replies'],
				'ID_TAG' => $row['ID_TAG'],
				'tag' => $row['tag'],

				);
		}
		$smcFunc['db_free_result']($dbresult);


	}
	
	
	$context['linktree'][] = array(
					'url' => $scripturl . '?action=tags',
					'name' => $txt['smftags_menu']
				);

}

/*
 * show the form for adding one or more tags.
 */
function TaggingSystem_Add()
{
	global $context, $txt, $mbname, $user_info, $smcFunc;

	$ajaxrequest = $_REQUEST['action'] == 'xmlhttp' ? true : false;
	
	if($ajaxrequest) {
		loadTemplate('Tagging');
		$context['sub_template']  = 'ajax_addtag';
	}
	else {
		isAllowedTo('smftags_add');
		$context['sub_template']  = 'addtag';
	}
	
	$topic = (int) $_REQUEST['topic'];

	if (empty($topic))
		fatal_error($txt['smftags_err_notopic'],false);

	$edit = allowedTo('smftags_manage');


	$dbresult = $smcFunc['db_query']('', '
	SELECT m.id_member FROM {db_prefix}topics as t, {db_prefix}messages as m 
		WHERE t.id_first_msg = m.id_msg AND t.id_topic = {int:topic} LIMIT 1', 
		array('topic' => $topic));

	$row = $smcFunc['db_fetch_assoc']($dbresult);
	$smcFunc['db_free_result']($dbresult);

	if ($user_info['id'] != $row['ID_MEMBER'] && $edit == false)
		fatal_error($txt['smftags_err_permaddtags'],false);

	$context['tags_topic'] = $topic;
	$context['page_title'] = $mbname . ' - ' . $txt['smftags_addtag'];
}

function TagErrorMsg($msg, $isajax)
{
	if(!$isajax)
		fatal_error($msg, false);
	else {
		echo $msg;
		die;
	}
}

/*
 * handles submission of one or more new tags either through ajax/xmlhttp
 * or conventional form
 */
function TaggingSystem_Submit()
{
	global $txt, $modSettings, $smcFunc, $user_info;

	$isajax = $_REQUEST['action'] == 'xmlhttp' ? true : false;
	
	if(!$isajax)
		isAllowedTo('smftags_add');
	else {
		if(!allowedTo('smftags_add'))
			TagErrorMsg('No permission');
	}
	
	$topic = (int) $_REQUEST['topic'];

	if (empty($topic))
		TagErrorMsg($txt['smftags_err_notopic']);

	$edit = allowedTo('smftags_manage');

	$result = $smcFunc['db_query']('', '
		SELECT t.id_member_started FROM {db_prefix}topics AS t
		WHERE t.id_topic = {int:topic}',
		array('topic' => $topic));

	$row = $smcFunc['db_fetch_assoc']($result);
	$smcFunc['db_free_result']($result);

	if ($user_info['id'] != $row['id_member_started'] && $edit == false)
		TagErrorMsg($txt['smftags_err_permaddtags']);

	$result = $smcFunc['db_query']('', '
		SELECT COUNT(*) as total FROM {db_prefix}tags_log WHERE ID_TOPIC = {int:topic}',
		array('topic' => $topic));

	$row = $smcFunc['db_fetch_assoc']($result);
	$totaltags = $row['total'];
	$smcFunc['db_free_result']($result);

	if ($totaltags >= $modSettings['smftags_set_maxtags'])
		TagErrorMsg($txt['smftags_err_toomaxtag']);

	// Check Tag restrictions
	$tag = htmlspecialchars(trim($_REQUEST['tag']),ENT_QUOTES);
	$tag = strtolower($tag);
	
	if (empty($tag))
		TagErrorMsg($txt['smftags_err_notag']);
	
	$tags = explode(',',htmlspecialchars($tag,ENT_QUOTES));

	foreach($tags as $tag)
	{
		$tag = trim($tag);
		if (strlen($tag) < $modSettings['smftags_set_mintaglength'])
			continue;
		if (strlen($tag) > $modSettings['smftags_set_maxtaglength'])
			continue;

		$dbresult = $smcFunc['db_query']('', 'SELECT id_tag FROM {db_prefix}tags 
			WHERE tag = {string:tag} LIMIT 1',
			array('tag' => $tag));
	
		if ($smcFunc['db_affected_rows']() == 0) {
			$smcFunc['db_query']('', 'INSERT INTO {db_prefix}tags
				(tag, approved)	VALUES ({string:tag},1)',
				array('tag' => $tag));
			
			$ID_TAG = $smcFunc['db_insert_id']("{db_prefix}tags",'id_tag');
	
			$smcFunc['db_query']('', 'INSERT INTO {db_prefix}tags_log (id_tag, id_topic, id_member)
			VALUES ({int:id_tag}, {int:topic}, {int:id_user})',
			array('id_tag' => $ID_TAG, 'topic' => $topic, 'id_user' => $user_info['id']));
		}
		else
		{
			$row = $smcFunc['db_fetch_assoc']($dbresult);
			$ID_TAG = $row['id_tag'];
			$dbresult2= $smcFunc['db_query']('', 'SELECT id	FROM {db_prefix}tags_log WHERE id_tag = {int:id_tag}
			 	AND id_topic = {int:topic}',
			 	array('id_tag' => $ID_TAG, 'topic' => $topic));
	
			if ($smcFunc['db_affected_rows']() != 0)
				continue;
				
			$smcFunc['db_free_result']($dbresult2);
			
			$smcFunc['db_query']('', 'INSERT INTO {db_prefix}tags_log (id_tag, id_topic, id_member)
			VALUES ({int:id_tag}, {int:id_topic}, {int:id_user})',
				array('id_tag' => $ID_TAG, 'id_topic' => $topic, 'id_user' => $user_info['id']));
		}
		$smcFunc['db_free_result']($dbresult);
	}
	if($isajax) {
		// construct the new tag list and return it for DOM insertion
		$result= $smcFunc['db_query']('', 'SELECT t.tag, l.ID, t.ID_TAG
        	FROM {db_prefix}tags_log as l, {db_prefix}tags as t
        	WHERE t.ID_TAG = l.ID_TAG && l.ID_TOPIC = {int:topic}',
        	array('topic' => $topic));
        	
        $tags = array();
        $output = '';
        
        while($row = $smcFunc['db_fetch_assoc']($result)) {
        	$output .= ' <a href="'.$scripturl.'?action=tags;tagid='.$row['ID_TAG'].'">'.$row['tag'].'</a>';
        	$output .= '<a href="'.$scripturl.'?action=tags;sa=deletetag;tagid='.$row['ID'].'"><span class="xtag">&nbsp;&nbsp;</span></a>';
		}
		$smcFunc['db_free_result']($result);
		echo $output;
		die;
	}
	redirectexit('topic=' . $topic);
}

function DeleteTag()
{
	global $txt, $smcFunc, $user_info;

	isAllowedTo('smftags_del');

	$id = (int) $_REQUEST['tagid'];
	// Check permission
	$a_manage = allowedTo('smftags_manage');

	$dbresult = $smcFunc['db_query']('', "
	SELECT
		ID_MEMBER, ID_TOPIC, ID_TAG
	FROM {db_prefix}tags_log 
	WHERE ID = $id LIMIT 1");

	$row = $smcFunc['db_fetch_assoc']($dbresult);
	$smcFunc['db_free_result']($dbresult);

	if ($row['ID_MEMBER'] != $user_info['id'] && $a_manage == false)
		fatal_error($txt['smftags_err_deletetag'],false);

	// Delete the tag for the topic
	$smcFunc['db_query']('', "DELETE FROM {db_prefix}tags_log WHERE ID = $id LIMIT 1");

	// Tag Cleanup
	TagCleanUp($row['ID_TAG']);

	// Redirect back to the topic
	redirectexit('topic=' . $row['ID_TOPIC']);
}

function TagsSettings2()
{
}

function TagCleanUp($ID_TAG)
{
	global $smcFunc;
	// Delete Tags that have no tag log entry
	//$dbresult = db_query("SELECT ID_TAG FROM {db_prefix}tags", __FILE__, __LINE__);
	//while($row = mysql_fetch_assoc($dbresult))
	//{
		//$dbresult2 = db_query("SELECT ID FROM {db_prefix}tags_log WHERE ID_TAG = " . $row['ID_TAG'], __FILE__, __LINE__);
		$dbresult2 = $smcFunc['db_query']('', "
		SELECT
			ID
		FROM {db_prefix}tags_log
		WHERE ID_TAG = " . $ID_TAG);

		if ($smcFunc['db_affected_rows']() == 0)
		{
			$smcFunc['db_query']('', "DELETE FROM {db_prefix}tags WHERE ID_TAG = " . $ID_TAG);
		}
		$smcFunc['db_free_result']($dbresult2);
	//}
	//mysql_free_result($dbresult);


	//Redirect to the admin section
	//redirectexit('action=tags;sa=admin');
}

function SuggestTag()
{
	global $context, $txt, $mbname;
	// Check permission
	isAllowedTo('smftags_suggest');

	$context['sub_template']  = 'suggest';
	$context['page_title'] = $mbname . ' - ' . $txt['smftags_suggest'];
}
function SuggestTag2()
{
	// Check permission
	isAllowedTo('smftags_suggest');
}

?>