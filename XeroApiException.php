<?php
namespace xero;

class XeroApiException extends Exception
{
    private $xml;

    public function __construct($xml_exception)
    {
        $this->xml = $xml_exception;
        $xml = new SimpleXMLElement($xml_exception);

        list($message) = $xml->xpath('/ApiException/Message');
        list($errorNumber) = $xml->xpath('/ApiException/ErrorNumber');
        list($type) = $xml->xpath('/ApiException/Type');

        parent::__construct((string)$type . ': ' . (string)$message, (int)$errorNumber);

        $this->type = (string)$type;
    }

    public function getXML()
    {
        return $this->xml;
    }

    public static function isException($xml)
    {
        return preg_match('/^<ApiException.*>/', $xml);
    }


}