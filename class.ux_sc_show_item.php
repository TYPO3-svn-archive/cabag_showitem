<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Dimitri König <dk@cabag.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Plugin for the 'cabag_showitem' extension.
 *
 * @author	Dimitri König <dk@cabag.ch>
 * @package	TYPO3
 * @subpackage	tx_cabagshowitem
 */

class ux_SC_show_item extends SC_show_item {
	var $prefixId      = 'ux_SC_show_item';		// Same as class name
	var $scriptRelPath = 'class.ux_sc_show_item.php';	// Path to this script relative to the extension dir.
	var $extKey        = 'cabag_showitem';	// The extension key.

	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TCA;

			// Setting input variables.
		$this->table = t3lib_div::_GET('table');
		$this->uid = t3lib_div::_GET('uid');

			// Initialize:
		$this->perms_clause = $BE_USER->getPagePermsClause(1);
		$this->access = 0;	// Set to true if there is access to the record / file.
		$this->type = '';	// Sets the type, "db" or "file". If blank, nothing can be shown.

			// Checking if the $table value is really a table and if the user has access to it.
		if (isset($TCA[$this->table]))	{
			t3lib_div::loadTCA($this->table);
			$this->type = 'db';
			$this->uid = intval($this->uid);

				// Check permissions and uid value:
			if ($this->uid && $BE_USER->check('tables_select',$this->table))	{
				if ((string)$this->table=='pages')	{
					$this->pageinfo = t3lib_BEfunc::readPageAccess($this->uid,$this->perms_clause);
					$this->access = is_array($this->pageinfo) ? 1 : 0;
					$this->row = $this->pageinfo;
				} else {
					$this->row = t3lib_BEfunc::getRecord($this->table,$this->uid);
					if ($this->row)	{
						$this->pageinfo = t3lib_BEfunc::readPageAccess($this->row['pid'],$this->perms_clause);
						$this->access = is_array($this->pageinfo) ? 1 : 0;
					}
				}

				$treatData = t3lib_div::makeInstance('t3lib_transferData');
				$treatData->renderRecord($this->table, $this->uid, 0, $this->row);
				$cRow = $treatData->theRecord;
			}
		} else	{
			// if the filereference $this->file is relative, we correct the path
			if (substr($this->table,0,3)=='../')	{
				$this->file = PATH_site.ereg_replace('^\.\./','',$this->table);
			} else {
				$this->file = $this->table;
			}
			if (@is_file($this->file) && t3lib_div::isAllowedAbsPath($this->file))	{
				$this->type = 'file';
				$this->access = 1;
			}
		}

			// Initialize document template object:
		$this->doc = t3lib_div::makeInstance('mediumDoc');
		$this->doc->backPath = $BACK_PATH;
		$this->doc->docType = 'xhtml_trans';

