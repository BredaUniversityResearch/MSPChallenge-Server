<?php

namespace App\ConfigTools;

use App\Command\ConfigListCommand;
use App\Command\ConfigMergeCommand;
use App\Command\ConfigSplitCommand;
use App\Command\ConfigStripCommand;
use App\Command\ConfigValidateCommand;
use App\Command\ConfigVerifyCommand;
use App\Domain\Config\SessionConfigValidator;
use Symfony\Component\Console\Application;

/**
 * The config tools on their own: the config commands of the server without the server. There is no kernel, no
 * database and no container: only the code of src/Domain/Config and the commands, so that it can be packaged (a phar)
 * and used by other programs, such as the config editor, and in the CI of a repository with configs.
 *
 * The commands are the ones of the server (app:config:*), with one difference: the default of --dir is the folder
 * that the tool is run in (the working directory), because there is no ServerManager/configfiles to default to.
 *
 * Start it with bin/config-tools. See docs/config-tools-cli.md for what the commands tell programs.
 */
final class ConfigToolsApplication extends Application
{
    /** Replaced by the version of the release when the tool is built (Box: git-version). */
    private const string VERSION = '@git-version@';

    /**
     * @param ?string $workingDirectory where relative paths are relative to (default: the working directory)
     */
    public function __construct(?string $workingDirectory = null)
    {
        parent::__construct('config-tools', str_starts_with(self::VERSION, '@') ? 'dev' : self::VERSION);
        $workingDirectory ??= getcwd() ?: '.';
        $validator = new SessionConfigValidator(dirname(__DIR__) . '/Domain/SessionConfigJSONSchema.json');
        $this->addCommands([
            new ConfigListCommand($workingDirectory, '.'),
            new ConfigValidateCommand($workingDirectory, $validator, '.'),
            new ConfigMergeCommand($workingDirectory, '.'),
            new ConfigVerifyCommand($workingDirectory, '.'),
            new ConfigStripCommand($workingDirectory, $validator, '.'),
            new ConfigSplitCommand($workingDirectory, $validator, '.'),
        ]);
    }
}
