<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Pure-PHP implementations of Triple DES.
 *
 * Uses mcrypt, if available, and an internal implementation, otherwise.
 *
 * PHP versions 4 and 5
 *
 * Here's a short example of how to use this library:
 * <code>
 * <?php
 *    include('Crypt/TripleDES.php');
 *
 *    $des = new Crypt_TripleDES();
 *
 *    $des->setKey('abcdefghijklmnopqrstuvwx');
 *
 *    $size = 10 * 1024;
 *    $plaintext = '';
 *    for ($i = 0; $i < $size; $i++) {
 *        $plaintext.= 'a';
 *    }
 *
 *    echo $des->decrypt($des->encrypt($plaintext));
 * ?>
 * </code>
 *
 * LICENSE: This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston,
 * MA  02111-1307  USA
 *
 * @category   Crypt
 * @package    Crypt_TripleDES
 * @author     Jim Wigginton <terrafrost@php.net>
 * @copyright  MMVII Jim Wigginton
 * @license    http://www.gnu.org/licenses/lgpl.txt
 * @version    $Id: TripleDES.php,v 1.1 2007-07-02 04:19:47 terrafrost Exp $
 * @link       http://pear.php.net/package/Crypt_TripleDES
 */

/**
 * Include Crypt_DES
 */
require_once 'DES.php';

/**
 * Encrypt / decrypt using a method required by SSHv1
 *
 * mcrypt does a single XOR operation for each block in CBC mode.  SSHv1 requires three XOR operations.
 */
define('CRYPT_DES_MODE_SSH', 3);

/**
 * Pure-PHP implementation of Triple DES.
 *
 * @author  Jim Wigginton <terrafrost@php.net>
 * @version 0.1.0
 * @access  public
 * @package Crypt_DES
 */
class Crypt_TripleDES {
    /**
     * The Three Keys
     *
     * @see Crypt_TripleDES::setKey()
     * @var String
     * @access private
     */
    var $key = "\0\0\0\0\0\0\0\0";

    /**
     * The Encryption Mode
     *
     * @see Crypt_TripleDES::Crypt_TripleDES()
     * @var Integer
     * @access private
     */
    var $mode = CRYPT_DES_MODE_CBC;

    /**
     * Continuous Buffer status
     *
     * @see Crypt_TripleDES::enableContinuousBuffer()
     * @var Boolean
     * @access private
     */
    var $continuousBuffer = false;

    /**
     * Padding status
     *
     * @see Crypt_TripleDES::enablePadding()
     * @var Boolean
     * @access private
     */
    var $padding = true;

    /**
     * The Initialization Vector
     *
     * @see Crypt_TripleDES::setIV()
     * @var Integer
     * @access private
     */
    var $iv = "\0\0\0\0\0\0\0\0";

    /**
     * A "sliding" Initialization Vector
     *
     * @see Crypt_TripleDES::enableContinuousBuffer()
     * @var Integer
     * @access private
     */
    var $encryptIV = "\0\0\0\0\0\0\0\0";

    /**
     * A "sliding" Initialization Vector
     *
     * @see Crypt_TripleDES::enableContinuousBuffer()
     * @var Integer
     * @access private
     */
    var $decryptIV = "\0\0\0\0\0\0\0\0";

    /**
     * MCrypt parameters
     *
     * @see Crypt_TripleDES::setMCrypt()
     * @var Array
     * @access private
     */
    var $mcrypt = array('', '');

    /**
     * The Crypt_DES objects
     *
     * @var Array
     * @access private
     */
    var $des;

