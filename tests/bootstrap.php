<?php

require_once(__DIR__ . "/../vendor/autoload.php");

if (!class_exists('PHPUnit_Framework_TestCase')) {
    class_alias(\PHPUnit\Framework\TestCase::class, 'PHPUnit_Framework_TestCase');
}

require_once(__DIR__ . "/Integration/BaseIntegration.php");
require_once(__DIR__ . "/Integration/Parsers/CustomSystemParser.php");
require_once(__DIR__ . "/Integration/Parsers/CustomXMLParser.php");
