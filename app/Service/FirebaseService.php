<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;


class FirebaseService
{

    private $messaging;


    public function __construct()
    {

        $factory = (new Factory)
            ->withServiceAccount(
                base_path('firebase.json')
            );


        $this->messaging = $factory->createMessaging();

    }



    public function sendNotification(
        $token,
        $title,
        $body
    )
    {


        $notification = Notification::create(
            $title,
            $body
        );


        $message = CloudMessage::withTarget(
            'token',
            $token
        )
        ->withNotification(
            $notification
        );


        return $this->messaging->send($message);

    }


}