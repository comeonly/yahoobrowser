<?php
/**
 * Class longin and scraping your yahoo.co.jp pagese
 *
 * PHP version 5
 *
 * LICENSE:
 *
 * Copyright (c) 2012, Atunori Kamori <atunori.kamori@gmail.com>
 * All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the
 * "Software"), to deal in the Software without restriction, including
 * without limitation the rights to use, copy, modify, merge, publish,
 * distribute, sublicense, and/or sell copies of the Software, and to
 * permit persons to whom the Software is furnished to do so, subject to
 * the following conditions:
 *
 * The above copyright notice and this permission notice shall be
 * included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
 * EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS
 * BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN
 * ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWAR
 *
 * @category Scraping
 * @package  Yahoo_Browser
 * @author   Atunori Kamori <atunori.kamori@gmail.com>
 * @license  http://www.opensource.org/licenses/mit-license.php MIT License
 * @link     https://github.com/comeonly/yahoobrowser
 */

/*
 * Class representing a HTTP request message
 * PEAR package should be installed
 */
require 'Request.php';

/**
 * Class longin and scraping your yahoo.co.jp pagese
 *
 * @category Scraping
 * @package  Yahoo_Browser
 * @author   Atunori Kamori <atunori.kamori@gmail.com>
 * @license  http://www.opensource.org/licenses/mit-license.php MIT License
 * @link     https://github.com/comeonly/yahoobrowser
 */
class YahooBrowser
{
    /*
     * HTTP_Request Class Object
     */
    protected $rq;
    protected $cookies;
    protected $id;
    protected $pass;
    protected $body;

    /**
     * construction function
     *
     * @param string $id      yahoo user account
     * @param string $pass    yahoo user password
     * @param array  $cookies optionl settings if you know login cookies
     *
     * @return void
     */
    function __construct($id, $pass, $cookies = array())
    {
        $this->rq = new HTTP_Request();
        $this->rq->addHeader(
            'User-Agent',
            'Mozilla/6.0 (Windows; U; Windows NT 6.0; ja; rv:1.9.1.1) Gecko/20090715 Firefox/3.5.1 (.NET CLR 3.5.30729)'
        );
        $this->rq->addHeader('Keep-Alive', 115);
        $this->rq->addHeader('Connection', 'keep-alive');
        $this->id = $id;
        $this->pass = $pass;
        if (empty($cookies)) {
            $this->login();
        } else {
            $this->cookies = $cookies;
        }
    }

    /**
    * login yahoo
    *
    * @return boolean
    */
    function login()
    {
        $login_url = 'https://login.yahoo.co.jp/config/login?';
        $login_params = '.lg=jp&.intl=jp&.src=auc&.done=http://auctions.yahoo.co.jp/'
        $current_cookies = $this->cookies;
        $this->cookies = array();
        $this->getBody('http://auctions.yahoo.co.jp/', null);
        $this->getBody(
            $login_url . $login_params,
            'http://auctions.yahoo.co.jp/'
        );

        // get post params
        preg_match_all(
            '/document\.getElementsByName\("\.albatross"\)\[0\]\.value = "(.*?)";/',
            $this->body,
            $albatross,
            PREG_SET_ORDER
        );
        preg_match_all(
            '/<input type="hidden" name="(.*?)" value="(.*?)" ?>/',
            $this->body,
            $matches,
            PREG_SET_ORDER
        );

        $this->rq->setMethod(HTTP_REQUEST_METHOD_POST);
        foreach ($matches as $entry) {
            $this->rq->addPostData($entry[1], $entry[2]);
        }
        unset($this->rq->_postData['.nojs']);
        $this->rq->_postData['.albatross'] = $albatross[0][1];
        if (!isset($this->rq->_postData['.slogin'])) {
            $this->rq->addPostdata('login', $this->id);
            $this->rq->addPostdata('.persistent', 'y');
        }
        $this->rq->addPostdata('passwd', $this->pass);

        // need more than 3 sec before submit
        sleep(3);

        $this->getBody(
            $login_url . $login_params,
            'https://login.yahoo.co.jp/config/login?'
        );

        $response_cookies = $this->cookies;
        $this->cookies = $current_cookies;
        $this->_updateCookies($response_cookies);

        if (empty($this->cookies)) {
            return false;
        }
        return true;
    }

    /**
    * get respomde body function
    *
    * @param string $url     target url
    * @param string $referer referer url
    *
    * @return void
    */
    function getBody($url, $referer = '')
    {
        if (empty($url)) {
            return null;
        }
        $this->rq->setURL($url);
        $this->rq->addHeader('Referer', $referer);
        $this->rq->clearCookies();
        if (!empty($this->cookies)) {
            foreach ($this->cookies as $cookie) {
                $this->rq->addCookie($cookie['name'], $cookie['value']);
            }
        }
        $this->rq->sendRequest();
        $this->_updateCookies();
        $this->body = $this->rq->getResponseBody();
    }

    /**
    * Update cookies function
    *
    * @param array $response_cookies responsed cookies
    *
    * @return boolean
    */
    function _updateCookies($response_cookies = array())
    {
        if (empty($response_cookies)) {
            $response_cookies = $this->rq->getResponseCookies();
        }
        if (empty($response_cookies)) {
            return false;
        }
        for ($i=0; $i < count($response_cookies); $i++) {
            $create = true;
            for ($j=0; $j < count($this->cookies); $j++) {
                if ($this->cookies[$j]['name'] === $response_cookies[$i]['name']) {
                    $this->cookies[$j]['value'] = $response_cookies[$i]['value'];
                    $create = false;
                }
            }
            if ($create) {
                $new_cookies[] = array(
                    'id' => '',
                    'yahoo_id' => $this->id,
                    'name' => $response_cookies[$i]['name'],
                    'value' => $response_cookies[$i]['value']
                );
            }
        }
        if (!empty($new_cookies)) {
            foreach ($new_cookies as $new_cookie) {
                $this->cookies[] = $new_cookie;
            }
        }
    }

} // END class
?>
