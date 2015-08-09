BallAndChain
============

This is a PHP implementation of [BallAndChain](https://www.youtube.com/watch?v=GfyM8lFkjo8).

The only difference in algorithms are as follows:

 1. This uses SHA-2 instead of SHA-3 due to availability in PHP
 2. This uses a combined hash format that encodes the settings with a version identifier

## Generating A Seed File

First, you need to generate a "seed file". This is a large random file that is the basis for security. 

Ideally you want to make this file as large as you possibly can (10gb should be the bare minimum). If you can hit 1TB that's better. 10TB is even better!

There are three options to generating this file. You can do it yourself with `dd`:

    # Our target is 10 * 2**30 or 10GB. For 100GB change to 100 * 2**30
    $ dd if=/dev/urandom of=/path/to/file bs=$((64 * 2**20)) count=$(((10 * 2**30) / (64 * 2**20)))

You can use openssl

    $ openssl rand -out /path/to/file $((10 * 2**30))

Or you can use the ballandchain tool to generate (the slowest method)

    $ bin/ballandchain build 10G /path/to/file

## Usage

To use, create a new `Hash` instance by passing the file to the constructor:

    $hash = new BallAndChain\Hash('/path/to/file');

Then, to hash a new password:

    $hashed = $hash->create($password);

And finally, to validate:

    if ($hash->verify($password, $hashed)) {
        // Success!
    }

It's that simple!

With the default settings, the resulting hash is 176 bytes wide.

## Options

There are a few options that you can pass to `->create()`:

 * rounds

    This is the number of data pointers to lookup when building the hash. The default is 10, and the minimum you should use is 4. More rounds increases the output size, but increases overall security.

 * pointerSize

    This is the size of the pointer to generate. The default is 8 bytes. You shouldn't have to change this. If you do, it will affect the output size of the resultant hash.

 * dataSize

    This is the amount of data to pull from each pointed location. The default is 16 bytes. Increasing this will *not* increase the output size of the result. You shouldn't have to change this either.

## How this works

 * The password is hashed using SHA-256 to create a cipher key.
 * Each round generates a random pointer
 * Each random pointer is looked up in the file and read into a data array.
 * All of the collected data points are hashed using SHA-256
 * The pointers and the hashed data points are encrypted using the cipher key.

In pseudo-code:

    function hash(password) {
        key = sha256(password)
        pointers = ''
        data = ''
        for (round in rounds) {
            pointer = random_pointer()
            pointers = pointers + pointer
            data = data + read(pointer)
        }
        iv = random_iv()
        return encrypt(key, iv, pointers + sha256(data))
    }

To validate a password, you decrypt the ciphertext to get the list of pointers. Then you lookup the pointers to rebuild the data array, and finally verify the hash of data matches the encrypted hash of data.

Watch the video linked above. It's worth it.