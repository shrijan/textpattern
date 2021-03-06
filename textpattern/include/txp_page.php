<?php
/*
	This is Textpattern

	Copyright 2005 by Dean Allen
	www.textpattern.com
	All rights reserved

	Use of this software indicates acceptance of the Textpattern license agreement

*/

/**
 * Pages panel.
 *
 * @package Admin\Page
 */

	if (!defined('txpinterface'))
	{
		die('txpinterface is undefined.');
	}

	if ($event == 'page')
	{
		require_privs('page');

		bouncer($step, array(
			'page_edit'       => false,
			'page_save'       => true,
			'page_delete'     => true,
			'save_pane_state' => true,
		));

		switch(strtolower($step))
		{
			case "":
				page_edit();
				break;
			case "page_edit":
				page_edit();
				break;
			case "page_save":
				page_save();
				break;
			case "page_delete":
				page_delete();
				break;
			case "page_new":
				page_new();
				break;
			case "save_pane_state":
				page_save_pane_state();
				break;
		}
	}

/**
 * The main editor panel.
 *
 * @param string|array $message The activity message
 */

	function page_edit($message = '')
	{
		global $event, $step;

		pagetop(gTxt('edit_pages'), $message);

		extract(array_map('assert_string', gpsa(array(
			'copy',
			'savenew',
		))));

		$name = sanitizeForPage(assert_string(gps('name')));
		$newname = sanitizeForPage(assert_string(gps('newname')));

		if ($step == 'page_delete' || empty($name) && $step != 'page_new' && !$savenew)
		{
			$name = safe_field('page', 'txp_section', "name = 'default'");
		}
		elseif(($copy || $savenew) && $newname)
		{
			$name = $newname;
		}

		// Format of each entry is popTagLink -> array ( gTxt() string, class/ID).
		$tagbuild_items = array(
			'page_article'     => array('page_article_hed', 'article-tags'),
			'page_article_nav' => array('page_article_nav_hed', 'article-nav-tags'),
			'page_nav'         => array('page_nav_hed', 'nav-tags'),
			'page_xml'         => array('page_xml_hed', 'xml-tags'),
			'page_misc'        => array('page_misc_hed', 'misc-tags'),
			'page_file'        => array('page_file_hed', 'file-tags'),
		);

		$tagbuild_links = '';
		foreach ($tagbuild_items as $tb => $item)
		{
			$tagbuild_links .= wrapRegion($item[1].'_group', taglinks($tb), $item[1], $item[0], 'page_'.$item[1]);
		}

		echo
			n.'<h1 class="txp-heading">'.gTxt('tab_pages').'</h1>'.
			n.'<div id="'.$event.'_container" class="txp-container">'.
			startTable('', '', 'txp-columntable').
			tr(
				tda(

					'<div id="tagbuild_links">'.hed(
						gTxt('tagbuilder')
					, 2).
						$tagbuild_links.
						n.'</div>'
				,' class="column"').

				tda(
					page_edit_form($name)
				, ' class="column"').

				tda(
					n.'<div id="content_switcher">'.
					hed(gTxt('all_pages'), 2).
					graf(sLink('page', 'page_new', gTxt('create_new_page')), ' class="action-create"').
					page_list($name).
					n.'</div>'
				, ' class="column"')
			).
			endTable().'</div>';
	}

/**
 * Renders an editor for page templates.
 *
 * @param  string $name The presented template
 * @return string HTML
 */

	function page_edit_form($name)
	{
		global $step;

		if ($name)
		{
			$html = safe_field('user_html', 'txp_page', "name='".doSlash($name)."'");
		}
		else
		{
			$html = gps('html');
		}

		if (empty($name))
		{
			$buttons = '<p class="edit-title">'.
			gTxt('name_for_this_page').
			fInput('text', 'newname', '', '', '', '', INPUT_REGULAR).
			hInput('savenew', 'savenew').
			'</p>';
		}
		else
		{
			$buttons = '<p class="edit-title">'.gTxt('you_are_editing_page').sp.strong(txpspecialchars($name)).'</p>';
		}

		$out[] = '<div id="main_content">'.$buttons.
					'<textarea id="html" class="code" name="html" cols="'.INPUT_LARGE.'" rows="'.INPUT_REGULAR.'">'.txpspecialchars($html).'</textarea>'.
					n.'<p>'.fInput('submit', 'save', gTxt('save'), 'publish').
					eInput('page').
					sInput('page_save').
					hInput('name', $name).'</p>';

		if (!empty($name))
		{
			$out[] =
				n.'<p class="copy-as"><label for="copy-page">'.gTxt('copy_page_as').'</label>'.
				fInput('text','newname','','input-medium','','',INPUT_MEDIUM,'','copy-page').
				fInput('submit','copy',gTxt('copy')).'</p>';
		}

		$out[] = '</div>';

		return form(join('',$out), '', '', 'post', '', '', 'page_form');
	}

