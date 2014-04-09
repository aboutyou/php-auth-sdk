<?php

namespace AuthSDK;


class AuthResult
{

    /**
     * @var array
     */
    private $_errors;
    /**
     * @var \stdClass
     */
    private $_result;


    /**
     * If there errors, $errors should contain descriptions TODO Maybe ['errorcode1'=>'errortext1', ...]?
     * and the $result MAY contain additional information for debugging proposes (or should be set to null)
     *
     * If there aren't any $errors, $result MUST contain a meaningful result and $errors MUST be an empty array
     *
     * @param \stdClass $result
     * @param array $errors
     */
    public function __construct($result, $errors = array())
    {
        $this->_result = $result;
        $this->_errors = $errors;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return (bool)$this->_errors;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * @return \stdClass
     */
    public function getResult()
    {
        return $this->_result;
    }
}
