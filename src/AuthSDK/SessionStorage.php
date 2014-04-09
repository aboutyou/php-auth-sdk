<?php
/**
 * Created by PhpStorm.
 * User: georg
 * Date: 27.01.14
 * Time: 13:13
 */

namespace AuthSDK;


class SessionStorage implements StorageInterface
{


    private $_sessionPrefix;

    public function setPersistentData($key, $value)
    {
        $_SESSION[$this->_sessionPrefix][$key] = $value;
    }

    public function getPersistentData($key)
    {
        if (isset($_SESSION[$this->_sessionPrefix][$key])) {
            return $_SESSION[$this->_sessionPrefix][$key];
        } else {
            return null;
        }
    }

    public function clearPersistentData($key)
    {
        if (isset($_SESSION[$this->_sessionPrefix][$key])) {
            unset($_SESSION[$this->_sessionPrefix][$key]);
        }
    }

    public function clearAllPersistentData()
    {
        $_SESSION[$this->_sessionPrefix] = array();
    }

    public function init($client_id)
    {
        $this->_sessionPrefix = $client_id . '_' . md5('av_authorize' . $client_id);
        if (!session_id()) {
            session_start();
        }
        if (!isset($_SESSION[$this->_sessionPrefix])) {
            $_SESSION[$this->_sessionPrefix] = array();
        }
    }
}