/**
 * Renders a list of page templates as a &lt;table&gt;.
 *
 * @param  string $current The selected template
 * @return string HTML
 */

	function page_list($current)
	{
		$protected = safe_column('DISTINCT page', 'txp_section', '1=1') + array('error_default');

		$criteria = 1;
		$criteria .= callback_event('admin_criteria', 'page_list', 0, $criteria);

		$rs = safe_rows_start('name', 'txp_page', "$criteria order by name asc");

		while ($a = nextRow($rs))
		{
			extract($a);

			$link  = eLink('page', '', 'name', $name, $name);
			$dlink = !in_array($name, $protected) ? dLink('page', 'page_delete', 'name', $name) : '';
			$out[] = ($current == $name) ?
				tr(td($name).td($dlink)) :
				tr(td($link).td($dlink));
		}

		return startTable('', '', 'txp-list').join(n, $out).endTable();
	}

/**
 * Deletes a page template.
 */

	function page_delete()
	{
		$name  = ps('name');
		$count = safe_count('txp_section', "page = '".doSlash($name)."'");

		if ($name == 'error_default')
		{
			return page_edit();
		}

		if ($count)
		{
			$message = array(gTxt('page_used_by_section', array('{name}' => $name, '{count}' => $count)), E_WARNING);
		}

		else
		{
			safe_delete('txp_page', "name = '".doSlash($name)."'");
			callback_event('page_deleted', '', 0, $name);

			$message = gTxt('page_deleted', array('{name}' => $name));
		}

		page_edit($message);
	}

/**
 * Saves a page template.
 */

	function page_save()
	{
		extract(doSlash(array_map('assert_string', psa(array(
			'savenew',
			'html',
			'copy',
		)))));

		$name = sanitizeForPage(assert_string(ps('name')));

		if ($savenew or $copy)
		{
			$newname = doSlash(sanitizeForPage(assert_string(ps('newname'))));

			if ($newname and safe_field('name', 'txp_page', "name = '$newname'"))
			{
				$message = array(gTxt('page_already_exists', array('{name}' => $newname)), E_ERROR);
				if ($savenew)
				{
					$_POST['newname'] = '';
				}
			}
			elseif ($newname)
			{
				if (safe_insert('txp_page', "name = '$newname', user_html = '$html'"))
				{
					update_lastmod();
					$message = gTxt('page_created', array('{name}' => $newname));
				}
				else
				{
					$message = array(gTxt('page_save_failed'), E_ERROR);
				}
			}
			else
			{
				$message = array(gTxt('page_name_invalid'), E_ERROR);
			}

			page_edit($message);
		}

		else
		{
			if (safe_update('txp_page', "user_html = '$html'", "name = '$name'"))
			{
				update_lastmod();
				$message = gTxt('page_updated', array('{name}' => $name));
			}
			else
			{
				$message = array(gTxt('page_save_failed'), E_ERROR);
			}

			page_edit($message);
		}
	}

/**
 * Directs requests to page_edit() armed with a 'page_new' step.
 *
 * @see page_edit()
 */

	function page_new()
	{
		page_edit();
	}

/**
 * Renders a list of tag builder options.
 *
 * @param  string $type
 * @return HTML
 * @access private
 * @see    popTagLinks()
 */

	function taglinks($type)
	{
		return popTagLinks($type);
	}

/**
 * Saves the pane visibility state on the server.
 */

	function page_save_pane_state()
	{
		global $event;
		$panes = array('article-tags', 'article-nav-tags', 'nav-tags', 'xml-tags', 'misc-tags', 'file-tags');
		$pane = gps('pane');
		if (in_array($pane, $panes))
		{
			set_pref("pane_page_{$pane}_visible", (gps('visible') == 'true' ? '1' : '0'), $event, PREF_HIDDEN, 'yesnoradio', 0, PREF_PRIVATE);
			send_xml_response();
		}
		else
		{
			trigger_error('invalid_pane', E_USER_WARNING);
		}
	}
