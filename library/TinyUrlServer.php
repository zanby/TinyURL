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
require_once 'Logging.php';

set_include_path(get_include_path().PATH_SEPARATOR.realpath($_SERVER['DOCUMENT_ROOT'].'/../library'));

defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', ( getenv('APPLICATION_ENV') ) ? getenv('APPLICATION_ENV') : 'production');

class TinyUrlServer
{
    /******** Exception codes *********/
    const E_DB_CONNECTION       = 0x1;
    const E_DB_QUERY_EXEC       = 0x2;
    const E_DB_QUERY_PREPARE    = 0x4;
    const E_DB_INSERT           = 0x8;
    const E_DB_SELECT           = 0x10;
    const E_DB_DELETE           = 0x20;
    const E_UNEXPECTED_CONTEXT  = 0x40;
    const E_URL_EMPTY           = 0x80;
    const E_URL_TOO_SHORT       = 0x100;
    const E_URL_TOO_LONG        = 0x200;
    const E_URL_INCORRECT       = 0x400;
    const E_URL_GENERATE_LIMIT  = 0x800;
    const E_CONFIG_INCORRECT    = 0x1000;
    const E_AUTH_FAILED         = 0x2000;
    const E_READ_ONLY_CONTEXT   = 0x4000;
    const E_PARAM_INCORRECT     = 0x8000;
    /***********************************/

    /**********************
     *  Database options  *
     **********************/
    static private $DB_NAME;
    static private $DB_HOST;
    static private $DB_USER;
    static private $DB_PASS;
    static private $DB_PORT;
    static private $DB_TABLE;
    /**********************/
    //  Users authentiation table
    const DB_USERS_TABLE = 'users';

    /** @var int What key length need to generate **/
    static private $keyLength = 10;
    /** @var int Number of attempts for generate key **/
    static private $attempt = 5;
    /** @var boolean **/
    static private $usePrefix = true;
    /** @var Zend_Config_Xml **/
    static private $cfg;
    /** @var bool **/
    private $authenticated = false;
    /** @var PDO **/
    static private $pdo = null;
    /** @var Logging **/
    static private $logging = null;

    public function __construct()
    {
        if ( self::$logging === null ) {
            self::$logging = new Logging;
        }
    }

    /**
     * @return boolean Is Valid authentication or throw SoapFault
     * @internal hasAuth
     * @throw SoapFault
     */
    public function hasAuth()
    {
        if ( $this->authenticated !== true ) {
            self::$logging->log("Authentification required");
            throw new SoapFault((string)self::E_AUTH_FAILED, "Authentification required");
        }
        return true;
    }

    /**
     * @return Zend_Config_Xml
     */
    static private function getCfg()
    {
        if ( self::$logging === null ) {
            self::$logging = new Logging;
        }

        if ( NULL === self::$cfg ) {
            require_once 'Zend/Config/Xml.php';
            if ( false === ($xml = realpath(dirname(__FILE__).'/../config/cfg.tinyserver.xml')) ) {
                self::$logging->log('Configuration filename is incorrect');
                throw new SoapFault((string)self::E_CONFIG_INCORRECT, 'Configuration filename is incorrect');
            }
            try {
                self::$cfg = new Zend_Config_Xml($xml, APPLICATION_ENV);
            } catch ( Zend_Config_Exception $e ) {
                self::$logging->log('TinyUrl configuration is missing');
                throw new SoapFault((string)self::E_CONFIG_INCORRECT, "TinyUrl configuration is missing.");
            }
        }
        return self::$cfg;
    }