		// Starting the page by creating page header stuff:
		$this->doc->getContextMenuCode();
		$this->content.=$this->doc->startPage($LANG->sL('LLL:EXT:lang/locallang_core.php:show_item.php.viewItem'));
		$this->content.=$this->doc->header($LANG->sL('LLL:EXT:lang/locallang_core.php:show_item.php.viewItem'));
		$this->content.=$this->doc->spacer(5);
	}

	function getTitelWithIcons($table, $uid) {
		$pageActionIcon = '<a href="#" onclick="'.htmlspecialchars(t3lib_BEfunc::editOnClick('&edit['.$table.']['.$uid.']=edit',$GLOBALS['BACK_PATH'])).'">'.'<img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'],'gfx/edit2.gif','width="11" height="12"').' title="'.$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_mod_web_list.php:edit',1).'" border="0" alt="" /></a>';
		$pageActionIcon .= '<a href="#" onclick="window.location.href=\'/typo3/show_rechis.php?element='.$table.'%3A'.$uid.'&returnUrl='.$GLOBALS['BACK_PATH'].'\'; return false;"><img src="/typo3/sysext/t3skin/icons/gfx/history2.gif" /></a>';
		if($table == "pages") {
			$pageActionIcon .= '<a href="#" onclick="window.opener.location.href=\'/typo3conf/ext/templavoila/mod1/index.php?&id='.$uid.'&returnUrl='.$GLOBALS['BACK_PATH'].'\'; return false;"><img src="/typo3/sysext/t3skin/icons/ext/templavoila/mod1/moduleicon.gif" /></a>';
			$pageActionIcon .= $this->doc->viewPageIcon($uid, "");
		}

		$rowTitle = '<a href="#" onclick="'.htmlspecialchars(t3lib_BEfunc::editOnClick('&edit['.$table.']['.$uid.']=edit',$GLOBALS['BACK_PATH'])).'">' .
					t3lib_BEfunc::getRecordTitle($table, t3lib_BEfunc::getRecordWSOL($table, $uid), 1, 0) .
					'</a> ';

		$content = $rowTitle . $pageActionIcon;
		
		return $content;
	}

	function makeRef($table,$ref)	{

		if ($table==='_FILE')	{
				// First, fit path to match what is stored in the refindex:
			$fullIdent = $ref;

			if (t3lib_div::isFirstPartOfStr($fullIdent,PATH_site))	{
				$fullIdent = substr($fullIdent,strlen(PATH_site));
			}

				// Look up the path:
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'*',
				'sys_refindex',
				'ref_table='.$GLOBALS['TYPO3_DB']->fullQuoteStr('_FILE','sys_refindex').
					' AND ref_string='.$GLOBALS['TYPO3_DB']->fullQuoteStr($fullIdent,'sys_refindex').
					' AND deleted=0'
			);
		} else {
				// Look up the path:
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
				'*',
				'sys_refindex',
				'ref_table='.$GLOBALS['TYPO3_DB']->fullQuoteStr($table,'sys_refindex').
					' AND ref_uid='.intval($ref).
					' AND deleted=0'
			);
		}

			// Compile information for title tag:
		$infoData = array();
		if (count($rows))	{
			$infoData[] = '<tr class="bgColor5 tableheader">' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:title', 1).'</td>' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:table', 1).'</td>' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:uid', 1).'</td>' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:field', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:flexpointer', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:softrefkey', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:sorting', 1).'</td>'.
					'</tr>';
		}

		foreach($rows as $row)	{
			$infoData[] = '<tr class="bgColor4"">' .
				'<td style="white-space:nowrap;">'.$this->getTitelWithIcons($row['tablename'], $row['recuid']).'</td>' .
				'<td>'.$row['tablename'].'</td>' .
				'<td>'.$row['recuid'].'</td>' .
				'<td>'.$row['field'].'</td>'.
				'<td>'.$row['flexpointer'].'</td>'.
				'<td>'.$row['softref_key'].'</td>'.
				'<td>'.$row['sorting'].'</td>'.
				'</tr>';
		}

		return count($infoData) ? '<table border="0" cellpadding="1" cellspacing="1">'.implode('',$infoData).'</table>' : '';
	}

	function makeRefFrom($table,$ref)	{
		// Look up the path:
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'*',
			'sys_refindex',
			'tablename='.$GLOBALS['TYPO3_DB']->fullQuoteStr($table,'sys_refindex').
				' AND recuid='.intval($ref)
		);

			// Compile information for title tag:
		$infoData = array();
		if (count($rows))	{
			$infoData[] = '<tr class="bgColor5 tableheader">' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:reftitle', 1).'</td>' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:reftable', 1).'</td>' .		
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:field', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:flexpointer', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:softrefkey', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:sorting', 1).'</td>'.
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:refuid', 1).'</td>' .
					'<td>'.$GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:refstring', 1).'</td>' .
					'</tr>';
		}
		foreach($rows as $row)	{
			$infoData[] = '<tr class="bgColor4"">' .
					'<td style="white-space:nowrap;">'.$this->getTitelWithIcons($row['ref_table'], $row['ref_uid']).'</td>' .
					'<td>'.$row['field'].'</td>'.
					'<td>'.$row['flexpointer'].'</td>'.
					'<td>'.$row['softref_key'].'</td>'.
					'<td>'.$row['sorting'].'</td>'.
					'<td>'.$row['ref_table'].'</td>' .
					'<td>'.$row['ref_uid'].'</td>' .
					'<td>'.$row['ref_string'].'</td>' .
					'</tr>';
		}

		return count($infoData) ? '<table border="0" cellpadding="1" cellspacing="1">'.implode('',$infoData).'</table>' : '';
	}


	function renderDBInfo()	{
		global $LANG,$TCA;

			// Print header, path etc:
		$code = $this->doc->getHeader($this->table,$this->row,$this->pageinfo['_thePath'],1).'<br />';
		$this->content.= $this->doc->section('',$code);

			// Initialize variables:
		$tableRows = Array();
		$i = 0;

		$compulsoryFields = array(
			"crdate" => $LANG->sL('LLL:EXT:cabag_showitem/locallang.php:crdate', 1),
			"cruser_id" => $LANG->sL('LLL:EXT:cabag_showitem/locallang.php:cruser_id', 1),
			"tstamp" => $LANG->sL('LLL:EXT:cabag_showitem/locallang.php:tstamp', 1)
		);

		foreach($compulsoryFields as $name => $value)	{
			$rowValue = htmlspecialchars(t3lib_BEfunc::getProcessedValueExtra($this->table,$name,$this->row[$name]));

			if($name == "cruser_id") {
				$userTemp = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
					'username, realName',
					'be_users',
					'uid = '.$rowValue
				);
				if($userTemp[0]['username']) {
					$rowValue = $userTemp[0]['username'];
					if($userTemp[0]['realName']) {
						$rowValue .= ' - '.$userTemp[0]['realName'];
					}
				}
			}

			$i++;
			$tableRows[] = '
				<tr>
					<td class="bgColor5">'.$value.'</td>
					<td class="bgColor4">'.$rowValue.'</td>
				</tr>';
		}

		// Traverse the list of fields to display for the record:
		$fieldList = t3lib_div::trimExplode(',',$TCA[$this->table]['interface']['showRecordFieldList'],1);
		
		foreach($fieldList as $name)	{
			$name = trim($name);
			if ($TCA[$this->table]['columns'][$name])	{
				if (!$TCA[$this->table]['columns'][$name]['exclude'] || $GLOBALS['BE_USER']->check('non_exclude_fields',$this->table.':'.$name))	{
					$i++;
					$tableRows[] = '
						<tr>
							<td class="bgColor5">'.$LANG->sL(t3lib_BEfunc::getItemLabel($this->table,$name),1).'</td>
							<td class="bgColor4">'.htmlspecialchars(t3lib_BEfunc::getProcessedValue($this->table,$name,$this->row[$name])).'</td>
						</tr>';
				}
			}
		}

			// Create table from the information:
		$tableCode = '
					<table border="0" cellpadding="1" cellspacing="1" id="typo3-showitem">
						'.implode('',$tableRows).'
					</table>';
		$this->content.=$this->doc->section('',$tableCode);
		$this->content.=$this->doc->divider(2);

			// Add path and table information in the bottom:
		$code = '';
		$code.= $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],-48).'<br />';
		$code.= $LANG->sL('LLL:EXT:lang/locallang_core.php:labels.table').': '.$LANG->sL($TCA[$this->table]['ctrl']['title']).' ('.$this->table.') - UID: '.$this->uid.'<br />';
		$this->content.= $this->doc->section('', $code);

			// References:
		$this->content.= $this->doc->section($GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:reftothisitem', 1),$this->makeRef($this->table,$this->row['uid']));

			// References:
		$this->content.= $this->doc->section($GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:reffromthisitem', 1),$this->makeRefFrom($this->table,$this->row['uid']));
	}	

	function renderFileInfo($returnLinkTag)	{
		global $LANG;

			// Initialize object to work on the image:
		require_once(PATH_t3lib.'class.t3lib_stdgraphic.php');
		$imgObj = t3lib_div::makeInstance('t3lib_stdGraphic');
		$imgObj->init();
		$imgObj->mayScaleUp = 0;
		$imgObj->absPrefix = PATH_site;

			// Read Image Dimensions (returns false if file was not an image type, otherwise dimensions in an array)
		$imgInfo = '';
		$imgInfo = $imgObj->getImageDimensions($this->file);

			// File information
		$fI = t3lib_div::split_fileref($this->file);
		$ext = $fI['fileext'];

		$code = '';

			// Setting header:
		$icon = t3lib_BEfunc::getFileIcon($ext);
		$url = 'gfx/fileicons/'.$icon;
		$fileName = '<img src="'.$url.'" width="18" height="16" align="top" alt="" /><b>'.$LANG->sL('LLL:EXT:lang/locallang_core.php:show_item.php.file',1).':</b> '.$fI['file'];
		if (t3lib_div::isFirstPartOfStr($this->file,PATH_site))	{
			$code.= '<a href="../'.substr($this->file,strlen(PATH_site)).'" target="_blank">'.$fileName.'</a>';
		} else {
			$code.= $fileName;
		}
		$code.=' &nbsp;&nbsp;<b>'.$LANG->sL('LLL:EXT:lang/locallang_core.php:show_item.php.filesize').':</b> '.t3lib_div::formatSize(@filesize($this->file)).'<br />
			';
		if (is_array($imgInfo))	{
			$code.= '<b>'.$LANG->sL('LLL:EXT:lang/locallang_core.php:show_item.php.dimensions').':</b> '.$imgInfo[0].'x'.$imgInfo[1].' pixels';
		}
		$this->content.=$this->doc->section('',$code);
		$this->content.=$this->doc->divider(2);

			// If the file was an image...:
		if (is_array($imgInfo))	{

			$imgInfo = $imgObj->imageMagickConvert($this->file,'web','346','200m','','','',1);
			$imgInfo[3] = '../'.substr($imgInfo[3],strlen(PATH_site));
			$code = '<br />
				<div align="center">'.$returnLinkTag.$imgObj->imgTag($imgInfo).'</a></div>';
			$this->content.= $this->doc->section('', $code);
		} else {
			$this->content.= $this->doc->spacer(10);
			$lowerFilename = strtolower($this->file);

				// Archive files:
			if (TYPO3_OS!='WIN' && !$GLOBALS['TYPO3_CONF_VARS']['BE']['disable_exec_function'])	{
				if ($ext=='zip')	{
					$code = '';
					$t = array();
					exec('unzip -l '.$this->file, $t);
					if (is_array($t))	{
						reset($t);
						next($t);
						next($t);
						next($t);
						while(list(,$val)=each($t))	{
							$parts = explode(' ',trim($val),7);
							$code.= '
								'.$parts[6].'<br />';
						}
						$code = '
							<span class="nobr">'.$code.'
							</span>
							<br /><br />';
					}
					$this->content.= $this->doc->section('', $code);
				} elseif($ext=='tar' || $ext=='tgz' || substr($lowerFilename,-6)=='tar.gz' || substr($lowerFilename,-5)=='tar.z')	{
					$code = '';
					if ($ext=='tar')	{
						$compr = '';
					} else {
						$compr = 'z';
					}
					$t = array();
					exec('tar t'.$compr.'f '.$this->file, $t);
					if (is_array($t))	{
						foreach($t as $val)	{
							$code.='
								'.$val.'<br />';
						}

						$code.='
								 -------<br/>
								 '.count($t).' files';

						$code = '
							<span class="nobr">'.$code.'
							</span>
							<br /><br />';
					}
					$this->content.= $this->doc->section('',$code);
				}
			} elseif ($GLOBALS['TYPO3_CONF_VARS']['BE']['disable_exec_function']) {
				$this->content.= $this->doc->section('','Sorry, TYPO3_CONF_VARS[BE][disable_exec_function] was set, so cannot display content of archive file.');
			}

				// Font files:
			if ($ext=='ttf')	{
				$thumbScript = 'thumbs.php';
				$check = basename($this->file).':'.filemtime($this->file).':'.$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'];
				$params = '&file='.rawurlencode($this->file);
				$params.= '&md5sum='.t3lib_div::shortMD5($check);
				$url = $thumbScript.'?&dummy='.$GLOBALS['EXEC_TIME'].$params;
				$thumb = '<br />
					<div align="center">'.$returnLinkTag.'<img src="'.htmlspecialchars($url).'" border="0" title="'.htmlspecialchars(trim($this->file)).'" alt="" /></a></div>';
				$this->content.= $this->doc->section('',$thumb);
			}
		}


			// References:
		$this->content.= $this->doc->section($GLOBALS['LANG']->sL('LLL:EXT:cabag_showitem/locallang.php:reftothisitem', 1),$this->makeRef('_FILE',$this->file));
	}

}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_showitem/class.tx_cabagshowitem.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cabag_showitem/class.tx_cabagshowitem.php']);
}

?>