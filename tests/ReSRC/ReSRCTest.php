<?php

class ReSRCTest extends PHPUnit_Framework_TestCase {
    public function setUp() {
        $this->resrc = new ReSRC\ReSRC(array(
            'token' => 'testToken',
            'silent' => true
        ));
    }

    public function testConstructorRequiresApiToken() {
        try {
            $r = new ReSRC\ReSRC();
        } catch (Exception $e) {
            $this->assertEquals('API token required', $e->getMessage());
            return;
        }

        $this->fail('Expected exception not raised');
    }

    public function testGetParamsForValidRequest() {

        $stub = $this->getMockRequest("S=W400/O=90/http://foo.com/bar.jpg");

        $params = $this->resrc->getRequestParams($stub);

        $this->assertEquals(array(
            "parameters" => "S=W400/O=90",
            "protocol"  => "http://",
            "src"       => "foo.com/bar.jpg",
        ), $params);
    }

    public function testGetParamsForValidRequestWithoutParameters() {

        $stub = $this->getMockRequest("http://foo.com/bar.jpg");

        $params = $this->resrc->getRequestParams($stub);

        $this->assertEquals(array(
            "parameters" => "",
            "protocol"  => "http://",
            "src"       => "foo.com/bar.jpg",
        ), $params);
    }

    public function testGetParamsForValidRequestWithoutParametersAndLeadingSlash() {

        $stub = $this->getMockRequest("/http://foo.com/bar.jpg");

        $params = $this->resrc->getRequestParams($stub);

        $this->assertEquals(array(
            "parameters" => "",
            "protocol"  => "http://",
            "src"       => "foo.com/bar.jpg",
        ), $params);
    }

    public function testGetParamsForFileRequest() {

        $stub = $this->getMockRequest("S=W400/O=90/file:///var/www/bar.jpg");

        $params = $this->resrc->getRequestParams($stub);

        $this->assertEquals(array(
            "parameters" => "S=W400/O=90",
            "protocol"  => "file://",
            "src"       => "/var/www/bar.jpg",
        ), $params);
    }

    public function testGetParamsForValidHttpsRequest() {

        $stub = $this->getMockRequest("S=W400/O=90/https://foo.com/bar.jpg");

        $params = $this->resrc->getRequestParams($stub);

        $this->assertEquals(array(
            "parameters" => "S=W400/O=90",
            "protocol"  => "https://",
            "src"       => "foo.com/bar.jpg",
        ), $params);
    }

    public function testGetParamsForInvalidRequestThrowsException() {

        $stub = $this->getMockRequest("invalid");

        try {
            $params = $this->resrc->getRequestParams($stub);
        } catch (Exception $e) {
            $this->assertEquals("The input string [invalid] does not appear to be valid", $e->getMessage());
            return;
        }

        $this->fail('Expected exception not raised');
    }

    /**
     * test helper methods
     */

    protected function getMockRequest($uri) {
        $stub = $this->getMockBuilder("ReSRC\Request")
                     ->disableOriginalConstructor()
                     ->getMock();

        $stub->expects($this->any())
             ->method("getUri")
             ->will($this->returnValue($uri));

        return $stub;
    }
}