    /**
     * Default Constructor.
     *
     * Determines whether or not the mcrypt extension should be used.  $mode should only, at present, be
     * CRYPT_DES_MODE_ECB or CRYPT_DES_MODE_CBC.  If not explictly set, CRYPT_DES_MODE_CBC will be used.
     *
     * @param optional Integer $mode
     * @return Crypt_TripleDES
     * @access public
     */
    function Crypt_TripleDES($mode = CRYPT_DES_MODE_CBC)
    {
        if ( !defined('CRYPT_DES_MODE') ) {
            switch (true) {
                case extension_loaded('mcrypt'):
                    // i'd check to see if des was supported, by doing in_array('des', mcrypt_list_algorithms('')),
                    // but since that can be changed after the object has been created, there doesn't seem to be
                    // a lot of point...
                    define('CRYPT_DES_MODE', CRYPT_DES_MODE_MCRYPT);
                    break;
                default:
                    define('CRYPT_DES_MODE', CRYPT_DES_MODE_INTERNAL);
            }
        }

        if ( $mode == CRYPT_DES_MODE_SSH ) {
            $this->mode = CRYPT_DES_MODE_SSH;
            $this->des = array(
                new Crypt_DES(CRYPT_DES_MODE_CBC),
                new Crypt_DES(CRYPT_DES_MODE_CBC),
                new Crypt_DES(CRYPT_DES_MODE_CBC)
            );
            return;
        }

        switch ( CRYPT_DES_MODE ) {
            case CRYPT_DES_MODE_MCRYPT:
                switch ($mode) {
                    case CRYPT_DES_MODE_ECB:
                        $this->mode = MCRYPT_MODE_ECB;    break;
                    case CRYPT_DES_MODE_CBC:
                    default:
                        $this->mode = MCRYPT_MODE_CBC;
                }

                break;
            default:
                $this->des = array(
                    new Crypt_DES(CRYPT_DES_MODE_ECB),
                    new Crypt_DES(CRYPT_DES_MODE_ECB),
                    new Crypt_DES(CRYPT_DES_MODE_ECB)
                );

                switch ($mode) {
                    case CRYPT_DES_MODE_ECB:
                    case CRYPT_DES_MODE_CBC:
                        $this->mode = $mode;
                        break;
                    default:
                        $this->mode = CRYPT_DES_MODE_CBC;
                }
        }
    }

    /**
     * Sets the key.
     *
     * Keys can be of any length.  Triple DES, itself, can use 128-bit (eg. strlen($key) == 16) or
     * 192-bit (eg. strlen($key) == 24) keys.  This function pads and truncates $key as appropriate.
     *
     * DES also requires that every eighth bit be a parity bit, however, we'll ignore that.
     *
     * If the key is not explicitly set, it'll be assumed to be all zero's.
     *
     * @access public
     * @param String $key
     */
    function setKey($key)
    {
        $length = strlen($key);
        if ($length > 8) {
            $key = str_pad($key, 24, chr(0));
            // if $key is between 64 and 128-bits, use the first 64-bits as the last, per this:
            // http://php.net/function.mcrypt-encrypt#47973
            $key = $length <= 16 ? substr_replace($key, substr($key, 0, 8), 16) : $key;
        }
        $this->key = $key;
        switch (true) {
            case CRYPT_DES_MODE == CRYPT_DES_MODE_INTERNAL:
            case $this->mode == CRYPT_DES_MODE_SSH:
                $this->des[0]->setKey(substr($key,  0, 8));
                $this->des[1]->setKey(substr($key,  8, 8));
                $this->des[2]->setKey(substr($key, 16, 8));

                // we're going to be doing the padding, ourselves, so disable it in the Crypt_DES objects
                $this->des[0]->disablePadding();
                $this->des[1]->disablePadding();
                $this->des[2]->disablePadding();
        }

        if ($this->mode == CRYPT_DES_MODE_SSH) {
            $this->des[0]->enableContinuousBuffer();
            $this->des[1]->enableContinuousBuffer();
            $this->des[2]->enableContinuousBuffer();
        }
    }

    /**
     * Sets the initialization vector. (optional)
     *
     * SetIV is not required when CRYPT_DES_MODE_ECB is being used.  If not explictly set, it'll be assumed
     * to be all zero's.
     *
     * @access public
     * @param String $iv
     */
    function setIV($iv)
    {
        $this->iv = str_pad(substr($key, 0, 8), 8, chr(0));;
    }

    /**
     * Sets MCrypt parameters. (optional)
     *
     * If MCrypt is being used, empty strings will be used, unless otherwise specified.
     *
     * @link http://php.net/function.mcrypt-module-open#function.mcrypt-module-open
     * @access public
     * @param Integer $algorithm_directory
     * @param Integer $mode_directory
     */
    function setMCrypt($algorithm_directory, $mode_directory)
    {
        $this->mcrypt = array($algorithm_directory, $mode_directory);
        if ( $this->mode == CRYPT_DES_MODE_SSH ) {
            $this->des[0]->setMCrypt($algorithm_directory, $mode_directory);
            $this->des[1]->setMCrypt($algorithm_directory, $mode_directory);
            $this->des[2]->setMCrypt($algorithm_directory, $mode_directory);
        }
    }

