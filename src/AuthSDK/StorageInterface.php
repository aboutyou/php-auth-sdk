<?php

namespace AuthSDK;

interface StorageInterface
{
    public function init($client_id);

    public function getPersistentData($key);

    public function clearPersistentData($key);

    public function clearAllPersistentData();

    public function setPersistentData($key, $value);
}
