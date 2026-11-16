<?php

namespace App\Domain\Config\Merge;

/**
 * Normalizing a config changed something that is not one of the documented changes (see ConfigNormalizationAudit).
 */
final class NormalizationAuditException extends \RuntimeException
{
    /**
     * @param string[] $problems what is different, one line each
     */
    public function __construct(public readonly array $problems)
    {
        $shown = implode('; ', array_slice($problems, 0, 3));
        $more = count($problems) > 3 ? sprintf(' (and %d more)', count($problems) - 3) : '';
        parent::__construct(
            'Normalizing changed something that is not on the list of documented changes, so nothing is written: '
            . $shown . $more
        );
    }
}
