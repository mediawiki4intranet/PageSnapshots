<?php

/**
 * PageSnapshots - extension allows to view 'snapshots' of old page revisions
 *   (with corresponding revisions of included templates and images)
 *
 * Copyright 2012+ Vitaliy Filippov <vitalif@mail.ru>
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @file
 * @ingroup Extensions
 * @author Vitaliy Filippov <vitalif@mail.ru>
 */

$wgExtensionMessagesFiles['PageSnapshots'] = dirname(__FILE__).'/PageSnapshots.i18n.php';
$wgHooks['PageHistoryLineEnding'][] = 'wfSnapshotHistoryItem';
$wgHooks['ArticleViewHeader'][] = 'wfSnapshotStart';
$wgHooks['BeforeParserFetchTemplateAndtitle'][] = 'wfSnapshotTemplateRev';
$wgHooks['BeforeParserFetchFileAndTitle'][] = 'wfSnapshotFileRev';

$egSnapshot = NULL;

function wfSnapshotHistoryItem($pager, &$row, &$s, &$classes)
{
    $p = strpos($s, ')');
    if ($p !== false)
    {
        $link = $pager->title->getLocalUrl(array('oldid' => $row->rev_id, 'snapshot' => 1));
        $msg = wfMsg('page-history-snapshot');
        $s = substr($s, 0, $p) . ' | <a href="'.htmlspecialchars($link).'">'.$msg.'</a>' . substr($s, $p);
    }
    return true;
}

function wfSnapshotStart($article, &$outputDone, &$useParserCache)
{
    global $wgRequest, $egSnapshot;
    if ($article->getOldId() && $wgRequest->getVal('snapshot'))
    {
        $egSnapshot = $wgRequest->getVal('snapshot');
        $egSnapshot = $egSnapshot && $egSnapshot != 1 ? wfTimestampOrNull(TS_MW, $egSnapshot) : NULL;
        $egSnapshot = $egSnapshot ?: $article->getTimestamp();
        $useParserCache = false;
    }
    return true;
}

function wfSnapshotTemplateRev($parser, $title, &$skip, &$id)
{
    global $egSnapshot;
    if ($egSnapshot)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $id = $dbr->selectField(
            array('page', 'revision'), 'rev_id', array(
                'page_id=rev_page',
                'page_namespace' => $title->getNamespace(),
                'page_title' => $title->getDBkey(),
                'rev_timestamp <= \''.wfTimestamp(TS_MW, $egSnapshot).'\'',
            ), __METHOD__, array('ORDER BY' => 'rev_timestamp DESC', 'LIMIT' => 1)
        );
    }
    return true;
}

function wfSnapshotFileRev($parser, $title, &$time, &$sha1, &$descQuery)
{
    global $egSnapshot;
    if ($egSnapshot)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $time = $dbr->selectField(
            'oldimage', 'oi_timestamp', array(
                'oi_name' => $title->getDBkey(),
                'oi_timestamp <= \''.wfTimestamp(TS_MW, $egSnapshot).'\'',
            ), __METHOD__, array('ORDER BY' => 'oi_timestamp DESC', 'LIMIT' => 1)
        );
    }
    return true;
}
