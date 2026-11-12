<?php

namespace App\Command;

use App\Domain\Config\Merge\ConfigNormalizer;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'app:config:normalize',
    description: 'Writes every config below --dir to --output-dir in one fixed form, with the same names and folders: '
        . 'in the current shape (simulation_settings), without the fields that the split removes, and with the keys in '
        . 'the order of a final config. A stripped config is merged with its parents first. For comparing as text (the '
        . 'originals with the merged configs), and as the input of a split. Reports only, unless --output-dir is given.'
)]
final class ConfigNormalizeCommand extends ConfigMergeAllCommand
{
    protected function finalize(\stdClass $merged): \stdClass
    {
        return new ConfigNormalizer()->normalize($merged);
    }
}
