<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile as HttpUploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProxyService
{
    //Original request
    private Request $originalRequest;
    //Params from multipart requests
    private $multipartParams;
    //Custom Headers
    private $headers;
    //Custom Authorization
    private $authorization;
    //Custom Method
    private $customMethod;

    private $addQuery;

    //If needed add cookies support with:
    //  - PendingRequest->withCookies
    //  - Request->hasCookie
    //  - or custom

    //Settings
    private $useDefaultAuth;
    //It's recommandable check manually (for multipart exceptions and other things)
    // private $useDefaultHeaders;

    /**
     * @param Request $request
     * @param $useDefaultAuth
     * @return $this
     */
    public function createProxy(Request $request, $useDefaultAuth = true /*, $useDefaultHeaders = false*/){
        $this->originalRequest = $request;
        $this->multipartParams = $this->GetMultipartParams();
        $this->useDefaultAuth = $useDefaultAuth;
        // $this->useDefaultHeaders = $useDefaultHeaders;
        return $this;
    }

    public function withHeaders($headers){ $this->headers = $headers; return $this; }

    public function withBasicAuth($user, $secret){ $this->authorization = ['type' => 'basic', 'user' => $user, 'secret' => $secret ]; return $this; }

    public function withDigestAuth($user, $secret){ $this->authorization = ['type' => 'digest', 'user' => $user, 'secret' => $secret ]; return $this; }

    public function withToken($token){ $this->authorization = ['type' => 'token', 'token' => $token ]; return $this; }

    public function withMethod($method = 'POST'){ $this->customMethod = $method; return $this; }

    public function preserveQuery($preserve){ $this->addQuery = $preserve; return $this; }

    /**
     * @param $url
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function getResponse($url){

        $info = $this->getRequestInfo();
        $http = $this->createHttp($info['type']);
        $http = $this->setAuth($http, $info['token']);
        $http = $this->setHeaders($http);
        $http = $this->verify($http);

        if($this->addQuery && $info['query'])
            $url = $url.'?'.http_build_query($info['query']);

        // Remove cache for now
        //$key = hash('sha256', implode('|', Arr::only($info, ['method', 'type', 'full_url', 'token', 'format'])));

        //return Cache::remember($key, Config::get('cache.proxy_response_ttl'), function() use ($http, $info, $url){
            $response = $this->call($http, $info['method'], $url, $this->getParams($info));
            return response($this->isJson($response) ? $response->json() : $response->body(), $response->status());
        //});
    }

    public function toUrl($url){ return $this->getResponse($url); }

    public function toHost($host, $proxyController){
        $host .= (substr($host, -1) == '/' ? '' : '/');
        return $this->getResponse($host.str_replace($proxyController, '', $this->originalRequest->path()));
    }

    public function toService() {
        $service = $this->findService();

        $serviceSettings = Config::get('gateway.microservices.'.$service);
        if(!$serviceSettings) {
            throw new NotFoundHttpException("Service is not exist!");
        }
        $token = $this->originalRequest->bearerToken();
        $this->withToken($token);

        $headers = collect(['id','email','name','role'])->reduce(function($accumulator, $item){
            $accumulator['X-PENSOFT-USER-'.strtoupper($item)] = $this->originalRequest->user()->{$item};
            return $accumulator;
        }, []);
        if(!$this->originalRequest->user()->email) {
            $headers['X-PENSOFT-SERVICE'] = 1;
        }
        $this->withHeaders($headers);

        return $this->toHost($serviceSettings['host'], $serviceSettings['ignore_path']);
    }

    private function findService(){
        $service = $this->originalRequest->segment(1);
        if($service == 'api') {
            $service = $this->originalRequest->segment(2);
        }

        return $service;
    }

    private function getParams($info){
        $defaultParams = [];

        if($info['method'] == 'GET')
            return $info['params'];
        if($info['type'] == 'multipart')
            $defaultParams = $this->multipartParams;
        else
            $defaultParams = $info['params'];

        if($info['query']) {
            foreach ($info['query'] as $key => $value) {
                unset($defaultParams[array_search(['name' => $key, 'contents' => $value], $defaultParams, true)]);
            }
        }

        return $defaultParams;
    }

    private function setAuth(PendingRequest $request, $currentAuth = null){
        if(!$this->authorization)
            return $request;
        switch ($this->authorization['type']) {
            case 'basic':
                return $request->withBasicAuth($this->authorization['user'],$this->authorization['secret']);
            case 'digest':
                return $request->withDigestAuth($this->authorization['user'],$this->authorization['secret']);
            case 'token':
                return $request->withToken($this->authorization['token']);
            default:
                if($currentAuth && $this->useDefaultAuth)
                    return $request->withToken($currentAuth);
                return $request;
        }
    }

    private function verify(PendingRequest $request): PendingRequest
    {
        if(!env('VERIFY_SSL', true)){
            return $request->withoutVerifying();
        }
        return $request;
    }

    private function setHeaders(PendingRequest $request){
        if(!$this->headers)
            return $request;
        return $request->withHeaders($this->headers);
    }

    private function createHttp($type){
        switch ($type) {
            case 'multipart':
                return Http::asMultipart();
            case 'form':
                return Http::asForm();
            case 'json':
                return Http::asJson();
            case null:
                return new PendingRequest();
            default:
                return Http::contentType($type);
        }
    }

    private function call(PendingRequest $request, $method, $url, $params){
        if($this->customMethod)
            $method = $this->customMethod;
        switch ($method) {
            case 'GET':
                return $request->get($url, $params);
            case 'HEAD':
                return $request->head($url, $params);
            default:
            case 'POST':
                return $request->post($url, $params);
            case 'PATCH':
                return $request->patch($url, $params);
            case 'PUT':
                return $request->put($url, $params);
            case 'DELETE':
                return $request->delete($url, $params);
        }
    }

    private function getRequestInfo(){

        return [
            'type' => ($this->originalRequest->isJson() ? 'json' :
                (strpos($this->originalRequest->header('Content-Type'),'multipart') !== false ? 'multipart' :
                    ($this->originalRequest->header('Content-Type') == 'application/x-www-form-urlencoded' ? 'form' : $this->originalRequest->header('Content-Type')))),
            'agent' => $this->originalRequest->userAgent(),
            'method' => $this->originalRequest->method(),
            'token' => $this->originalRequest->bearerToken(),
            'full_url'=>$this->originalRequest->fullUrl(),
            'url'=>$this->originalRequest->url(),
            'format'=>$this->originalRequest->format(),
            'query' =>$this->originalRequest->query(),
            'params' => $this->originalRequest->isJson()? json_decode($this->originalRequest->getContent(), true) : $this->originalRequest->all(),
        ];
    }

    private function GetMultipartParams(){
        $multipartParams = [];
        if ($this->originalRequest->isMethod('post')) {
            $formParams = $this->originalRequest->all();
            $fileUploads = [];
            foreach ($formParams as $key => $param)
                if(is_array($param)){
                    foreach ($param as $k=>$p){
                        if ($p instanceof HttpUploadedFile) {
                            $fileUploads[$key][$k] = $p;
                            unset($formParams[$key][$k]);
                        }
                    }
                    if(sizeof($formParams[$key]) == 0){
                        unset($formParams[$key]);
                    }
                } elseif ($param instanceof HttpUploadedFile) {
                    $fileUploads[$key] = $param;
                    unset($formParams[$key]);
                }

            if (count($fileUploads) > 0){
                $multipartParams = [];
                foreach ($formParams as $key => $value)
                    $multipartParams[] = [
                        'name' => $key,
                        'contents' => $value
                    ];
                foreach ($fileUploads as $key => $value)
                    if(is_array($value)){
                        foreach($value as $k=>$v) {
                            $multipartParams[] = [
                                'name' => $key.'[]',
                                'contents' => fopen($v->getRealPath(), 'r'),
                                'filename' => $v->getClientOriginalName(),
                                'headers' => [
                                    'Content-Type' => $v->getMimeType()
                                ]
                            ];
                        }
                    } else {
                        $multipartParams[] = [
                            'name' => $key,
                            'contents' => fopen($value->getRealPath(), 'r'),
                            'filename' => $value->getClientOriginalName(),
                            'headers' => [
                                'Content-Type' => $value->getMimeType()
                            ]
                        ];
                    }
            }

        }

        return $multipartParams;
    }

    private function isJson(Response $response){
        return strpos($response->header('Content-Type'),'json') !== false;
    }
}
