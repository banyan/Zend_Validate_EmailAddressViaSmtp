<?php

$path2Zend = "/path/to/ZendFramework/library";
set_include_path(get_include_path() . PATH_SEPARATOR . $path2Zend);

/**
 * @see Zend_Validate_Abstract
 */
require_once 'Zend/Validate/Abstract.php';

/**
 * @see Zend_Mail_Protocol_Smtp
 */
require_once 'Zend/Mail/Protocol/Smtp.php';

class Zend_Validate_EmailAddressViaSmtp extends Zend_Validate_Abstract
{
    const INVALID          = 'emailAddressViaSmtpInvalid';
    const INVALID_FORMAT   = 'emailAddressViaSmtpInvalidFormat';
    const INVALID_HOSTNAME = 'emailAddressViaSmtpInvalidHostname';
    const INVALID_MX_RECORD = 'emailAddressViaSmtpInvalidMxRecord';
    const DNS_TIMEOUT      = 'emailAddressViaSmtpDnsTimeout';
    const UNKNOWN_USER     = 'emailAddressViaSmtpUnknownUser';
    const SMTP_TIMEOUT     = 'emailAddressViaSmtpSmtpTimeout';
    const SMTP_UNREACHABLE = 'emailAddressViaSmtpSmtpUnreachable';
    const MAILBOX_FULL     = 'emailAddressViaSmtpMailboxFull';

    /**
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID          => "Invalid type given, value should be a string",
        self::INVALID_FORMAT   => "'%value%' is not a valid email address format: missing @",
        self::INVALID_HOSTNAME => "'%hostname%' is not a valid hostname for email address '%value%'",
        self::DNS_TIMEOUT      => "DNS timeout",
        self::UNKNOWN_USER     => "'%localPart%' such a user does not exist",
        self::SMTP_TIMEOUT     => "SMTP timeout",
        self::SMTP_UNREACHABLE => "Cannot connect SMTP servers",
        self::MAILBOX_FULL     => "mailbox full"
    );

    /**
     * @var array
     */
    protected $_messageVariables = array(
        'hostname'  => '_hostname',
        'localPart' => '_localPart'
    );

    /**
     * @var string
     */
    protected $_hostname;

    /**
     * @var string
     */
    protected $_localPart;

    /**
     * @var string
     */
    protected $_senderAddr = "hoge@gmail.com";

    /**
     * timeout, defaults to 5.
     *
     * @var integer
     */
    protected $_timeout = 60;

    /**
     * checkMx
     *
     * @var boolean
     */
    protected $_checkMx = false;

    /**
     * Instantiates hostname validator for local use
     *
     * You can pass a bitfield to determine what types of hostnames are allowed.
     * These bitfields are defined by the ALLOW_* constants in Zend_Validate_Hostname
     * The default is to allow DNS hostnames only
     *
     * @return void
     */
    public function __construct($options = null)
    {
        // Set options
        if (is_array($options)) {
            $this->setOptions($options);
        }
    }

    /**
     * Whether MX checking via dns_get_mx is supported or not
     *
     * This currently only works on UNIX systems
     *
     * @return boolean
     */
    public function validateMxSupported()
    {
        return function_exists('dns_get_mx');
    }

    /**
     * Defined by Zend_Validate_Interface
     *
     * Returns true if and only if $value is a valid email address
     * according to RFC2822
     *
     * @link   http://www.ietf.org/rfc/rfc2822.txt RFC2822
     * @link   http://www.columbia.edu/kermit/ascii.html US-ASCII characters
     * @param  string $value
     * @return boolean
     */
    public function isValid($value)
    {
        if (!is_string($value)) {
            $this->_error(self::INVALID);
            return false;
        }

        $pattern = '/^(.*)@(.*)$/';
        preg_match($pattern, trim($value), $matches);
        list( , $username, $hostname) = $matches; // skip $matches[0] that matched the full pattern

        if (!$hostname) {
            $this->_error(self::INVALID_FORMAT);
            return false;
        }

        return $this->_checkNetwork($username, $hostname);
    }

    /**
     *
     *
     *
     * @param  string $username
     * @param  string $hostname
     * @return boolean
     */
    protected function _checkNetwork($username, $hostname)
    {
        // list of mail servers for hostname
        $mailServers = array();
        $timeout     = $this->_timeout;

        // MX check on hostname via dns_get_mx()
        if ($this->validateMxSupported()) {
            dns_get_mx($hostname, $mailServers);
            if (count($mailServers) < 1) {
                if (!($mailServers = gethostbynamel($hostname))) {
                    $this->_error(self::INVALID_MX_RECORD);
                    return false;
                }
            }
        } else {
            /**
             * MX checks are not supported by this system
             * @see Zend_Validate_Exception
             */
            require_once 'Zend/Validate/Exception.php';
            throw new Zend_Validate_Exception('Internal error: MX checking not available on this system');
        }

        foreach ($mailServers as $mailServer) {
            $smtp = new Zend_Mail_Protocol_Smtp();
            if (!$smtp->connect()) {
                $this->_error(self::SMTP_CONNECTION_FAILED);
                continue;
            }
            // TODO handle SMTP
            $smtp->helo();
            $smtp->mail();
            $smtp->rcpt();

            $res                 = $smtp->getResponse();
            $this->response_code = $res[0];
            $this->response      = $res[1];

            $smtp->disconnect();

            //$res = $smtp->getResponse();
            //if ($res[0] == -1) {
                //$this->_error(self::SMTP_CONNECTION_FAILED);
                //continue;
            //} else {
                //$this->setResponse($res[0], $res[1]);
                //return $this->result();
            //}

            //$smtp->rcpt($email);
            //$res = $smtp->getResponse();
            //if ($res[0] == -1) {
                //$this->_error(self::SMTP_CONNECTION_FAILED);
                //continue;
            //} else {
                //$this->setResponse($res[0], $res[1]);
                //return $this->result();
            //}
        }
        return $this->_result;
    }
}

//$smtp = new Zend_Mail_Protocol_Smtp();
//if (!$smtp->connect()) {
    //echo 'error';
//}
//var_dump($smtp->helo());
//var_dump($smtp->mail());
//var_dump($smtp->mail());
//var_dump($smtp->getResponse());
