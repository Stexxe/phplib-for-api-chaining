<?php

use PHPUnit\Framework\TestCase;

class ApiChainTest extends TestCase {
    /**
     * @expectedException \apiChain\ApiChainError
     * @expectedExceptionMessage Error while parsing chain config: Syntax error
     */
    public function testInvalidJSONThrowsException() {
        new \apiChain\apiChain("invalid json");
    }

    public function testDecreaseCallsRequestedForEachLink() {
        $chain = new \apiChain\apiChain(json_encode([[]]), false);
        $this->assertEquals(0, $chain->callsRequested);
    }

    public function testUseParentDataAsLastResponse() {
        $chain = new \apiChain\apiChain(json_encode([[]]), false, false, [], 'data');
        $this->assertEquals('data', $chain->lastResponse);
    }

    public function testReplaceGlobalsInLink() {
        $config = json_encode([
            $this->createRule(['href' => '/$global.main/${global.sub}/${global.nonexistent}'])
        ]);

        $handler = function ($resource) {
            $this->assertEquals('/path//${global.nonexistent}', $resource);
        };

        new \apiChain\apiChain($config, $handler, $this->createResponse(), [
            'main' => 'path',
            'sub' => 'test'
        ]);
    }

    public function testReplacePlaceholdersInData() {
        $config = json_encode([
            $this->createRule(['data' => ['key' => '$body.key']])
        ]);

        $handler = function ($_1, $_2, $_3, $body) {
            $this->assertEquals('val', $body->key);
        };

        $body = new stdClass();
        $body->key = 'val';
        new \apiChain\apiChain($config, $handler, $this->createResponse($body));
    }

    public function testChainWithoutHandler() {
        $config = json_encode([
            $this->createRule(['globals' => ['key' => 'val']])
        ]);

        $chain = new \apiChain\apiChain($config);
        $this->assertEquals(null, $chain->globals['key']);
    }

    public function testCheckForEvilHackers() {
        $config = json_encode([
            $this->createRule(['doOn' => 'always']),
            $this->createRule(['doOn' => 'hack']),
        ]);

        $chain = new \apiChain\apiChain($config);
        $this->assertEquals(2, $chain->callsRequested);
        $this->assertEquals(1, $chain->callsCompleted);
        $this->assertEquals(0.5, $chain->getCallPer());


        $config = json_encode([
            $this->createRule(['doOn' => '"mike" == \'mike\'']),
        ]);
        $chain = new \apiChain\apiChain($config, false, $this->createResponse());
        $this->assertEquals(1, $chain->callsCompleted);
    }

    public function testEvalFailsByParseError() {
        $config = json_encode([
            $this->createRule(['doOn' => '23\'2']),
        ]);

        $chain = new \apiChain\apiChain($config, false, $this->createResponse());
        $this->assertEquals(1, $chain->callsRequested);
        $this->assertEquals(0, $chain->callsCompleted);
    }

    public function testDefaultOutput() {
        $chain = new \apiChain\apiChain(json_encode([]), false, $this->createResponse());
        $this->assertEquals([
            'parentData' => false,
            'callsRequested' => 0,
            'callsCompleted' => 0,
            'globals' => [],
            'responses' => [],
            'lastResponse' => null,
        ], json_decode($chain->getOutput(), true));
    }

    private function createRule(array $partial) {
        return array_merge([
            'doOn' => 'always',
            'href' => '/',
            'method' => 'get',
            'data' => [],
            'return' => true,
        ], $partial);
    }

    private function createResponse($body = null) {
        $body = ($body === null ? new stdClass() : $body);
        return new \apiChain\apiResponse([], '', 0, [], $body, true);
    }
}