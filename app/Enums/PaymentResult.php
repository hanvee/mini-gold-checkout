<?php

namespace App\Enums;

enum PaymentResult: string
{
    case Accepted = 'accepted';
    case AlreadyProcessed = 'already_processed';
    case Rejected = 'rejected';
}