    /**
     * @return void
     */
    static private function readConfig()
    {
        if ( NULL === min(self::$DB_HOST, self::$DB_NAME, self::$DB_PASS, self::$DB_USER, self::$DB_TABLE) ) {
            $cfg = self::getCfg();

            self::$DB_HOST   = $cfg->db->host;
            self::$DB_NAME   = $cfg->db->name;
            self::$DB_PASS   = $cfg->db->pass;
            self::$DB_USER   = $cfg->db->user;
            self::$DB_TABLE  = $cfg->db->table;
            self::$DB_PORT   = ( empty($cfg->db->port) ? null : $cfg->db->port );
            self::$usePrefix = ( ($cfg->useprefix === '' || (int)$cfg->useprefix === 1) ? true : false );
            self::$attempt   = ( empty($cfg->attempt) ? 5 : $cfg->attempt );
            self::$keyLength = ( empty($cfg->keylength) ? 10 : $cfg->keylength );
        }

        if ( NULL === min(self::$DB_HOST, self::$DB_NAME, self::$DB_PASS, self::$DB_USER, self::$DB_TABLE) ) {
            if ( self::$logging === null ) {
                self::$logging = new Logging;
            }
            self::$logging->log('TinyUrl configuration is missing. Please, check required params');
            throw new SoapFault((string)self::E_CONFIG_INCORRECT, "Tiny Url configuration is missing. Please check required params");
        }
    }

    /**
     * @return bool
     */
    static private function connectPdo()
    {
        if ( null !== self::$pdo )
            return true;

        self::readConfig();

        $dns =  "mysql:dbname=".self::$DB_NAME
                .";host=".self::$DB_HOST
                .( (self::$DB_PORT != '') ? ";port=".self::$DB_PORT : '' );
        try {
            self::$pdo = new PDO($dns, self::$DB_USER, self::$DB_PASS);
        } catch ( PDOException $e ) {
            if ( self::$logging === null ) {
                self::$logging = new Logging;
            }
            self::$logging->log('Error connection to DB: '.$e->getMessage());
            throw new SoapFault((string)self::E_DB_CONNECTION, "Error connection to DB");
        }
        return true;
    }

    /**
     * @param string $baseUrl
     * @return string
     */
    private function getBaseUrl($baseUrl)
    {
        if ( empty($baseUrl) ) {
            self::$logging->log('Base url is required and can\'t be empty');
            throw new SoapFault((string)self::E_URL_EMPTY, "Base url is required and can't be empty");
        }
        return rtrim($baseUrl, ' /').'/';
    }

    /**
     * @param string $context
     * @return string
     * @throw SoapFault
     */
    private function makeKey($context = null)
    {
        $key = '';
        if ( self::$usePrefix == 1 ) {
            $cfg = self::getCfg();
            if ( empty($cfg->context->{$context}) ) {
                self::$logging->log("Context '{$context}' is wrong");
                throw new SoapFault((string)self::E_CONFIG_INCORRECT, "Context '{$context}' is missing");
            }
            $key = $cfg->context->{$context}->prefix;   // Set prefix before random key sequence
        }
        $needSymbols = self::$keyLength - strlen($key);

        $string = '0123456789qwertyuioplkjhgfdsazxcvbnmQWERTYUIOPLKJHGFDSAZXCVBNM0123456789qwertyuioplkjhgfdsazxcvbnmQWERTYUIOPLKJHGFDSAZXCVBNM';
        for ( $i = 0; $i < $needSymbols; $i++ )
            $key .= $string[rand(0, strlen($string)-1)];
        return $key;
    }

