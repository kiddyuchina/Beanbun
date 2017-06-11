<?php
namespace Beanbun\Lib;

use InvalidArgumentException;
use SplFixedArray;

class BloomFilter
{
    protected $bitField = '';
    protected $m;
    protected $k;

    /**
     * @param int $m Size of the bit field. Actual memory used will be $m/8 bytes.
     * @param int $k Number of hash functions
     */
    public function __construct($m, $k)
    {
        if (!is_numeric($m) || !is_numeric($k)) {
            throw new InvalidArgumentException('$m and $k must be integers');
        }
        $this->bitField = $this->initializeBitFieldOfLength($m);
        $this->m = (int) $m;
        $this->k = (int) $k;
    }

    /**
     * Calculates the optimal number of k given m and a
     * typical number of items to be stored.
     *
     * @param int $m Size of the bit field
     * @param int $n Typical number of items to insert
     * @return int Optimal number for k
     */
    public static function getK($m, $n)
    {
        return ceil(($m / $n) * log(2));
    }

    /**
     * Returns an instance based on the bit field size and expected number of stored items.
     * Automates the calculation of k.
     *
     * @param int $m Bit field size
     * @param int $n Expected number of stored values
     * @return BloomFilter
     */
    public static function constructForTypicalSize($m, $n)
    {
        return new self($m, self::getK($m, $n));
    }

    /**
     * Unserializes in instance from an ASCII safe string representation produced by __toString.
     *
     * @param string $string String representation
     * @return BloomFilter Unserialized instance
     */
    public static function unserializeFromStringRepresentation($string)
    {
        if (!preg_match('~k:(?P<k>\d+)/m:(?P<m>\d+)\((?P<bitfield>[0-9a-zA-Z+/=]+)\)~', $string, $matches)) {
            throw new InvalidArgumentException('Invalid string representation');
        }
        $bf = new self((int) $matches['m'], (int) $matches['k']);
        $bf->bitField = base64_decode($matches['bitfield']);
        return $bf;
    }
    protected function initializeBitFieldOfLength($length)
    {
        return str_repeat("\x00", ceil($length / 8));
    }

    protected function setBitAtPosition($pos)
    {
        list($char, $byte) = $this->position2CharAndByte($pos);
        $this->bitField[$char] = $this->bitField[$char] | $byte;
    }

    protected function getBitAtPosition($pos)
    {
        list($char, $byte) = $this->position2CharAndByte($pos);
        return ($this->bitField[$char] & $byte) === $byte;
    }

    /**
     * Returns a tuple with the char offset into the bitfield string
     * in index 0 and a bitmask for the specific position in index 1.
     * E.g.: Position 9 -> (1, "10000000") (2nd byte, "first" bit)
     *
     * @param int $pos The $pos'th bit in the bit field.
     * @return array array(int $charOffset, string $bitmask)
     */
    protected function position2CharAndByte($pos)
    {
        if ($pos > $this->m) {
            throw new InvalidArgumentException("\$pos of $pos beyond bitfield length of $this->m");
        }
        static $positionMap = array(
            8 => "\x01",
            7 => "\x02",
            6 => "\x04",
            5 => "\x08",
            4 => "\x10",
            3 => "\x20",
            2 => "\x40",
            1 => "\x80",
        );

        $char = (int) ceil($pos / 8) - 1;
        $byte = $positionMap[$pos % 8 ?: 8];
        return array($char, $byte);
    }

    /**
     * Calculates the positions a value hashes to in the bitfield.
     *
     * @param string $value The value to insert into the bitfield.
     * @return SplFixedArray Array containing the numeric positions in the bitfield.
     */
    protected function positions($value)
    {
        mt_srand(crc32($value));
        $positions = new SplFixedArray($this->k);
        for ($i = 0; $i < $this->k; $i++) {
            $positions[$i] = mt_rand(1, $this->m);
        }
        return $positions;
    }

    /**
     * Add a value into the set.
     *
     * @param string $value
     */
    public function add($value)
    {
        foreach ($this->positions($value) as $position) {
            $this->setBitAtPosition($position);
        }
    }

    /**
     * Checks if the value may have been added to the set before.
     * False positives are possible, false negatives are not.
     *
     * @param string $value
     * @return boolean
     */
    public function maybeInSet($value)
    {
        foreach ($this->positions($value) as $position) {
            if (!$this->getBitAtPosition($position)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns an ASCII representation of the current bit field.
     *
     * @return string
     */
    public function showBitField()
    {
        return join(array_map(function ($chr) {return str_pad(base_convert(bin2hex($chr), 16, 2), 8, '0', STR_PAD_LEFT);}, str_split($this->bitField)));
    }

    /**
     * Returns an ASCII safe representation of the BloomFilter object.
     * This representation can be unserialized using unserializeFromStringRepresentation().
     *
     * @return string
     */
    public function __toString()
    {
        return "k:$this->k/m:$this->m(" . base64_encode($this->bitField) . ')';
    }

}
