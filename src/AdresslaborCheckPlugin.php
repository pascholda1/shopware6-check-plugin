<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin;

use Shopware\Core\Framework\Plugin;

class AdresslaborCheckPlugin extends Plugin
{
    public function executeComposerCommands(): bool
    {
        return true;
    }
    
}