    /**
     * @param string $key
     * @return bool
     */
    private function isKeyExists($key)
    {
        $sql = "SELECT COUNT(*) AS cnt FROM `".self::$DB_TABLE."` WHERE `key` = :key LIMIT 1";

        try {
            $statement = self::$pdo->prepare($sql);
            if ( FALSE === $statement ) {
                self::$logging->log("Error query prepare: '{$sql}'");
                throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
            }
        } catch ( PDOException $e ) {
            self::$logging->log("Error query prepare: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
        }

        $statement->bindParam(':key', $key, PDO::PARAM_STR, self::$keyLength);
        if ( $statement->execute() ) {
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ( $result && isset($result['cnt']) ) {
                if ( $result['cnt'] > 0 )
                    return true;
                else
                    return false;
            } else {
                self::$logging->log("Error query execute: '{$sql}'");
                throw new SoapFault((string)self::E_DB_QUERY_EXEC, 'Error query execute: "'.$sql.'"');
            }
        } else {
            self::$logging->log("Error query execute: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_EXEC, 'Error query execute: "'.$sql.'"');
        }
    }

    /**
     * @param string $url
     * @return array
     */
    private function getUrlParts($url)
    {
        $outArray = array('base_url' => '', 'create_date' => '', 'protocol' => '', 'query_str' => '');
        $protocol = '';

        if ( stripos($url, 'http://') !== false ) {
            $protocol = 'http://';
            $url = substr($url, strlen('http://'));
        } elseif ( stripos($url, 'https://') !== false ) {
            $protocol = 'https://';
            $url = substr($url, strlen('https://'));
        }
        $urlParts = explode('/', $url);
        $outArray['base_url']    = $urlParts[0];
        $outArray['protocol']    = $protocol;
        $outArray['query_str']   = substr($url, strlen($urlParts[0]));
        $outArray['create_date'] = date('Y-m-d H:i:s');

        return $outArray;
    }

    /**
     * Fuction work well with Tiny Url formats:
     *      http://domaine/j67SgwMw1D
     *      http://domaine/j67SgwMw1D/params/
     *      https://domaine/j67SgwMw1D
     *      https://domaine/j67SgwMw1D/params/
     *      j67SgwMw1D
     *      domaine/j67SgwMw1D
     *      j67SgwMw1D/other/sequense    ---  for $_SERVER['REQUEST_URI']
     *
     * @param string $tinyUrl
     * @return string
     * @throw SoapFault
     */
    private function getKeyFromTiny($tinyUrl)
    {
        $tinyUrl = trim($tinyUrl);
        if ( strpos($tinyUrl, 'http://') !== false )
            $tinyUrl = substr($tinyUrl, strlen('http://'));
        elseif ( strpos($tinyUrl, 'https://') !== false )
            $tinyUrl = substr($tinyUrl, strlen('https://'));

        if ( empty($tinyUrl) ) {
            self::$logging->log("TinyUrl key can't be empty");
            throw new SoapFault((string)self::E_URL_EMPTY, 'TinyUrl key can\'t be empty;');
        } elseif ( strlen($tinyUrl) < self::$keyLength ) {
            self::$logging->log("TinyUrl key too short, length must be ".self::$keyLength." symbols");
            throw new SoapFault((string)self::E_URL_TOO_SHORT, 'Tiny Url too short, length must be '.self::$keyLength.' symbols');
        } elseif ( strpos($tinyUrl, '/') === false && strlen($tinyUrl) > self::$keyLength ) {
            self::$logging->log('Tiny Url too long, length must be '.self::$keyLength.' symbols');
            throw new SoapFault((string)self::E_URL_TOO_LONG, 'Tiny Url too long, length must be '.self::$keyLength.' symbols');
        } elseif ( preg_match("/^[0-9a-z]{".self::$keyLength."}$/i", $tinyUrl) ) {
            return $tinyUrl;
        } elseif ( strpos($tinyUrl, '/') !== false ) {
            $parts = explode('/', $tinyUrl);
            return $this->getKeyFromTiny($parts[1]);
        } else {
            self::$logging->log('Incorrect Tiny Url template or length: url is "'.$tinyUrl.'"');
            throw new SoapFault((string)self::E_URL_INCORRECT, 'Incorrect Tiny Url template or length: url is "'.$tinyUrl.'"');
        }
    }

    /**
     * @param string $url
     * @return string|false
     */
    private function isUrlExists( $url )
    {
        self::connectPdo();

        $sql = 'SELECT `key` FROM `'.self::$DB_TABLE.'` WHERE `base_url` = :baseurl AND `full_url` = :fullurl LIMIT 1';

        try {
            $statement = self::$pdo->prepare($sql);
            if ( false === $statement ) {
                self::$logging->log("Error query prepare: '{$sql}'");
                throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
            }
        } catch ( PDOException $e ) {
            self::$logging->log("Error query prepare: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
        }

        $urlParts = $this->getUrlParts($url);
        $baseUrl = $urlParts['protocol'].$urlParts['base_url'];
        $queryStr = $urlParts['query_str'];

        $statement->bindParam(':baseurl', $baseUrl, PDO::PARAM_STR, 50);        //  50  -> DB Field length
        $statement->bindParam(':fullurl', $queryStr, PDO::PARAM_STR, 255);      //  255 -> DB Field length

        if ( $statement->execute() ) {
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ( $result ) {
                return $result['key'];
            } else {
                return false;
            }
        } else {
            self::$logging->log("Error query execute: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_EXEC, 'Error query execute: "'.$sql.'"');
        }
    }

    /**
     * Set all static properties to NULL
     */
    public function  __destruct() {
        $this->authenticated    = false;
        self::$DB_NAME          = null;
        self::$DB_HOST          = null;
        self::$DB_USER          = null;
        self::$DB_PASS          = null;
        self::$DB_PORT          = null;
        self::$DB_TABLE         = null;
        self::$keyLength        = null;
        self::$attempt          = null;
        self::$usePrefix        = null;
        self::$cfg              = null;
        self::$pdo              = null;
    }

    /**
     * @param UsernameToken $user Username and Password
     * @internal soapheader
     */
    public function UsernameToken( $user )
    {
        $this->authenticated = false;
        self::connectPdo();

        if ( !empty($user->Username) && !empty($user->Password) ) {
            $sql = "SELECT * FROM `".self::DB_USERS_TABLE."` WHERE `login` = :username LIMIT 1";
            try {
                $statement = self::$pdo->prepare($sql);
                if ( false === $statement ) {
                    self::$logging->log("Error query prepare: '{$sql}'");
                    throw new Exception('Incorrect statement');
                }
            } catch ( Exception $e ) { return; }

            $username = $user->Username;
            $statement->bindParam(':username', $username, PDO::PARAM_STR, 50); // 50 -> DB Field length
            if ( $statement->execute() ) {
                $result = $statement->fetch(PDO::FETCH_ASSOC);
                if ( $result ) {
                    if ( trim($result['hash']) === (trim($user->Password)) ) {
                        $this->authenticated = true;
                    }
                    //  Set params to NULL for read refresh configuration with correct CONTEXT
                    self::$cfg      = null;
                    self::$pdo      = null;
                }
            }
        }
    }

    /**
     * @param string $url First string
     * @param string $baseUrl Second string
     * @param string $context Third string
     * @return string Tinyurl string
     * @internal soaprequires UsernameToken
     * @throw SoapFault
     */
    public function getTinyUrl($url, $returnBaseUrl = null, $context = null)
    {
        if ( empty($url) ) {
            self::$logging->log('Url for encode to TinyUrl format can\'t be empty');
            throw new SoapFault((string)self::E_URL_EMPTY, 'Url for encode to TinyUrl format can\'t be empty');
        }

        if ( empty($context) || $context === 'generalcontext' ) {
            self::$logging->log('Write operation with READ-ONLY context');
            throw new SoapFault((string)self::E_READ_ONLY_CONTEXT, 'Write operation with READ-ONLY context');
        }

        $this->hasAuth();
        self::connectPdo();

        $presentTiny = $this->isUrlExists( $url );
        if ( $presentTiny !== FALSE ) {
            return $this->getBaseUrl($returnBaseUrl).$presentTiny;
        }

        $attempt = self::$attempt;
        $key = null;
        while ( $attempt > 0 ) {
            $key = $this->makeKey($context);
            if ( !$this->isKeyExists($key) )
                break;
            else
                $key = null;
            --$attempt;
        }
        if ( null === $key ) {
            self::$logging->log('Can\'t generate correct KEY, all attempt was lost');
            throw new SoapFault((string)self::E_URL_GENERATE_LIMIT, 'Can\'t generate correct KEY, all attempt was lost');
        }

        $sql = 'INSERT `'.self::$DB_TABLE.'` SET `key` = :key, `base_url` = :baseurl, `full_url` = :fullurl, `create_date` = :createdate';

        try {
            $statement = self::$pdo->prepare($sql);
            if ( false === $statement ) {
                self::$logging->log("Error query prepare: '{$sql}'");
                throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
            }
        } catch ( PDOException $e ) {
            self::$logging->log("Error query prepare: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
        }

        $urlParts = $this->getUrlParts($url);
        $baseUrl = $urlParts['protocol'].$urlParts['base_url'];
        $queryStr = $urlParts['query_str'];
        $createDate = $urlParts['create_date'];

        $statement->bindParam(':key', $key, PDO::PARAM_STR, self::$keyLength);  //  DB Field length
        $statement->bindParam(':baseurl', $baseUrl, PDO::PARAM_STR, 50);        //  50  -> DB Field length
        $statement->bindParam(':fullurl', $queryStr, PDO::PARAM_STR, 255);      //  255 -> DB Field length
        $statement->bindParam(':createdate', $createDate, PDO::PARAM_STR, 19);  //  19  -> DB Field length

        if ( $statement->execute() ) {
            if ( !$statement->rowCount() ) {
                self::$logging->log('New Tiny Url row not inserted to DB. Query: "'.$sql.'"');
                throw new SoapFault((string)self::E_DB_INSERT, 'New Tiny Url row not inserted to DB. Query: "'.$sql.'"');
            }
        } else {
            self::$logging->log('Error query execute: "'.$sql.'"');
            throw new SoapFault((string)self::E_DB_QUERY_EXEC, 'Error query execute: "'.$sql.'"');
        }

        return $this->getBaseUrl($returnBaseUrl).$key;
    }

    /**
     * @param string $tinyUrl First string
     * @return string Fullurl string
     * @internal soaprequires UsernameToken
     * @throw SoapFault
     */
    public function getFullUrl($tinyUrl)
    {
        $this->hasAuth();
        self::connectPdo();

        $key = $this->getKeyFromTiny($tinyUrl);

        $sql = "SELECT * FROM `".self::$DB_TABLE."` WHERE `key` = :key LIMIT 1";

        try {
            $statement = self::$pdo->prepare($sql);
            if ( false === $statement ) {
                self::$logging->log("Error query prepare: '{$sql}'");
                throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
            }
        } catch ( PDOException $e ) {
            self::$logging->log("Error query prepare: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
        }

        $statement->bindParam(':key', $key, PDO::PARAM_STR, self::$keyLength);
        if ( $statement->execute() ) {
            $result = $statement->fetch(PDO::FETCH_ASSOC);
            if ( $result ) {
                $add = trim(substr($tinyUrl, strpos($tinyUrl, $key)+strlen($key)), ' /');
                $url = rtrim($result['base_url'].$result['full_url'], ' /').'/';
                if ( !empty($add) )
                    $url .= $add.'/';
                return $url;
            } else {
                self::$logging->log('Full Url by key "'.$key.'" not found');
                throw new SoapFault((string)self::E_DB_SELECT, 'Full Url by key "'.$key.'" not found');
            }
        } else {
            self::$logging->log('Error query execute: "'.$sql.'"');
            throw new SoapFault((string)self::E_DB_QUERY_EXEC, 'Error query execute: "'.$sql.'"');
        }
    }

    /**
     * @param string $key First string
     * @return int Number of deleted rows
     * @internal soaprequires UsernameToken
     * @throw SoapFault
     */
    public function delete( $key )
    {
        if  ( empty($key) || !is_string($key) ) {
            self::$logging->log('Tiny key must be string');
            throw new SoapFault((string)self::E_PARAM_INCORRECT, 'Tiny key must be string');
        }
        $this->hasAuth();
        self::connectPdo();
        $key = $this->getKeyFromTiny($key);

        $sql = "DELETE FROM `".self::$DB_TABLE."` WHERE `key` = :key";
        
        try {
            $statement = self::$pdo->prepare($sql);
            if ( false === $statement ) {
                self::$logging->log("Error query prepare: '{$sql}'");
                throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
            }
        } catch ( PDOException $e ) {
            self::$logging->log("Error query prepare: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_PREPARE, "Error query prepare: '{$sql}'");
        }

        $statement->bindParam(':key', $key, PDO::PARAM_STR, self::$keyLength);
        if ( $statement->execute() ) {
            return $statement->rowCount();
        } else {
            self::$logging->log("Error query execute: '{$sql}'");
            throw new SoapFault((string)self::E_DB_QUERY_EXEC, 'Error query execute: "'.$sql.'"');
        }
    }
}

