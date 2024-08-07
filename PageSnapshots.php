<?php

/**
 * PageSnapshots - extension allows to view 'snapshots' of old page revisions
 *   (with corresponding revisions of included templates and images).
 * NOTES:
 * 1) DOES NOT deal with deletions or renames, so if you want to see
 *    old versions of your pages in future - DO NOT delete anything that was used.
 * 2) It's relatively slow to view snapshots.
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
$wgHooks['ArticleViewFooter'][] = 'PageSnapshotsExtension::end';
$wgHooks['BeforeParserFetchTemplateAndtitle'][] = 'PageSnapshotsExtension::templateRev';
$wgHooks['BeforeParserFetchFileAndTitle'][] = 'PageSnapshotsExtension::fileRev' . (version_compare($wgVersion, '1.19', '>=') ? '1_19' : '');
$wgHooks['LinkBegin'][] = 'PageSnapshotsExtension::linkRev';

class PageSnapshotsExtension
{
    static $activeSnapshot;

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
                'rev_timestamp <= '.$dbr->timestamp($time),
            ), __METHOD__, array('ORDER BY' => 'rev_timestamp DESC', 'LIMIT' => 1)
        );
        return $oldid;
    }

    /**
     * Append snapshot links to history items
     */
    static function historyItem($pager, &$row, &$s, &$classes)
    {
        if (preg_match('/^(<[^>]*>|[^<\)]+)*?(\))/s', $s, $m, PREG_OFFSET_CAPTURE))
        {
            $link = $pager->getTitle()->getLocalUrl(array('oldid' => $row->rev_id, 'snapshot' => 1));
            $msg = wfMsg('page-history-snapshot');
            $s = substr($s, 0, $m[2][1]) . ' | <a rel="noindex,nofollow" href="'.htmlspecialchars($link).'">'.$msg.'</a>' . substr($s, $m[2][1]);
        }
        return true;
    }

    /**
     * Set oldid by passed 'snapshot=DATE' query parameter
     */
    static function init(&$title, $unused, &$output, &$user, $request, $wiki)
    {
        global $wgRequest;
        self::$activeSnapshot = $wgRequest->getVal('snapshot');
        if (self::$activeSnapshot && self::$activeSnapshot != '1')
        {
            self::$activeSnapshot = wfTimestampOrNull(TS_MW, self::$activeSnapshot);
        }
        if ((self::$activeSnapshot && self::$activeSnapshot != '1') &&
            $title &&
            $wgRequest->getVal('action', 'view') == 'view' &&
            !$wgRequest->getVal('oldid'))
        {
            $wgRequest->setVal('oldid', self::getRevByTime($title, self::$activeSnapshot));
        }
        return true;
    }

    /**
     * Disable page cache, and optionally set snapshot date from passed oldid
     * if query parameter 'snapshot' is equal to '1' (&snapshot=1)
     */
    static function start($article, &$outputDone, &$useParserCache)
    {
        global $wgRequest;
        if (self::$activeSnapshot && $article->getOldId())
        {
            if (self::$activeSnapshot == '1')
            {
                self::$activeSnapshot = Revision::newFromId($article->getOldId())->getTimestamp();
            }
            $useParserCache = false;
        }
        return true;
    }

    /**
     * Print a snapshot warning near page revision information
     */
    static function end($article)
    {
        global $wgOut;
        if (self::$activeSnapshot && $article->getOldId())
        {
            $wgOut->setSubtitle($wgOut->getSubtitle().'<div id="mw-snapshots-info">'.wfMsg('page-snapshot-warning').'</div>');
        }
        return true;
    }

    /**
     * Override revision of included template
     */
    static function templateRev($parser, $title, &$skip, &$id)
    {
        if (self::$activeSnapshot)
        {
            $id = self::getRevByTime($title, self::$activeSnapshot);
        }
        return true;
    }

    /**
     * Override revision of included file (for MW 1.19+)
     */
    static function fileRev1_19($parser, $title, &$options, &$descQuery)
    {
        if (self::$activeSnapshot)
        {
            $file = wfFindFile($title);
            if ($file && $file->getTimestamp() > self::$activeSnapshot)
            {
                $old = $file->getHistory(1, NULL, self::$activeSnapshot);
                if ($old)
                {
                    $options['time'] = $old[0]->getTimestamp();
                }
            }
        }
        return true;
    }

    /**
     * Override revision of included file
     */
    static function fileRev($parser, $title, &$time, &$sha1, &$descQuery)
    {
        $opt = array('time' => &$time, 'sha1' => &$sha1);
        return self::fileRev1_19($parser, $title, $opt, $descQuery);
    }

    /**
     * Include snapshot time into all page links
     */
    static function linkRev($dummy, $target, &$html, &$customAttribs, &$query, &$options, &$ret)
    {
        if (self::$activeSnapshot)
        {
            $query['snapshot'] = isset($query['oldid']) ? '1' : self::$activeSnapshot;
        }
        return true;
    }
}
