<?php
namespace App\Http\Controllers;

use whitemerry\phpkin\Metadata;
use whitemerry\phpkin\Tracer;
use whitemerry\phpkin\Endpoint;
use whitemerry\phpkin\Span;
use whitemerry\phpkin\Identifier\SpanIdentifier;
use whitemerry\phpkin\AnnotationBlock;
use whitemerry\phpkin\Logger\SimpleHttpLogger;
use whitemerry\phpkin\TracerInfo;

/**
 * Class HomeController
 * @package App\Http\Controllers
 */
class HomeController extends Controller
{
    private $zipkin_host;

    /**
     * @var SimpleHttpLogger
     */
    private $logger;

    /**
     * @var Tracer
     */
    private $tracer;

    public function __construct()
    {
        $this->zipkin_host = 'http://127.0.0.1:9411';
        $this->logger = new SimpleHttpLogger(['host' => $this->zipkin_host, 'muteErrors' => false]);
        $endpoint = new Endpoint('php-zipkin-demo', '127.0.0.1', '80');
        $this->tracer = new Tracer('home', $endpoint, $this->logger);
    }

    /**
     * @param string $service_name
     * @return object
     * @throws \Exception
     */
    public static function getService(string $service_name)
    {
        $consul_host = 'http://127.0.0.1:8500';

        //guzzle request options
        $options = [
            'base_uri' => $consul_host,
            'connect_timeout' => 3,
            'timeout' => 3,
        ];

        /**
         * @var \SensioLabs\Consul\Services\Health $h
         * @var \SensioLabs\Consul\ConsulResponse $resp
         */
        $h = (new \SensioLabs\Consul\ServiceFactory($options))->get('health');
        $resp = $h->service($service_name);
        $services = \json_decode($resp->getBody());
        if (!$services) {
            throw new \Exception('service '.$service_name.' not exists');
        }

        foreach ($services as $service) {
            if ($service->Service->Service != $service_name) {
                continue;
            }

            $del = false;
            foreach ($service->Checks as $check) {
                if ($check->Status == 'critical') {
                    $del = true;
                    break;
                }
            }
            if ($del) {
                continue;
            }
            //$host = $service->Service->Address.':'.$service->Service->Port;
            return $service->Service;
        }

        throw new \Exception('service '.$service_name.' not available');
    }

    /**
     * @param Metadata $meta
     * @return array
     */
    private static function getZipKinMetadata(Metadata $meta)
    {
        $return = [];
        foreach ($meta->toArray() as $m) {
            $return[$m['key']] = [$m['value']];
        }
        return $return;
    }

    private function span_http_request()
    {
        $request_start = zipkin_timestamp();
        $span_id = new SpanIdentifier();

        //your logic code, just for demo
        usleep(1500);

        $endpoint = new Endpoint('demo http request', '127.0.0.1', '80');
        $span = new Span($span_id, 'request /api/user/1', new AnnotationBlock($endpoint, $request_start));
        $this->tracer->addSpan($span);

        return [
            'code' => 0,
            'msg' => 'http request demo'
        ];
    }

    private function span_grpc_request()
    {
        $request_start = zipkin_timestamp();
        $span_id = new SpanIdentifier();

        //your logic code begin
        $q = '卧槽';
        $meta = new Metadata();
        $meta->set('X-B3-TraceId', (string)TracerInfo::getTraceId());
        $meta->set('X-B3-ParentSpanId', (string)TracerInfo::getTraceSpanId());
        $meta->set('X-B3-SpanId', (string)$span_id);
        $meta->set('X-B3-Sampled', true);

        $service_name = 'go.zipkin.demo';
        $service = self::getService($service_name);
        $hostname = $service->Address.':'.$service->Port;

        $opts = [
            'credentials' => \Grpc\ChannelCredentials::createInsecure()
        ];

        $client = new \Pb\DemoClient($hostname, $opts);
        $request = new \Pb\HelloRequest();
        $request->setQ($q);
        $request->setN(10);
        $call = $client->Hello($request, self::getZipKinMetadata($meta));
        /**
         * @var \Pb\HelloReply $reply
         */
        list($reply, $status) = $call->wait();
        if ($status->code == 0) {
            $code = $reply->getCode();
            $msg = $reply->getMsg();
            //TODO
        } else {
            $code = -1;
            $msg = 'grpc request failed';
        }

        //for demo
        usleep(200);


        $endpoint = new Endpoint('request '.$service_name, $service->Address, $service->Port);
        $span = new Span($span_id, "q: $q", new AnnotationBlock($endpoint, $request_start));
        $this->tracer->addSpan($span);

        return [$code, $msg];
    }

    public function test()
    {
        $http_ret = $this->span_http_request();
        $grpc_ret = $this->span_grpc_request();

        //send to zipkin
        $this->tracer->trace();

        $context = [
            'data' => [
                'http_result' => $http_ret,
                'grpc_result' => $grpc_ret,
                'trace_id' => (string)TracerInfo::getTraceId(),
                'parent_span_id' => (string)TracerInfo::getTraceSpanId(),
            ]
        ];

        return view('test', $context);
    }

}
