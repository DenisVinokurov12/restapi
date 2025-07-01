<?php

namespace App\Config;

enum DocumentType: string
{

    case INCOMING = 'incoming';
    case OUTCOMING = 'outcoming';
    case INVENTORY = 'inventory';

}
