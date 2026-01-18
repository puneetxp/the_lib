<?php

namespace The;

class Mail
{
    // Subject
    public $subject = 'Birthday Reminders for August';
    public function subject($x): Mail
    {
        $this->subject = $x;
        return $this;
    }
    public $message;
    // Message
    public function message($x): Mail
    {
        $this->message = $x;
        return $this;
    }
    public $cc = [];
    public function Cc($x): Mail
    {
        $this->cc[] = $x;
        return $this;
    }
    // public $from = [];
    // public function from($x): Mail
    // {
    //     $this->from = $x;
    //     return $this;
    // }
    public $bcc = [];
    public function Bcc($x): Mail
    {
        $this->bcc[] = $x;
        return $this;
    }
    public $headers = [];
    public function headers($e): Mail
    {
        $this->headers[] = $e;
        return $this;
    }
    public $to = [];
    public function to(array $e): Mail
    {
        $this->to[] = $e;
        return $this;
    }
    public function getheader()
    {
        // To send HTML mail, the Content-type header must be set
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';

        if (count($this->bcc)) {
            $headers[] = 'Bcc: ' . implode(',', $this->bcc);
        }

        if (count($this->cc)) {
            $headers[] = 'Cc: ' . implode(',', $this->cc);
        }

        if (count($this->to)) {
            $emailString ='';
            foreach ($this->to as $name => $email) {
                $emailString .= "$name <$email>, ";
            }
            $headers[] = 'To: ' . rtrim($emailString, ', ');
        }
        return
            implode("\r\n", $this->headers) . "\r\n" . 'From: sender@' . $_ENV['host'] . "\r\n" .
            'Reply-To: sender@' . $_ENV['host'] . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
    }
    public function __construct()
    {
        // To send HTML mail, the Content-type header must be set
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';

        // Additional headers
        $headers[] = 'Bcc: ' . implode(',', $this->bcc);
        $headers[] = 'Cc: ' . implode(',', $this->cc);
    }
    public function send()
    {
        mail(
            implode(',', array_values($this->to)),
            $this->subject,
            $this->message,
            $this->getheader()
        );
    }
}
