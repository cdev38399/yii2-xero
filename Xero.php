<?php
namespace xero;

class Xero
{
    const ENDPOINT = 'https://api.xero.com/api.xro/2.0/';//todo: make extension(github and composer) with config

    private $key;
    private $secret;
    private $public_cert;
    private $private_key;
    private $consumer;
    private $token;
    private $signature_method;
    private $format;

    public function __construct($key = false, $secret = false, $public_cert = false, $private_key = false, $format = 'json')
    {
        $this->key = $key;
        $this->secret = $secret;
        $this->public_cert = $public_cert;
        $this->private_key = $private_key;
        if (!($this->key) || !($this->secret) || !($this->public_cert) || !($this->private_key)) {
            error_log('Stuff missing ');
            return false;
        }
        if (!file_exists($this->public_cert))
            throw new Exception('Public cert does not exist: ' . $this->public_cert);
        if (!file_exists($this->private_key))
            throw new Exception('Private key does not exist: ' . $this->private_key);

        $this->consumer = new OAuthConsumer($this->key, $this->secret);
        $this->token = new OAuthToken($this->key, $this->secret);
        $this->signature_method = new OAuthSignatureMethod_Xero($this->public_cert, $this->private_key);
        $this->format = (in_array($format, array('xml', 'json'))) ? $format : 'json';
    }

