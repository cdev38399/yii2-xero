<?php
namespace xero;

class OAuthSignatureMethod_Xero extends OAuthSignatureMethod_RSA_SHA1
{
    protected $public_cert;
    protected $private_key;

    public function __construct($public_cert, $private_key)
    {
        $this->public_cert = $public_cert;
        $this->private_key = $private_key;
    }

    protected function fetch_public_cert(&$request)
    {
        return file_get_contents($this->public_cert);
    }

    protected function fetch_private_cert(&$request)
    {
        return file_get_contents($this->private_key);
    }
}