<?php declare(strict_types=1);

namespace Adresslabor\CheckPlugin\Core\Checkout\Cart\Validation\Error;

class InvalidEmailAddressCartError extends AbstractInvalidAddressCartError
{
    protected const KEY = 'adresslabor-email-address-blocked';
    
    protected const CHECK_RESULT_PARTIAL_KEY_MAP = [
        'E-Mail ist ungültig' => 'invalid',
        'E-Mail ist zu lang' => 'invalid-length',
        'E-Mail hat kein @-Zeichen' => 'missing-at',
        'Ungültiges Zeichen im lokalen Teil' => 'invalid-character',
        'E-Mail hat keinen lokalen Teil' => 'missing-local-part',
        'E-Mail hat keinen Domain-Teil' => 'missing-domain',
        'E-Mail beginnt oder endet mit einem Punkt oder hat 2 Punkte hintereinander' => 'invalid-dots',
        'E-Mail beinhaltet ungültige Zeichen in einem Kommentar' => 'invalid-character-in-comment',
        'E-Mail beinhaltet ein Zeichen, das escaped werden muss, aber nicht ist' => 'unescaped-character',
        'Der lokale Teil der E-Mail ist zu lang' => 'invalid-local-part-character-count',
        'Die numerische Adresse hat ein falsches Präfix' => 'invalid-prefix',
        'Der Domain-Teil enhält ein leeres Element' => 'invalid-domain-empty-part',
        'Der Domain-Teil enhält ein Element, das zu lang ist' => 'invalid-domain-character-count-part',
        'Der Domain-Teil enhält ein ungültiges Zeichen' => 'invalid-domain-invalid-character',
        'Der Domain-Teil ist zu lang' => 'invalid-domain-character-length',
        'Die IPv6-Adresse enthält zu viele Gruppen' => 'invalid-ip6-group-count',
        'Die IPv6-Adresse enthält die falsche Anzahl von Gruppen' => 'invalid-ip6-group-count',
        'Die IPv6-Adresse enthält eine falsche Gruppe von Zeichen' => 'invalid-ip6-character-count',
        'Die IPv6-Adresse enthält zuviele :: Sequenzen' => 'invalid-ip6-sequence-count',
        'Die IPv6-Adresse beginnt mit einem einzelnen Punkt' => 'invalid-ip6-starts-with-dot',
        'Die IPv6-Adresse endet mit einem einzelnen Punkt' => 'invalid-ip6-ends-with-dot',
        'Top-Level-Domain ist ungültig' => 'invalid-domain',
        'Domain weist auf Wegwerf-Email-Adresse hin' => 'invalid-disposable-mail'
    ];
    
    
}
