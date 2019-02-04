<?php
declare(strict_types=1);

namespace PHPSu\Process;

use PHPSu\Exceptions\CommandExecutionException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class CommandExecutor
{
    /**
     * @param string[] $commands
     * @param OutputInterface $logOutput
     * @param OutputInterface $statusOutput
     * @return void
     */
    public function executeParallel(array $commands, OutputInterface $logOutput, OutputInterface $statusOutput): void
    {
        $manager = new ProcessManager();
        foreach ($commands as $name => $command) {
            $logOutput->writeln(sprintf('<fg=yellow>%s:</> <fg=white;options=bold>running command: %s</>', $name, $command), OutputInterface::VERBOSITY_VERBOSE);
            $process = Process::fromShellCommandline($command, null, null, null, null);
            $process->setName($name);
            $manager->addProcess($process);
        }
        $callback = new StateChangeCallback($statusOutput);
        $manager->addStateChangeCallback($callback);
        $manager->addTickCallback($callback->getTickCallback());
        $manager->addOutputCallback(new OutputCallback($logOutput));
        $manager->mustRun();
    }

    public function passthru(string $command, OutputInterface $output): int
    {
        $process = Process::fromShellCommandline($command, null, null, null, null);
        $process->setTty(true);
        $process->run(function ($type, $buffer) use ($output) {
            if ($type === Process::ERR && $output instanceof ConsoleOutputInterface) {
                $output = $output->getErrorOutput();
            }
            $output->write($buffer);
        });
        return $process->getExitCode();
    }

    public function executeDirectly(string $command): array
    {
        $process = Process::fromShellCommandline($command, null, null, null, null);
        $result = [];
        $process->run(function ($type, $buffer) use (&$result) {
            $result = [$type, $buffer];
        });
        return $result;
    }

    public function getCommandReturnBuffer(array $executedCommandArray, bool $getBufferType = false): string
    {
        if (empty($executedCommandArray)) {
            throw new CommandExecutionException('executed command returned nothing');
        }
        return $executedCommandArray[$getBufferType === false ? 1 : 0];
    }
}
