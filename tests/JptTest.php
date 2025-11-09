<?php


use Petalbranch\Jpt\Jpt;
use PHPUnit\Framework\TestCase;

class JptTest extends TestCase
{

    public function testGetOption()
    {
        $jpt = new Jpt();
        $this->assertTrue($jpt->getOption('aud') === '*');
        $jpt->setOption('aud', 'test');
        $this->assertTrue($jpt->getOption('aud') === 'test');
        $jpt->setOption('aud', null);
        $this->assertTrue($jpt->getOption('aud') === null);
    }

    public function testSetOptSub()
    {
        $jpt = new Jpt();
        $jpt->setOptSub('test');
        $this->assertTrue($jpt->getOption('sub') === 'test');
    }

    public function testSetOptIss()
    {
        $jpt = new Jpt();
        $jpt->setOptIss('test');
        $this->assertTrue($jpt->getOption('iss') === 'test');
    }

    public function testGenerate()
    {
        $jpt = new Jpt();
        $jpt->setOption('sub', 'test');
        $jpt->setOption('iss', 'test');
        $token = $jpt->generate();
        $this->assertTrue(strlen($token) > 0);

    }

    public function testGetExpiration()
    {
        $jpt = new Jpt();
        $token = $jpt->generate();

        sleep(1);
        $jpt = new Jpt();
        $jpt->validate($token);
        $this->assertEquals(3599,$jpt->getExpiration());
    }

    public function testGetPetalData()
    {
        $jpt = new Jpt();
        $jpt->setPetalData(['test' => 'test']);
        $token = $jpt->generate();

        $jpt = new Jpt();
        $jpt->validate($token);

        $this->assertEquals('test',$jpt->getPetalData('test'));
    }

    public function testGetCrownData()
    {
        $jpt = new Jpt();
        $jpt->setOption('sub', 'test');
        $jpt->setOption('iss', 'test');
        $jpt->setCrownData(['test' => 'test']);
        $token = $jpt->generate();

        $jpt = new Jpt();
        $jpt->setOptIssuers(['test']);
        $jpt->validate($token);

        $this->assertEquals('test',$jpt->getCrownData('test'));
    }
//
//    public function testSetOption()
//    {
//
//    }
//
//    public function testSetPetalData()
//    {
//
//    }
//
//    public function testSetCrownData()
//    {
//
//    }
//
//    public function testSetOptions()
//    {
//
//    }
//
//    public function testSetOptNbf()
//    {
//
//    }
//
//    public function testValidate()
//    {
//
//    }
//
//    public function testSetOptIssuers()
//    {
//
//    }
//
//    public function testSetOptAud()
//    {
//
//    }
}
