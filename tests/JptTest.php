<?php

namespace Petalbranch\Jpt;

use Petalbranch\Jpt\Crypto\HmacSigner;
use Petalbranch\PetalCipher\PetalCipher;
use PHPUnit\Framework\TestCase;

class JptTest extends TestCase
{

    public function testEncode()
    {
        $signer = new HmacSigner();
        $pc = new PetalCipher("123456");
        $jpt = new Jpt($signer, $pc, "123456");
        $jpt->encode(['a' => 1, 'b' => 2], ['c' => 3]);

        echo $jpt->encode(['a' => 1, 'b' => 2], ['c' => 3]);
    }

    public function testVerify()
    {

    }

    public function testDecode()
    {
        $signer = new HmacSigner();
        $pc = new PetalCipher("123456");
        $jpt = new Jpt($signer, $pc, "123456");
        $arr = $jpt->decode('eyJ0eXAiOiJKUFQiLCJhbGciOiJIUzI1NiIsImEiOjEsImIiOjJ9.lAzgKXYrQvU91hL9I9f84iaPmDLFmdtA-dbT-Kt5-K4imKaTIntimYtimdbSmitF-K-Q4szK4Nvd4saTInU84sgNbD0K-K38ID4FmK45f9S94Af7b1F.G089SQCqObroQe-EMtcNX6KYXLFTy_Fq3qzfbKvny_8');
        print_r($arr);
    }
}
