<?php

/** ============================================================================
  * GoogleSitemap for Nucleus
  *
  * Copyright 2005 by Niels Leenheer
  * ============================================================================
  * This program is free software and open source software; you can redistribute
  * it and/or modify it under the terms of the GNU General Public License as
  * published by the Free Software Foundation; either version 2 of the License,
  * or (at your option) any later version.
  *
  * This program is distributed in the hope that it will be useful, but WITHOUT
  * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
  * FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
  * more details.
  *
  * You should have received a copy of the GNU General Public License along
  * with this program; if not, write to the Free Software Foundation, Inc.,
  * 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA  or visit
  * http://www.gnu.org/licenses/gpl.html
  * ============================================================================
  **/

/**
  * History
  *  0.7    modified release by shizuki
  *             Generate URL modified from
  *               'http://example.com/action.php?action=plugin&name=Sitemap' to
  *               'http://example.com/sitemap.xml' and,or
  *               'http://example.com/index.php?virtualpath=sitemap.xml'
  *             Add 'lastmod' attribute
  *  0.9    SitemapProtocol updated release
  *             SitemapProtocol ver.0.9 as common for Google, Yahoo! and MSN(Live! Search)
  *  1.0    Add Sitemap type and chage 'lastmod' generate
  *             Add 'ROR Sitemap' format
  *               For details about the ROR format, go to www.rorweb.com
  *             Modify 'lastmod' attribute
  *               item posted time or comment posted time or item update time
  *               item update time generate by NP_UpdateTime
  *  1.1    Send Sitemaps to Yahoo!
  *  1.2    Send Sitemaps to Live! Search
  *         Cahge sitemap notification URI to yahoo.com No appid required
  *         Add priority setting options
  **/

class NP_SEOSitemaps extends NucleusPlugin
{

    function getName()           {return 'SearchenginesSitemapsGenerator';}
    function getAuthor()         {return 'Niels Leenheer + shizuki';}
    function getURL()            {return 'http://japan.nucleuscms.org/wiki/plugins:seositemaps';}
    function getVersion()        {return '1.2.1';}
    function getDescription()    {return _G_SITEMAP_DESC;}
    function supportsFeature($k) {return $k==='SqlTablePrefix'?1:0;}
    function getEventList()      {return array('PostAddItem','PreSendContentType');}
    
