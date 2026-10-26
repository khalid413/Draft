<?php

namespace App\Enums;

enum GamePhase: string
{
    case Setup = 'setup';
    case PassDevice = 'pass_device';
    case Picking = 'picking';
    case Revealing = 'revealing';
    case Finished = 'finished';
}
