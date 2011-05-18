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
class Logging
{
    private $_use       = false;
    private $_resource  = null;

    public function __construct()
    {
        defined('APPLICATION_ENV')
            || define('APPLICATION_ENV', getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production');
        $this->_readConfig();
    }

    public function __destruct()
    {
        if ( $this->isEnabled() )
            fclose($this->_resource);
    }

    private function _readConfig()
    {
        if ( false !== ($xml = realpath(dirname(__FILE__).'/../config/cfg.tinyserver.xml')) ) {
            require_once 'Zend/Config/Xml.php';
            $config = new Zend_Config_Xml($xml, APPLICATION_ENV);

            $this->_use = ( strtolower($config->logging->use) === 'on' ) ? true : false;
            if ( $this->_use === true ) {
                $file = $config->logging->file;
                if ( false === realpath($file) ) {
                    $dir  = dirname($file);
                    if ( !file_exists($dir) )
                        mkdir($dir, 0755, true);
                    if ( file_exists($dir) && is_writeable($dir) )
                        touch($file);
                }
                $file = realpath($file);
                if ( ! ($file && is_writeable($file)) ) {
                    $this->_use  = false;
                } else if ( FALSE === ($this->_resource = fopen($file, 'a')) ) {
                    $this->_use = false;
                    $this->_resource = null;
                }
            }
        }
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->_use;
    }

    /**
     * log 
     * 
     * @param string $str 
     * @access public
     * @return boolean
     */
    public function log($str)
    {
        if ( empty($str) || !$this->isEnabled() )
            return false;

        $host   = (!empty($_SERVER['REMOTE_ADDR'])) ? gethostbyaddr($_SERVER['REMOTE_ADDR']) : 'unknown';
        $str    = sprintf("Date: %s; Request from: %s; Message: %s\n", date('d.m.Y H:i'), $host, trim($str));
        $strlen = strlen($str);
        
        if ( fwrite($this->_resource, $str, $strlen) != $strlen ) {
            return false;
        }

        return true;
    }
}

