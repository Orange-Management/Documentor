<?php

namespace Documentor\src\Application;

use Documentor\src\Application\Controllers\CodeCoverageController;
use Documentor\src\Application\Controllers\DocumentationController;
use Documentor\src\Application\Controllers\GuideController;
use Documentor\src\Application\Controllers\MainController;
use Documentor\src\Application\Controllers\UnitTestController;

class Application
{
    private ?DocumentationController $docController         = null;
    private ?CodeCoverageController $codeCoverageController = null;
    private ?UnitTestController $unitTestController         = null;
    private ?GuideController $guideController               = null;
    private ?MainController $mainController                 = null;

    public function __construct(array $argv)
    {
        $this->setupHandlers();

        $help        = ($key = \array_search('-h', $argv)) === false || $key === \count($argv) - 1 ? null : \trim($argv[$key + 1], '" ');
        $source      = ($key = \array_search('-s', $argv)) === false || $key === \count($argv) - 1 ? null : \trim($argv[$key + 1], '" ');
        $destination = ($key = \array_search('-d', $argv)) === false || $key === \count($argv) - 1 ? null : \trim($argv[$key + 1], '" ');

        if (isset($help) || !isset($source) || !isset($destination)) {
            $this->printUsage();
        } else {
            $destination = \rtrim($destination, '/\\');
            $source      = \rtrim($source, '/\\');
            $this->createDocumentation($source, $destination, $argv);
        }
    }

    private function setupHandlers()
    {
        \set_exception_handler(function(\Throwable $e) { 
            echo $e->getLine(), ': ' , $e->getMessage(); 
        });

        \set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline) { 
            if (!(\error_reporting() & $errno)) {
                echo $errline , ': ' , $errfile;
            } 
        });

        \register_shutdown_function(function() { 
            $e = \error_get_last(); 
            
            if (isset($e)) {
                echo $e['line'] , ': ' , $e['message']; 
            }
        });

        \mb_internal_encoding('UTF-8');
    }

    private function createDocumentation(string $source, string $destination, array $argv)
    {
        $unitTest     = ($key = \array_search('-u', $argv)) === false || $key === \count($argv) - 1 ? null : \trim($argv[$key + 1], '" ');
        $codeCoverage = ($key = \array_search('-c', $argv)) === false || $key === \count($argv) - 1 ? null : \trim($argv[$key + 1], '" ');
        $guide        = ($key = \array_search('-g', $argv)) === false || $key === \count($argv) - 1 ? null : \trim($argv[$key + 1], '" ');
        $base         = ($key = \array_search('-b', $argv)) === false || $key === \count($argv) - 1 ? $destination : \trim($argv[$key + 1], '" ');
        $base         = rtrim($base, '/\\');
        $sources      = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source));

        $this->mainController         = new MainController($destination, $base);
        $this->codeCoverageController = new CodeCoverageController($destination, $base, $codeCoverage);
        $this->unitTestController     = new UnitTestController($destination, $base, $unitTest);
        $this->docController          = new DocumentationController($destination, $base, $source, $this->codeCoverageController, $this->unitTestController);
        $this->guideController        = new GuideController($destination, $base, $guide);

        $this->parse($sources);
        $this->docController->createTableOfContents();
        $this->docController->createSearchSet();
    }

    private function printUsage()
    {
        echo 'Usage: -s <SOURCE_PATH> -d <DESTINATION_PATH> -c <COVERAGE_PATH>' . "\n\n";
        echo "\t" . '-s Source path of the code to create the documentation from.' . "\n";
        echo "\t" . '-d Destination of the finished documentation.' . "\n";
        echo "\t" . '-c Code coverage xml log generated by `coverage-clover` in PHPUnit.' . "\n";
        echo "\t" . '-u Unit test log generated by `junit` in PHPUnit.' . "\n";
        echo "\t" . '-g Directory containing the html guide.' . "\n";
        echo "\t" . '-b Base uri for web access (e.g. http://www.yoururl.com).' . "\n";
    }

    private function parse(\RecursiveIteratorIterator $sources)
    {
        foreach ($sources as $source) {
            if ($source->isFile() 
                && (($temp = \strlen($source->getPathname()) - strlen('.php')) >= 0 && \strpos($source->getPathname(), '.php', $temp) !== false)
                && (\stripos($source->getPathname(), '/test') === false && \stripos($source->getPathname(), '\\test') === false)
            ) {
                $this->docController->parse($source);
            }
        }
    }
}
