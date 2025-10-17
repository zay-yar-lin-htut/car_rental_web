<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class Welcome extends Mailable
{
    public $user_name;  
    public function __construct($data)
    {
        $this->user_name = $data['name'];
    }

    public function build()
    {
        return $this->from(config('mail.from.address'))
            ->subject('Your account has been created successfully!')
            ->view('emails.user_welcome')
            ->with([
                'user_name' => $this->user_name,
                'date' => now('Y'),
            ]);
    }
}
