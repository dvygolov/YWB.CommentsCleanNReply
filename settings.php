<?php
class Settings{
    public static $password = "qwerty"; //password for admin panel
    public static $dbFilePath = __DIR__."/comments.db"; //change this for security reasons!
    public static $fbApiVersion = 22;
    public static $debug = true;
}

if (Settings::$debug){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}