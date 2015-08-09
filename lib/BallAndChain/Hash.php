<?php

/*
 * This file is part of BallAndChain for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace BallAndChain;

class Hash {
    const HASH_PRIMITIVE = 'sha256';
    const HASH_LENGTH = 32;
    const CIPHER_PRIMITIVE = 'aes-256-ctr';
    const IV_LENGTH = 16;
    const HEADER_SIZE = 4;
    protected $file;
    protected $fileSize = 0;


    public function __construct($fileName) {
        $this->file = fopen($fileName, 'r');
        if (!$this->file) {
            throw new \RuntimeException("Invalid seed file");
        }
        $this->fileSize = (int) fstat($this->file)['size'];
    }

    public function create($password, $rounds = 10, $pointerSize = 8, $dataSize = 16) {
        $key = hash(self::HASH_PRIMITIVE, $password, true);
        $data = '';
        $pointers = '';
        for ($i = 0; $i < $rounds; $i++) {
            $pointers .= $pointer = random_bytes($pointerSize);
            $data .= $this->read($pointer, $dataSize);
        }
        $result = $pointers . hash(self::HASH_PRIMITIVE, $data, true);
        $iv = random_bytes(self::IV_LENGTH);
        $header = pack('CCCC', 1, $rounds, $pointerSize, $dataSize);
        return base64_encode($header . $iv . openssl_encrypt($result, self::CIPHER_PRIMITIVE, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv));
    }

    public function verify($password, $hash) {
        $key = hash(self::HASH_PRIMITIVE, $password, true);
        $hash = base64_decode($hash);
        $header = substr($hash, 0, self::HEADER_SIZE);
        $iv = substr($hash, self::HEADER_SIZE, self::IV_LENGTH);
        $ciphertext = substr($hash, self::HEADER_SIZE + self::IV_LENGTH);
        $decrypted = openssl_decrypt($ciphertext, self::CIPHER_PRIMITIVE, $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        $data = '';
        list (, $version, $rounds, $pointerSize, $dataSize) = unpack('C*', $header);
        if ($version !== 1) {
            throw new \RuntimeException("Unknown version encountered");
        }
        if (strlen($decrypted) !== self::HASH_LENGTH + $rounds * $pointerSize) {
            throw new \RuntimeException("Invalid data payload, was it truncated?");
        }

        for ($i = 0; $i < $rounds; $i++) {
            $pointer = substr($decrypted, $i * $pointerSize, $pointerSize);
            $data .= $this->read($pointer, $dataSize);
        }
        $test = hash(self::HASH_PRIMITIVE, $data, true);
        return hash_equals($test, substr($decrypted, $rounds * $pointerSize));
    }

    protected function read($pointer, $dataSize) {
        $numeric = abs(unpack('q', $pointer)[1]);
        $offset = $numeric % $this->fileSize;
        fseek($this->file, $offset);
        $data = fread($this->file, $dataSize);
        if ($offset + $dataSize > $this->fileSize) {
            // wrap the read
            fseek($this->file, 0);
            $data .= fread($this->file, $dataSize - ($this->fileSize - $offset));
        }
        if (strlen($data) !== $dataSize) {
            throw new \RuntimeException("Invalid data read, couldn't fill data block");
        }
        return $data;
    }
}