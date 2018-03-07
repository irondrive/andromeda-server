<?php namespace Andromeda\Core\Database; if (!defined('Andromeda')) { die(); }

class Config
{
    const PERSISTENT = false;
    const CONNECT = "mysql: host=localhost; dbname=Andromeda2"; 
    const PREFIX = "a2_";
    const USERNAME = "root"; 
    const PASSWORD = "password";     
}
