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

## Output Size

The output will change depending on the settings you provide. With a 10gb file, and using default settings, the output will be 124 bytes (characters) wide. 

The output can get quite big depending on filesize and number of rounds specified.

## Options

There are a few options that you can pass to `->create()`:

 * rounds

    This is the number of data pointers to lookup when building the hash. It is expressed as a power-of-2. The minimum is `2` (resulting in using 4 pointers). The arbitrary maximum is 62 (maximum 64 bit signed integer power of 2). The default is 3, using 8 pointers.

    Realistically, values of greater than 10 will be useless as the generated hash size increases drastically (by a factor of 2 each time).

    Increasing this number will provide brute-forcing protection, as it doubles the number of I/O operations required to hash a password. This can provide additional protections if the seed file is leaked to an attacker.

 * pointerSize

    This is the size of the pointer to generate. The default will detect the smallest pointer required to access the entire file (a 255 byte file will use a pointer size of 1, a 10GB file will use 5 bytes).

    You can override the default, but beware that setting too short of a size will raise an error.

 * dataSize

    This is the power-of-2 amount of data to pull from each pointed location. The default is 4 (16 bytes). Increasing this will *not* increase the output size of the result. 

    Increasing this number will provide brute-forcing protection as it increases the amount of I/O bandwidth consumed in a hashing operation.


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

## Optimal Settings

Ideally, you would use as large of a file as possible. 10TB is ideal. You want a file so large that an attacker can't copy it.

Besides making the file large, you can also restrict the bandwidth to the server with the file. This introduces more potential security holes, but it may be a valid option.

If your file is extremely large, the default settings should suffice. If we assume that an attacker cannot download the file, then rounds=3 and dataSize=4 are sufficient.

If however there's a risk of file download, we can increase the two parameters to provide additional brute force protection. 

Increasing rounds will increase the number of I/O operations that an attacker needs to do for each hash. Increasing dataSize will put stress on the I/O throughput the attacker needs for each hash. 

To see how they are related, let's pick rounds=8 and dataSize=13, and look at what happens:

 * The attacker needs to do 256 read operations per hash. On a modern SSD, that will limit them to approximately 400 hashes per second per SSD. This is because the current fastest SSDs can do approximately 100,000 random 4kb block reads per second.

 * The attacker needs to read 8192 bytes for each read operation. Since the 8192 bytes requires 2 block reads, we would expect approximately 200 hashes per second per SSD.

Combining the two, we'd expect an attacker to have approximately 200 hashes per second per SSD. That's quite good. However, if we dropped the dataSize to 12, the attacker can double their hashes per second. As a rule-of-thumb, below data size of 12, the size will have no effect on hash rate. Above 12, it will cause the hash rate to be cut in half for each step up. So we'd expect 14 to be approximately 100 hashes per second.

If an attacker can fit the seed file into main memory, things change a bit. If we only look at bandwidth constraints (and not IOPS constraints), then we'd expect approximately 10gb/s per memory module. Meaning that using our prior settings, we'd expect approximately 1.3 million hashes per second (note this is an upper bound and assumes there is no overhead to random multiple block reads). Severely less than a simple SHA-1, but still too high for comfort. This is why making the file large and protecting it is incredibly important.