    public function __call($name, $arguments)
    {
        $name = strtolower($name);
        $valid_methods = array('accounts', 'contacts', 'creditnotes', 'currencies', 'invoices', 'organisation', 'payments', 'taxrates', 'trackingcategories', 'items', 'banktransactions', 'brandingthemes', 'receipts', 'expenseclaims');
        $valid_post_methods = array('banktransactions', 'contacts', 'creditnotes', 'expenseclaims', 'invoices', 'items', 'manualjournals', 'receipts');
        $valid_put_methods = array('payments');
        $valid_get_methods = array('accounts', 'banktransactions', 'brandingthemes', 'contacts', 'creditnotes', 'currencies', 'employees', 'expenseclaims', 'invoices', 'items', 'journals', 'manualjournals', 'organisation', 'payments', 'receipts', 'taxrates', 'trackingcategories', 'users');
        $methods_map = array(
            'accounts' => 'Accounts',
            'banktransactions' => 'BankTransactions',
            'brandingthemes' => 'BrandingThemes',
            'contacts' => 'Contacts',
            'creditnotes' => 'CreditNotes',
            'currencies' => 'Currencies',
            'employees' => 'Employees',
            'expenseclaims' => 'ExpenseClaims',
            'invoices' => 'Invoices',
            'items' => 'Items',
            'journals' => 'Journals',
            'manualjournals' => 'ManualJournals',
            'organisation' => 'Organisation',
            'payments' => 'Payments',
            'receipts' => 'Receipts',
            'taxrates' => 'TaxRates',
            'trackingcategories' => 'TrackingCategories',
            'users' => 'Users'

        );
        if (!in_array($name, $valid_methods)) {
            throw new Exception('The selected method does not exist. Please use one of the following methods: ' . implode(', ', $methods_map));
        }
        if ((count($arguments) == 0) || (is_string($arguments[0])) || (is_numeric($arguments[0])) || ($arguments[0] === false)) {
            //it's a GET request
            if (!in_array($name, $valid_get_methods)) {
                return false;
            }
            $filterid = (count($arguments) > 0) ? strip_tags(strval($arguments[0])) : false;
            if (!empty($arguments) && $arguments[1] != false) $modified_after = (count($arguments) > 1) ? str_replace('X', 'T', date('Y-m-dXH:i:s', strtotime($arguments[1]))) : false;
            if (!empty($arguments) && $arguments[2] != false) $where = (count($arguments) > 2) ? $arguments[2] : false;
            if (isset($where) && is_array($where) && (count($where) > 0)) {
                $temp_where = '';
                foreach ($where as $wf => $wv) {
                    if (is_bool($wv)) {
                        $wv = ($wv) ? "%3d%3dtrue" : "%3d%3dfalse";
                    } else if (is_array($wv)) {
                        if (is_bool($wv[1])) {
                            $wv = ($wv[1]) ? rawurlencode($wv[0]) . "true" : rawurlencode($wv[0]) . "false";
                        } else {
                            $wv = rawurlencode($wv[0]) . "%22{$wv[1]}%22";
                        }
                    } else {
                        $wv = "%3d%3d%22$wv%22";
                    }
                    $temp_where .= "%26%26$wf$wv";
                }
                $where = strip_tags(substr($temp_where, 6));
            } elseif (isset($where)) {
                $where = strip_tags(strval($where));
            }
            $order = (count($arguments) > 3) ? strip_tags(strval($arguments[3])) : false;
            $acceptHeader = (!empty($arguments[4])) ? $arguments[4] : '';
            $method = $methods_map[$name];
            $xero_url = self::ENDPOINT . $method;
            if ($filterid) {
                $xero_url .= "/$filterid";
            }
            if (isset($where)) {
                $xero_url .= "?where=$where";
            }
            if ($order) {
                $xero_url .= "&order=$order";
            }
            $req = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'GET', $xero_url);
            $req->sign_request($this->signature_method, $this->consumer, $this->token);
            $ch = curl_init();
            if ($acceptHeader == 'pdf') {
                curl_setopt($ch, CURLOPT_HTTPHEADER,
                    array(
                        "Accept: application/" . $acceptHeader
                    )
                );
            }
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_URL, $req->to_url());
            if (isset($modified_after) && $modified_after != false) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, array("If-Modified-Since: $modified_after"));
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $temp_xero_response = curl_exec($ch);
            curl_close($ch);
            if ($acceptHeader == 'pdf') {
                return $temp_xero_response;

            }

            try {
                if (@simplexml_load_string($temp_xero_response) == false) {
                    throw new \Exception($temp_xero_response);
                    $xero_xml = false;
                } else {
                    $xero_xml = simplexml_load_string($temp_xero_response);
                }
            } catch (XeroException $e) {
                return $e->getMessage() . "<br/>";
            }


            if ($this->format == 'xml' && isset($xero_xml)) {
                return $xero_xml;
            } elseif (isset($xero_xml)) {
                return ArrayToXML::toArray($xero_xml);
            }
        } elseif ((count($arguments) == 1) || (is_array($arguments[0])) || (is_a($arguments[0], 'SimpleXMLElement'))) {
            //it's a POST or PUT request
            if (!(in_array($name, $valid_post_methods) || in_array($name, $valid_put_methods))) {
                return false;
            }
            $method = $methods_map[$name];
            if (is_a($arguments[0], 'SimpleXMLElement')) {
                $post_body = $arguments[0]->asXML();
            } elseif (is_array($arguments[0])) {
                $post_body = ArrayToXML::toXML($arguments[0], $rootNodeName = $method);
            }
            $post_body = trim(substr($post_body, (stripos($post_body, ">") + 1)));

            if (in_array($name, $valid_post_methods)) {
                $xero_url = self::ENDPOINT . $method;
                $req = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'POST', $xero_url, array('xml' => $post_body));
                $req->sign_request($this->signature_method, $this->consumer, $this->token);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_URL, $xero_url);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $req->to_postdata());
                curl_setopt($ch, CURLOPT_HEADER, $req->to_header());
            } else {
                $xero_url = self::ENDPOINT . $method;
                $req = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, 'PUT', $xero_url);
                $req->sign_request($this->signature_method, $this->consumer, $this->token);
                $xml = $post_body;
                $fh = fopen('php://memory', 'w+');
                fwrite($fh, $xml);
                rewind($fh);
                $ch = curl_init($req->to_url());
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_PUT, true);
                curl_setopt($ch, CURLOPT_INFILE, $fh);
                curl_setopt($ch, CURLOPT_INFILESIZE, strlen($xml));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $xero_response = curl_exec($ch);
            if (isset($fh)) fclose($fh);
            try {
                if (@simplexml_load_string($xero_response) == false) {
                    throw new Exception($xero_response);

                } else {
                    $xero_xml = simplexml_load_string($xero_response);
                }
            } catch (XeroException $e) {
                //display custom message
                return $e->getMessage() . "<br/>";
            }

            curl_close($ch);
            if (!isset($xero_xml)) {
                return false;
            }
            if ($this->format == 'xml' && isset($xero_xml)) {
                return $xero_xml;
            } elseif (isset($xero_xml)) {
                return ArrayToXML::toArray($xero_xml);
            }
        } else {
            return false;
        }
    }

    public function __get($name)
    {
        return $this->$name();
    }

    public static function sayHello()
    {
        echo "hello\n";
    }
}