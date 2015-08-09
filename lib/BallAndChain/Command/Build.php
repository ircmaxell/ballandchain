<?php

/*
 * This file is part of BallAndChain for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace BallAndChain\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Build extends Command {

    /**
     * @var Tuli\Rule[]
     */
    protected $rules = [];

    protected function configure() {
        $this->setName('build')
            ->setDescription('Build a seed file')
            ->addArgument('size', InputArgument::REQUIRED, 'The size of file to generate')
            ->addArgument('file', InputArgument::REQUIRED, 'The name of file to generate');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $f = fopen($input->getArgument('file'), 'w+');
        $random = fopen('/dev/urandom', 'r');
        $written = 0;
        $bytes = $this->parseSize($input->getArgument('size'));
        do {
            $output->writeln("$written bytes written");
            $written += stream_copy_to_stream($random, $f, $bytes - $written);
        } while($written < $bytes);
    }

    protected function parseSize($size) {
        if (is_numeric($size)) {
            return (int) $size;
        }
        switch (strtoupper(substr($size, -1))) {
            case 'K':
                return 1024 * $this->parseSize(substr($size, 0, -1));
            case 'M':
                return 1024 * 1024 * $this->parseSize(substr($size, 0, -1));
            case 'G':
                return 1024 * 1024 * 1024 * $this->parseSize(substr($size, 0, -1));
            case 'T':
                return 1024 * 1024 * 1024 * 1024 * $this->parseSize(substr($size, 0, -1));
        }
        throw new \RuntimeException("Unknown size: $size");
    }

}