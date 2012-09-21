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
$wgHooks['PageHistoryLineEnding'][] = 'PageSnapshotsExtension::historyItem';
$wgHooks['BeforeInitialize'][] = 'PageSnapshotsExtension::init';
$wgHooks['ArticleViewHeader'][] = 'PageSnapshotsExtension::start';
$wgHooks['BeforeParserFetchTemplateAndtitle'][] = 'PageSnapshotsExtension::templateRev';
$wgHooks['BeforeParserFetchFileAndTitle'][] = 'PageSnapshotsExtension::fileRev';
$wgHooks['LinkBegin'][] = 'PageSnapshotsExtension::linkRev';

$egSnapshot = NULL;

class PageSnapshotsExtension
{
    /**
     * Get page revision id, actual for $time
     */
    static function getRevByTime($title, $time)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $oldid = $dbr->selectField(
            array('page', 'revision'), 'rev_id', array(
                'page_id=rev_page',
                'page_namespace' => $title->getNamespace(),
                'page_title' => $title->getDBkey(),
                'rev_timestamp <= \''.wfTimestamp(TS_MW, $time).'\'',
            ), __METHOD__, array('ORDER BY' => 'rev_timestamp DESC', 'LIMIT' => 1)
        );
        return $oldid;
    }

    /**
     * Append snapshot links to history items
     */
    static function historyItem($pager, &$row, &$s, &$classes)
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

    /**
     * Set oldid by passed 'snapshot=DATE' query parameter
     */
    static function init(&$title, $unused, &$output, &$user, $request, $wiki)
    {
        global $wgRequest, $egSnapshot;
        $egSnapshot = $wgRequest->getVal('snapshot');
        if ($egSnapshot && $egSnapshot != '1')
        {
            $egSnapshot = wfTimestampOrNull(TS_MW, $egSnapshot);
        }
        if ($title &&
            $wgRequest->getVal('action', 'view') == 'view' &&
            ($egSnapshot && $egSnapshot != '1') &&
            !$wgRequest->getVal('oldid'))
        {
            $wgRequest->setVal('oldid', self::getRevByTime($title, $egSnapshot));
        }
        return true;
    }

    /**
     * Disable page cache, and optionally set snapshot date from passed oldid
     * if query parameter 'snapshot' is equal to '1' (&snapshot=1)
     */
    static function start($article, &$outputDone, &$useParserCache)
    {
        global $wgRequest, $egSnapshot;
        if ($article->getOldId() && $egSnapshot)
        {
            if ($egSnapshot == '1')
            {
                $egSnapshot = $article->getTimestamp();
            }
            $useParserCache = false;
        }
        return true;
    }

    /**
     * Override revision of included template
     */
    static function templateRev($parser, $title, &$skip, &$id)
    {
        global $egSnapshot;
        if ($egSnapshot)
        {
            $id = self::getRevByTime($title, $egSnapshot);
        }
        return true;
    }

    /**
     * Override revision of included file
     */
    static function fileRev($parser, $title, &$time, &$sha1, &$descQuery)
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

    /**
     * Include snapshot time into all page links
     */
    static function linkRev($dummy, $target, &$html, &$customAttribs, &$query, &$options, &$ret)
    {
        global $egSnapshot;
        if ($egSnapshot)
        {
            $query['snapshot'] = $egSnapshot;
        }
        return true;
    }
}
