<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Pb {

  class DemoClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
      parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Pb\HelloRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     */
    public function Hello(\Pb\HelloRequest $argument,
      $metadata = [], $options = []) {
      return $this->_simpleRequest('/pb.Demo/Hello',
      $argument,
      ['\Pb\HelloReply', 'decode'],
      $metadata, $options);
    }

  }

}
