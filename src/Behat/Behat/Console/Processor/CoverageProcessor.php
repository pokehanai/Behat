<?php

namespace Behat\Behat\Console\Processor;

use Symfony\Component\DependencyInjection\ContainerInterface,
    Symfony\Component\Console\Command\Command,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Output\OutputInterface;

use Behat\Behat\Event\SuiteEvent,
    Behat\Behat\Event\FeatureEvent;

use PHP_CodeCoverage,
    PHP_CodeCoverage_Report_Clover,
    PHP_CodeCoverage_Report_HTML,
    PHP_CodeCoverage_Report_PHP,
    PHP_CodeCoverage_Report_Text;

use PHPUnit_Util_Printer;

/**
 * PHP_CodeCoverage
 *
 * Copyright (c) 2009-2011, Sebastian Bergmann <sb@sebastian-bergmann.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   PHP
 * @package    CodeCoverage
 * @author     Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright  2009-2011 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link       http://github.com/sebastianbergmann/php-code-coverage
 * @since      File available since Release 1.0.0
 */

/**
 * Coverage processor.
 *
 * @author      HANAI Tohru <hanai@pokelabo.co.jp>
 */
class CoverageProcessor implements ProcessorInterface
{
    /**
     * @see     Behat\Behat\Console\Configuration\ProcessorInterface::configure()
     */
    public function configure(Command $command)
    {
        $command
            ->addOption('--coverage-clover', null, InputOption::VALUE_REQUIRED,
                        "Write coverage report to file in Clover XML format."
            )
            ->addOption('--coverage-html', null, InputOption::VALUE_REQUIRED,
                        "Write coverage report to directory in HTML format."
            )
            ->addOption('--coverage-php', null, InputOption::VALUE_REQUIRED,
                        "Serialize PHP_CodeCoverage to file."
            )
            ->addOption('--coverage-text', null, InputOption::VALUE_OPTIONAL,
                        "Write coverage report to file in text format.\n" .
                        "Default to writing to the standard output."
            )
        ;
    }

    /**
     * @see     Behat\Behat\Console\Configuration\ProcessorInterface::process()
     */
    public function process(ContainerInterface $container, InputInterface $input, OutputInterface $output)
    {
        $coverage_writers = array();
        
        if ($path = $input->getOption('coverage-clover')) {
            $coverage_writers[] = array('path' => $path,
                                        'class' => 'PHP_CodeCoverage_Report_Clover');
        }
        if ($path = $input->getOption('coverage-html')) {
            $coverage_writers[] = array('path' => $path,
                                        'class' => 'PHP_CodeCoverage_Report_HTML');
        }
        if ($path = $input->getOption('coverage-php')) {
            $coverage_writers[] = array('path' => $path,
                                        'class' => 'PHP_CodeCoverage_Report_PHP');
        }
        if ($path = $input->getOption('coverage-text')) {
            $coverage_writers[] = array('path' => $path,
                                        'class' => 'PHP_CodeCoverage_Report_Text');
        }

        if (!$coverage_writers) {
            return;
        }
        
        require_once 'PHP/CodeCoverage/Autoload.php';
        $coverage = new PHP_CodeCoverage();
        $this->setupCoverage($container, $coverage);
        $this->installCoverage($container, $output, $coverage, $coverage_writers);
    }

    /**
     * Set up PHP_CodeCoverage instance.
     * @param Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param PHP_CodeCoverage $coverage 
     */
    protected function setupCoverage(ContainerInterface $container, PHP_CodeCoverage $coverage)
    {
        if ($container->hasParameter('behat.coverage.whitelist')) {
            $whitelist = $container->getParameter('behat.coverage.whitelist');
            foreach ((array)$whitelist['dir'] as $dir) {
                $coverage->filter()->addDirectoryToWhitelist($dir);
            }
            if (count($whitelist['file'])) {
                $coverage->filter()->addFilesToWhitelist($whitelist['file']);
            }
        }
        
        if ($container->hasParameter('behat.coverage.blacklist')) {
            $blacklist = $container->getParameter('behat.coverage.blacklist');
            foreach ((array)$blacklist['dir'] as $dir) {
                $coverage->filter()->addDirectoryToBlacklist($dir);
            }
            if (count($blacklist['file'])) {
                $coverage->filter()->addFilesToBlacklist($blacklist['file']);
            }
        }
    }
    
    /**
     * Install coverage process into the test suit.
     * @param Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param PHP_CodeCoverage $coverage 
     */
    protected function installCoverage(ContainerInterface $container, OutputInterface $output,
                                       PHP_CodeCoverage $coverage, array $coverage_writers)
    {
        $event_dispatcher = $container->get('behat.event_dispatcher');
        $event_dispatcher->addListener('beforeFeature', function(FeatureEvent $event) use($coverage) {
                $coverage->start($event->getFeature()->getTitle());
            });

        $event_dispatcher->addListener('afterFeature', function(FeatureEvent $event) use($coverage) {
                $coverage->stop();
            });

        $event_dispatcher->addListener('afterSuite', function(SuiteEvent $event) use($coverage_writers, $coverage, $output) {
                foreach ($coverage_writers as $entry) {
                    $output->writeln("generating {$entry['path']}...");
                    switch ($entry['class']) {
                    case 'PHP_CodeCoverage_Report_Text':
                        if (!class_exists('\PHPUnit_Util_Printer')) {
                            require_once 'PHPUnit/Util/Printer';
                        }
                        $path = preg_match('/^([-+]?|stdout)$/i', $entry['path']) ? null : $entry['path'];
                        $printer = new PHPUnit_Util_Printer($path);
                        $writer = new PHP_CodeCoverage_Report_Text($printer, '', 35, 70, false);
                        $writer->process($coverage, true);
                        break;
                    default:
                        $writer = new $entry['class'];
                        $writer->process($coverage, $entry['path']);
                        break;
                    }
                }
            });
    }
}
