<?php

declare(strict_types=1);

namespace M7md5ttab\LaravelShared\Enums;

enum HostingProvider: string
{
    case Cpanel = 'cpanel';
    case Hostinger = 'hostinger';
    case Namecheap = 'namecheap';
    case GoDaddy = 'godaddy';
    case DirectAdmin = 'directadmin';
    case Generic = 'generic';

    public function label(): string
    {
        return match ($this) {
            self::Cpanel => 'cPanel',
            self::Hostinger => 'Hostinger',
            self::Namecheap => 'Namecheap',
            self::GoDaddy => 'GoDaddy',
            self::DirectAdmin => 'DirectAdmin',
            self::Generic => 'Generic shared hosting',
        };
    }
}