    /**
     * Encrypts a message.
     *
     * @access public
     * @param String $plaintext
     */
    function encrypt($plaintext)
    {
        // if the key is smaller then 8, do what we'd normally do
        if ($this->mode == CRYPT_DES_MODE_SSH && strlen($this->key) > 8) {
            $ciphertext = $this->des[2]->encrypt($this->des[1]->decrypt($this->des[0]->encrypt($plaintext)));

            return $ciphertext;
        }

        if ($this->padding) {
            $plaintext = $this->_pad($plaintext);
        }

        if ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) {
            $td = mcrypt_module_open(MCRYPT_3DES, $this->mcrypt[0], $this->mode, $this->mcrypt[1]);
            mcrypt_generic_init($td, $this->key, $this->encryptIV);

            $ciphertext = mcrypt_generic($td, $plaintext);

            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);

            if ($this->continuousBuffer) {
                $this->encryptIV = substr($ciphertext, -8);
            }

            return $ciphertext;
        }

        if (strlen($this->key) <= 8) {
            $this->des[0]->mode = $this->mode;

            return $this->des[0]->encrypt($plaintext);
        }

        // we pad with chr(0) since that's what mcrypt_generic does.  to quote from http://php.net/function.mcrypt-generic :
        // "The data is padded with "\0" to make sure the length of the data is n * blocksize."
        $plaintext = str_pad($plaintext, ceil(strlen($plaintext) / 8) * 8, chr(0));

        $ciphertext = '';
        switch ($this->mode) {
            case CRYPT_DES_MODE_ECB:
                for ($i = 0; $i < strlen($plaintext); $i+=8) {
                    $block = substr($plaintext, $i, 8);
                    $block = $this->des[0]->_processBlock($block, CRYPT_DES_ENCRYPT);
                    $block = $this->des[1]->_processBlock($block, CRYPT_DES_DECRYPT);
                    $block = $this->des[2]->_processBlock($block, CRYPT_DES_ENCRYPT);
                    $ciphertext.= $block;
                }
                break;
            case CRYPT_DES_MODE_CBC:
                $xor = $this->encryptIV;
                for ($i = 0; $i < strlen($plaintext); $i+=8) {
                    $block = substr($plaintext, $i, 8) ^ $xor;
                    $block = $this->des[0]->_processBlock($block, CRYPT_DES_ENCRYPT);
                    $block = $this->des[1]->_processBlock($block, CRYPT_DES_DECRYPT);
                    $block = $this->des[2]->_processBlock($block, CRYPT_DES_ENCRYPT);
                    $xor = $block;
                    $ciphertext.= $block;
                }
                if ($this->continuousBuffer) {
                    $this->encryptIV = $xor;
                }
        }

        return $ciphertext;
    }

    /**
     * Decrypts a message.
     *
     * @access public
     * @param String $ciphertext
     */
    function decrypt($ciphertext)
    {
        if ($this->mode == CRYPT_DES_MODE_SSH && strlen($this->key) > 8) {
            $plaintext = $this->des[0]->decrypt($this->des[1]->encrypt($this->des[2]->decrypt($ciphertext)));

            return $plaintext;
        }

        // we pad with chr(0) since that's what mcrypt_generic does.  to quote from http://php.net/function.mcrypt-generic :
        // "The data is padded with "\0" to make sure the length of the data is n * blocksize."
        $ciphertext = str_pad($ciphertext, (strlen($ciphertext) + 7) & 0xFFFFFFF8, chr(0));

        if ( CRYPT_DES_MODE == CRYPT_DES_MODE_MCRYPT ) {
            $td = mcrypt_module_open(MCRYPT_3DES, $this->mcrypt[0], $this->mode, $this->mcrypt[1]);
            mcrypt_generic_init($td, $this->key, $this->decryptIV);

            $plaintext = mdecrypt_generic($td, $ciphertext);

            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);

            if ($this->continuousBuffer) {
                $this->decryptIV = substr($ciphertext, -8);
            }

            return $this->_unpad($plaintext);
        }

        if (strlen($this->key) <= 8) {
            $this->des[0]->mode = $this->mode;

            return $this->des[0]->decrypt($plaintext);
        }

        $plaintext = '';
        switch ($this->mode) {
            case CRYPT_DES_MODE_ECB:
                for ($i = 0; $i < strlen($ciphertext); $i+=8) {
                    $block = substr($ciphertext, $i, 8);
                    $block = $this->des[0]->_processBlock($block, CRYPT_DES_DECRYPT);
                    $block = $this->des[1]->_processBlock($block, CRYPT_DES_ENCRYPT);
                    $block = $this->des[2]->_processBlock($block, CRYPT_DES_DECRYPT);
                    $plaintext.= $block;
                }
                break;
            case CRYPT_DES_MODE_CBC:
                $xor = $this->decryptIV;
                for ($i = 0; $i < strlen($ciphertext); $i+=8) {
                    $orig = $block = substr($ciphertext, $i, 8);
                    $block = $this->des[0]->_processBlock($block, CRYPT_DES_DECRYPT);
                    $block = $this->des[1]->_processBlock($block, CRYPT_DES_ENCRYPT);
                    $block = $this->des[2]->_processBlock($block, CRYPT_DES_DECRYPT);
                    $plaintext.= $block ^ $xor;
                    $xor = $orig;
                }
                if ($this->continuousBuffer) {
                    $this->decryptIV = $xor;
                }
        }

        return $this->_unpad($plaintext);
    }

    /**
     * Treat consecutive "packets" as if they are a continuous buffer.
     *
     * Say you have a 16-byte plaintext $plaintext.  Using the default behavior, the two following code snippets
     * will yield different outputs:
     *
     * <code>
     *    echo $des->encrypt(substr($plaintext, 0, 8));
     *    echo $des->encrypt(substr($plaintext, 8, 8));
     * </code>
     * <code>
     *    echo $des->encrypt($plaintext);
     * </code>
     *
     * The solution is to enable the continuous buffer.  Although this will resolve the above discrepancy, it creates
     * another, as demonstrated with the following:
     *
     * <code>
     *    $des->encrypt(substr($plaintext, 0, 8));
     *    echo $des->decrypt($des->encrypt(substr($plaintext, 8, 8)));
     * </code>
     * <code>
     *    echo $des->decrypt($des->encrypt(substr($plaintext, 8, 8)));
     * </code>
     *
     * With the continuous buffer disabled, these would yield the same output.  With it enabled, they yield different
     * outputs.  The reason is due to the fact that the initialization vector's change after every encryption /
     * decryption round when the continuous buffer is enabled.  When it's disabled, they remain constant.
     *
     * Put another way, when the continuous buffer is enabled, the state of the Crypt_DES() object changes after each
     * encryption / decryption round, whereas otherwise, it'd remain constant.  For this reason, it's recommended that
     * continuous buffers not be used.  They do offer better security and are, in fact, sometimes required (SSH uses them),
     * however, they are also less intuitive and more likely to cause you problems.
     *
     * @see Crypt_TripleDES::disableContinuousBuffer()
     * @access public
     */
    function enableContinuousBuffer()
    {
        $this->continuousBuffer = true;
    }

    /**
     * Treat consecutive packets as if they are a discontinuous buffer.
     *
     * The default behavior.
     *
     * @see Crypt_TripleDES::enableContinuousBuffer()
     * @access public
     */
    function disableContinuousBuffer()
    {
        $this->continuousBuffer = false;
        $this->encryptIV = $this->iv;
        $this->decryptIV = $this->iv;
    }

    /**
     * Pad "packets".
     *
     * DES works by encrypting eight bytes at a time.  If you ever need to encrypt or decrypt something that's not
     * a multiple of eight, it becomes necessary to pad the input so that it's length is a multiple of eight.
     *
     * Padding is enabled by default.  Sometimes, however, it is undesirable to pad strings.  Such is the case in SSH1,
     * where "packets" are padded with random bytes before being encrypted.  Unpad these packets and you risk stripping
     * away characters that shouldn't be stripped away. (SSH knows how many bytes are added because the length is
     * transmitted separately)
     *
     * @see Crypt_TripleDES::disablePadding()
     * @access public
     */
    function enablePadding()
    {
        $this->padding = true;
    }

    /**
     * Do not pad packets.
     *
     * @see Crypt_TripleDES::enablePadding()
     * @access public
     */
    function disablePadding()
    {
        $this->padding = false;
    }

    /**
     * Pads a string
     *
     * Pads a string using the RSA PKCS padding standards so that its length is a multiple of the blocksize (8).
     * 8 - (strlen($text) & 7) bytes are added, each of which is equal to chr(8 - (strlen($text) & 7)
     *
     * @see Crypt_TripleDES::_unpad()
     * @access private
     */
    function _pad($text)
    {
        if (!$this->padding) {
            return $text;
        }

        $length = 8 - (strlen($text) & 7);
        return str_pad($text, strlen($text) + $length, chr($length));
    }

    /**
     * Unpads a string
     *
     * @see Crypt_TripleDES::_pad()
     * @access private
     */
    function _unpad($text)
    {
        if (!$this->padding) {
            return $text;
        }

        $length = ord($text{strlen($text) - 1});
        return substr($text, 0, -$length);
    }
}

// vim: ts=4:sw=4:et:
// vim6: fdl=1:
?>