    function event_PreSendContentType($data)
    {
        global $CONF, $manager, $blogid;

        $mcategories = $this->pluginCheck('MultipleCategories');
        if ($mcategories) {
            if (method_exists($mcategories, 'getRequestName')) {
                $subReq = $mcategories->getRequestName();
            } else {
                $subReq = 'subcatid';
            }
        }
        $npUpdateTime = $this->pluginCheck('UpdateTime');

        if (!$blogid) {
            $blogid = $CONF['DefaultBlog'];
        } else {
            if (is_numeric($blogid)) {
                $blogid = intval($blogid);
            } else {
                $blogid = intval(getBlogIDFromName($blogid));
            }
        }

        $b       =& $manager->getBlog($blogid);
        $BlogURL = $b->getURL();
        if (!$BlogURL) {
            $BlogURL = $CONF['IndexURL'];
        }

        if ( substr($BlogURL, -1) != '/'
          && substr($BlogURL, -4) != '.php') {
            $BlogURL .= '/';
        }

        if (getVar('virtualpath')) {
            $info = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', getVar('virtualpath'));
        } elseif (serverVar('PATH_INFO')) {
            $info = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', serverVar('PATH_INFO'));
        } else {
            return;
        }

        $path_arr  = explode('/', $info);
        $PcMap     = $this->getBlogOption($blogid, 'PcSitemap');
        $MobileMap = $this->getBlogOption($blogid, 'MobileSitemap');
        if ( end($path_arr) == $PcMap
          || end($path_arr) == 'ror.xml'
          || (!empty($MobileMap) && end($path_arr) == $MobileMap) ) {
            $sitemap = array();
            if ( $this->getOption('AllBlogMap') == 'yes'
              && $blogid == $CONF['DefaultBlog']) {
                $blogQuery  = 'SELECT * '
                            . 'FROM %s '
                            . 'ORDER BY bnumber';
                $blogQuery  = sprintf($blogQuery, sql_table('blog'));
                $blogResult = sql_query($blogQuery);
            } else {
                $blogQuery   = 'SELECT * '
                             . 'FROM %s '
                             . 'WHERE bnumber = %d';
                $blogQuery   = sprintf($blogQuery, sql_table('blog'), $blogid);
                $blogResult  = sql_query($blogQuery);
                $currentBlog = TRUE;
            }
            while ($blogs = sql_fetch_array($blogResult)) {
                $blog_id = intval($blogs['bnumber']);
                if (  $this->getBlogOption($blog_id, 'IncludeSitemap') == 'yes'
                   || !empty($currentBlog)) {
                    $temp_b  =& $manager->getBlog($blog_id);
                    $TempURL =  $temp_b->getURL();
                    $SelfURL =  $TempURL;

                    $URLMode = $CONF['URLMode'];
                    if (substr($TempURL, -4) == '.php') {
                        $CONF['URLMode'] = 'normal';
                    }

                    $usePathInfo = ($CONF['URLMode'] == 'pathinfo');

                    if (substr($SelfURL, -1) == '/') {

                        if ($usePathInfo) {
                            $SelfURL = substr($SelfURL, 0, -1);
                        } else {
                            $SelfURL = $SelfURL . 'index.php';
                        }

                    } elseif (substr($SelfURL, -4) != '.php') {

                        if ($usePathInfo) {
                            $SelfURL = $SelfURL;
                        } else {
                            $SelfURL = $SelfURL . '/index.php';
                        }

                    }

                    $CONF['ItemURL']     = $SelfURL;
                    $CONF['CategoryURL'] = $SelfURL;

                    if ( substr($TempURL, -1) != '/'
                      && substr($TempURL, -4) != '.php') {
                        $TempURL .= '/';
                    }

                    $patternURL = '/^' . preg_replace('/\//', '\/', $BlogURL) . '/';

                    if (preg_match($patternURL, $TempURL)) {

                        if (end($path_arr) == 'ror.xml') {
                            $rorTitleURL  = $this->_prepareLink($SelfURL, $TempURL);
                            $rooTitleURL  = hsc($rooTitleURL);
                            $sitemapTitle = "     <title>ROR Sitemap for " . $rorTitleURL . "</title>\n"
                                          . "     <link>" . $rorTitleURL . "</link>\n"
                                          . "     <item>\n"
                                          . "     <title>ROR Sitemap for " . $rorTitleURL . "</title>\n"
                                          . "     <link>" . $rorTitleURL . "</link>\n"
                                          . "     <ror:about>sitemap</ror:about>\n"
                                          . "     <ror:type>SiteMap</ror:type>\n"
                                          . "     </item>\n";
                        } else {
                            $bPriority = intval($this->getBlogOption($blog_id, 'blogPriority'));
                            if ($bPriority > 10) $bPriority = 10;
                            $bPriority = $bPriority / 10;
                            $sitemap[] = array(
                                'loc'        => $this->_prepareLink($SelfURL, $TempURL),
                                'priority'   => number_format($bPriority ,1),
                                'changefreq' => 'daily'
                            );
                        }

                        $catQuery  = 'SELECT * '
                                   . 'FROM %s '
                                   . 'WHERE cblog = %d '
                                   . 'ORDER BY catid';
                        $catTable  = sql_table('category');
                        $catQuery  = sprintf($catQuery, $catTable, $blog_id);
                        $catResult = sql_query($catQuery);

                        while ($cat = sql_fetch_array($catResult)) {

                            $cat_id = intval($cat['catid']);
                            $Link   = createCategoryLink($cat_id);
                            $catLoc =$this->_prepareLink($SelfURL, $Link);

                            if (end($path_arr) != 'ror.xml') {
                                $cPriority = intval($this->getCategoryOption($cat_id, 'catPriority'));
                                if ($cPriority > 10) $priority = 10;
                                $sPriority = ($cPriority - 1) / 10;
                                $cPriority  = $cPriority / 10;
                                $sitemap[] = array(
                                    'loc'        => $catLoc,
                                    'priority'   => number_format($cPriority, 1),
                                    'changefreq' => 'daily'
                                );
                            }

                            if ($mcategories) {
                                $scatQuery  = 'SELECT * '
                                            . 'FROM %s '
                                            . 'WHERE catid = %d '
//                                            . 'ORDER BY scatid';
                                            . 'ORDER BY ordid';
                                $scatTable  = sql_table('plug_multiple_categories_sub');
                                $scatQuery  = sprintf($scatQuery, $scatTable, $cat_id);
                                $scatResult = sql_query($scatQuery);

                                while ($scat = sql_fetch_array($scatResult)) {

                                    $scat_id = intval($scat['scatid']);
                                    $params  = array($subReq => $scat_id);
                                    $Link    = createCategoryLink($cat_id, $params);
                                    $scatLoc = $this->_prepareLink($SelfURL, $Link);

                                    if (end($path_arr) != 'ror.xml') {
                                        $sitemap[] = array(
                                            'loc'        => $scatLoc,
                                            'priority'   => number_format($sPriority, 1),
                                            'changefreq' => 'daily'
                                        );
                                    }
                                }
                            }
                        }

                        $itemQuery  = 'SELECT *, '
                                    . '       UNIX_TIMESTAMP(itime) AS timestamp '
                                    . 'FROM %s '
                                    . 'WHERE iblog  = %d '
                                    . 'AND   idraft = 0 '
                                    . 'ORDER BY itime DESC';
                        $itemTable  = sql_table('item');
                        $itemQuery  = sprintf($itemQuery, $itemTable, $blog_id);
                        $itemResult = sql_query($itemQuery);
                        while ($item = sql_fetch_array($itemResult)) {

                            $item_id  = intval($item['inumber']);
                            $Link     = createItemLink($item_id);
                            $tz       = date('O', $item['timestamp']);
                            $tz       = substr($tz, 0, 3) . ':' . substr($tz, 3, 2);
                            $itemLoc  = $this->_prepareLink($SelfURL, $Link);

                            $mdQuery  = 'SELECT'
                                      . '   UNIX_TIMESTAMP(ctime) AS timestamp'
                                      . ' FROM '
                                      .     sql_table('comment')
                                      . ' WHERE'
                                      . '   citem = ' . $item_id
                                      . ' ORDER BY'
                                      . '   ctime DESC'
                                      . ' LIMIT'
                                      . '   1';
                            $modTime  = sql_query($mdQuery);
                            $itemTime = $item['timestamp'];
                            if (sql_num_rows($modTime) > 0) {
                                $lastMod  = sql_fetch_object($modTime);
                                $itemTime = $lastMod->timestamp;
                            } elseif ($npUpdateTime) { // NP_UpdateTime exists
                                $mdQuery = 'SELECT'
                                         . '   UNIX_TIMESTAMP(updatetime) AS timestamp'
                                         . ' FROM '
                                         .     sql_table('plugin_rectime')
                                         . ' WHERE'
                                         . '   up_id = ' . $item_id;
                                $modTime = sql_query($mdQuery);
                                if (sql_num_rows($modTime) > 0) { 
                                    $lastMod  = sql_fetch_object($modTime);
                                    $itemTime = $lastMod->timestamp;
                                }
                            }

/*                            if (time() - $itemTime < 86400 * 2) {
                                $fq = 'hourly';
                            } elseif (time() - $itemTime < 86400 * 14) {
                                $fq = 'daily'; 
                            } elseif (time() - $itemTime < 86400 * 62) {
                                $fq = 'weekly';
                            } else {
                                $fq = 'monthly';
                            }*/
                            if ($itemTime < strtotime('-1 month')) {
                                $fq = 'monthly';
                            } elseif ($itemTime < strtotime('-1 week')) {
                                $fq = 'weekly';
                            } elseif ($itemTime < strtotime('-1 day')) {
                                $fq = 'daily'; 
                            } else {
                                $fq = 'hourly';
                            }
                            $lastmod = gmdate('Y-m-d\TH:i:s', $itemTime) . $tz;

                            if (end($path_arr) != 'ror.xml') {
                                $iPriority = intval($this->getItemOption($item_id, 'itemPriority'));
                                if ($iPriority > 10) $iPriority = 10;
                                $iPriority = $iPriority / 10;
                                $sitemap[] = array(
                                    'loc'        => $itemLoc,
                                    'lastmod'    => $lastmod,
                                    'priority'   => number_format($iPriority, 1),
                                    'changefreq' => $fq
                                );
                            } else {
                                $iTitle = $item['ititle'];
                                if (_CHARSET != 'UTF-8') {
                                    $iTitle = mb_conbert_encoding($iTitle, 'UTF-8', _CHARSET);
                                }
                                $sitemap[] = array(
                                    'title'            => $iTitle,
                                    'link'             => $itemLoc,
                                    'ror:updated'      => $lastmod,
                                    'ror:updatePeriod' => 'day',
                                    'ror:sortOrder'    => '0',
                                    'ror:resourceOf'   => 'sitemap',
                                );
                            }
                        }
                    }
                }

                if ($CONF['URLMode'] != $URLMode) {
                    $CONF['URLMode'] = $URLMode;
                }

            }

            $params = array('sitemap' => & $sitemap);
            $manager->notify('SiteMap', $params);

            header ("Content-type: application/xml");

            if (end($path_arr) == 'ror.xml') {

            // ror sitemap feed
            $sitemapHeader ="<" . "?xml version='1.0' encoding='UTF-8'?" . ">\n\n"
                           . "<!--  This file is a ROR Sitemap for describing this website to the search engines. "
                           . "For details about the ROR format, go to www.rorweb.com.   -->\n"
                           . '<rss version="2.0" xmlns:ror="http://rorweb.com/0.1/" >' . "\n"
                           . "<channel>\n";

            } else {

            // old Google sitemap protocol ver.0.84
//            $sitemapHeader  = "<" . "?xml version='1.0' encoding='UTF-8'?" . ">\n\n";
//                            . "\t<urlset" . ' xmlns="http://www.google.com/schemas/sitemap/0.84"' . "\n";
//                            . "\t" . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
//                            . "\t" . 'xsi:schemaLocation="http://www.google.com/schemas/sitemap/0.84' . "\n";
//                            . "\t" . '        http://www.google.com/schemas/sitemap/0.84/sitemap.xsd">' . "\n";

            // new sitemap common protocol ver 0.9
            $sitemapHeader  = "<" . "?xml version='1.0' encoding='UTF-8'?" . ">\n\n"
                            . '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n"
                            . '         xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n"
                            . '         http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"' . "\n"
                            . '         xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
            // uncomment and edit next line when you need "example_schema"
//            $sitemapHeader .= '         xmlns:example="http://www.example.com/schemas/example_schema"';
            $sitemapHeader .= '>';

            }

            echo $sitemapHeader;
            if (end($path_arr) == 'ror.xml') {
                echo $sitemapTitle;
            }

            while (list(, $url) = each($sitemap)) {

                if (end($path_arr) == 'ror.xml') {
                    echo "\t<item>\n";
                } else {
                    echo "\t<url>\n";
                }

                while (list($key, $value) = each($url)) {
                    if ($key == 'loc') {
                        $value = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $value);
                        $data  = "\t\t<" . $key . ">"
                               . hsc($value)
                               . "</" . $key . ">\n";
                    } else {
                        $data  = "\t\t<" . $key . ">"
                               . hsc($value)
                               . "</" . $key . ">\n";
                    }
                    echo $data;
                }

                if (end($path_arr) == 'ror.xml') {
                    echo "\t</item>\n";
                } else {
                    echo "\t</url>\n";
                }

            }

            if (end($path_arr) == 'ror.xml') {
                echo "</channel>\n</rss>\n";
            } else {
                echo "</urlset>\n";
            }
//            echo "</urlset>\n";
            exit;

        }
    }

    function pluginCheck($pluginName)
    {
        global $manager;
        if (!$manager->pluginInstalled('NP_' . $pluginName)) {
            return;
        } else {
            $plugin =& $manager->getPlugin('NP_' . $pluginName);
            return $plugin;
        }
    }

    function _prepareLink($base, $url) {
        if (substr($url, 0, 7) == 'http://') {
            return $url;
        } else {
            return $base . $url;
        }
    }

    function event_PostAddItem(&$data)
    {
        global $manager, $CONF;

        $item_id =  intval($data['itemid']);
        $blog_id =  intval(getBlogIDFromItemID($item_id));
        $b       =& $manager->getBlog($blog_id);
        $b_url   =  $b->getURL();

        if (substr($b_url, -4) == '.php') $CONF['URLMode'] = 'normal';
        $usePathInfo = ($CONF['URLMode'] == 'pathinfo');

        if (substr($b_url, -1) == '/') {
            if (!$usePathInfo) {
                $b_url .= 'index.php?virtualpath=';
            }
        } elseif (substr($b_url, -4) == '.php') {
            $b_url .= '?virtualpath=';
        } else {
            if ($usePathInfo) {
                $b_url = $b_url . '/';
            } else {
                $b_url = $b_url . '/index.php?virtualpath=';
            }
        }
        $siteMap = $this->getBlogOption($blog_id, 'PcSitemap');

        if ($this->getBlogOption($blog_id, 'PingGoogle') == 'yes') {
            $baseURL = 'http://www.google.com/webmasters/sitemaps/ping?sitemap=';
            $utl     = $baseURL . urlencode($b_url . $siteMap);
            $url     = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $url);
            $fp      = @fopen($url, 'r');
            @fclose($fp);
            $MobileMap = $this->getBlogOption($blog_id, 'MobileSitemap');
            if (!empty($MobileMap)) {
                $url = $baseURL . urlencode($b_url . $MobileMap);
                $url = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $url);
                $fp  = @fopen($url, 'r');
                @fclose($fp);
            }
        }

        if ($this->getBlogOption($blog_id, 'PingYahoo') == 'yes') {    // &&
//            $this->getBlogOption($blog_id, 'YahooAPID') != '') {
//            $baseURL = 'http://search.yahooapis.com/SiteExplorerService/V1/updateNotification?appid='
//                     . $this->getBlogOption($blog_id, 'YahooAPID')
//                     . '&url=';
            $baseURL = 'http://search.yahooapis.com/SiteExplorerService/V1/ping?sitemap=';
            $url     = $baseURL . urlencode($b_url . $siteMap);
            $url     = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $url);
            $fp      = @fopen($url, 'r');
            @fclose($fp);
            $MobileMap = $this->getBlogOption($blog_id, 'MobileSitemap');
            if (!empty($MobileMap)) {
                $url = $baseURL . urlencode($b_url . $MobileMap);
                $url = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $url);
                $fp  = @fopen($url, 'r');
                @fclose($fp);
            }
        }

        if ($this->getBlogOption($blog_id, 'PingLive') == 'yes') {
            $baseURL = 'http://webmaster.live.com/webmaster/ping.aspx?siteMap=';
            $utl     = $baseURL . urlencode($b_url . $siteMap);
            $url     = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $url);
            $fp      = @fopen($url, 'r');
            @fclose($fp);
            $MobileMap = $this->getBlogOption($blog_id, 'MobileSitemap');
            if (!empty($MobileMap)) {
                $url = $baseURL . urlencode($b_url . $MobileMap);
                $url = preg_replace('|[^a-zA-Z0-9-~+_.?#=&;,/:@%]|i', '', $url);
                $fp  = @fopen($url, 'r');
                @fclose($fp);
            }
        }

    }

    function init()
    {
        global $admin;
        $language = str_replace( array('/','\\'), '', getLanguageName());
        if (is_file($this->getDirectory() . $language.'.php')) {
            include_once($this->getDirectory() . $language.'.php');
        }else {
            include_once($this->getDirectory() . 'english.php');
        }
    }

    function install()
    {
        $this->createOption('AllBlogMap',         _G_SITEMAP_ALLB,   'yesno', 'no');
        $this->createBlogOption('IncludeSitemap', _G_SITEMAP_INC,    'yesno', 'no');
        $this->createBlogOption('PingGoogle',     _G_SITEMAP_PING_G, 'yesno', 'yes');
        $this->createBlogOption('PingYahoo',      _G_SITEMAP_PING_Y, 'yesno', 'yes');
        $this->createBlogOption('PingLive',       _G_SITEMAP_PING_L, 'yesno', 'yes');
//        $this->createBlogOption('YahooAPID',      _G_SITEMAP_YAPID,  'text',  '');
        $this->createBlogOption('PcSitemap',      _G_SITEMAP_PCSM,   'text',  'sitemap.xml');
        $this->createBlogOption('MobileSitemap',  _G_SITEMAP_MBSM,   'text',  'msitemap.xml');
        $this->createBlogOption('blogPriority',   _G_SITEMAP_BPRI,   'text',  '10', 'datatype=numerical');
        $this->createCategoryOption('catPriority', _G_SITEMAP_CPRI,  'text',  '9', 'datatype=numerical');
        $this->createItemOption('itemPriority',   _G_SITEMAP_IPRI,   'text',  '10', 'datatype=numerical');
    }
}
