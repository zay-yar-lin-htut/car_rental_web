<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class PasswordReset extends Mailable
{
    public $user_name;  
    public $password;
    public function __construct($name, $password)
    {
        $this->user_name = $name;
        $this->password = $password;
    }

    public function build()
    {
        return $this->from(config('mail.from.address'))
            ->subject('Your account has been created successfully!')
            ->view('emails.password_reset')
            ->with([
                'user_name' => $this->user_name,
                'password' => $this->password,
                'date' => now('Y'),
            ]);
    }
}
