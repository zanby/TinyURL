<?php
/**
*   Zanby Enterprise Group Family System
*
*    Copyright (C) 2005-2011 Zanby LLC. (http://www.zanby.com)
*
*    This program is free software: you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation, either version 3 of the License, or
*    (at your option) any later version.
*
*    This program is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*    To contact Zanby LLC, send email to info@zanby.com.  Our mailing 
*    address is:
*
*            Zanby LLC
*            3611 Farmington Road
*            Minnetonka, MN 55305
*
* @category   Zanby
* @package    TinyURL
* @copyright  Copyright (c) 2005-2011 Zanby LLC. (http://www.zanby.com)
* @license    http://zanby.com/license/     GPL License
* @version    <this will be auto generated>
*/
if (false !== ($wsdl = file_get_contents(realpath($_SERVER['DOCUMENT_ROOT'].'/../library/tinyurl.wsdl')))) {
    $wsdl = str_replace('http://HTTP_HOST/tiny-server', 'http://'.$_SERVER['HTTP_HOST'].'/services/tinyurl/wsdl/', $wsdl);
    $wsdl = str_replace('http://HTTP_HOST/tiny.server.php', 'http://'.$_SERVER['HTTP_HOST'].'/tiny.server.php', $wsdl);
    header('Content-Type: text/xml');
    echo $wsdl;
    exit;
} else {
    throw new Exception('Faild load wsdl');
}